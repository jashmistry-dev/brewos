<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InvoiceController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('permission', 'invoice.view');

        $invoices = Invoice::with(['order'])->orderBy('created_at', 'desc')->get();

        return response()->json([
            'invoices' => $invoices->map(fn ($inv) => $this->formatInvoice($inv)),
        ]);
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        Gate::authorize('permission', 'invoice.create');

        $cafeId    = app(TenantContext::class)->getCafeId();
        $validated = $request->validated();

        // Verify order belongs to the active cafe (server-side)
        $order = Order::withoutGlobalScopes()
            ->where('id', $validated['order_id'])
            ->where('cafe_id', $cafeId)
            ->firstOrFail();

        // Server-side recalculation: derive total from subtotal + tax - discount
        $subtotal = round((float) $validated['subtotal'], 2);
        $tax      = round((float) ($validated['tax'] ?? 0), 2);
        $discount = round((float) ($validated['discount'] ?? 0), 2);
        $total    = round($subtotal + $tax - $discount, 2);

        // Enforce unique invoice_number per cafe
        $numberExists = Invoice::withoutGlobalScopes()
            ->where('cafe_id', $cafeId)
            ->where('invoice_number', $validated['invoice_number'])
            ->exists();

        if ($numberExists) {
            return response()->json([
                'message' => 'Invoice number already exists for this cafe.',
                'errors'  => [
                    'invoice_number' => ['The invoice number has already been taken for this cafe.'],
                ],
            ], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $invoice = Invoice::create([
            'cafe_id'        => $cafeId,
            'order_id'       => $order->id,
            'invoice_number' => $validated['invoice_number'],
            'subtotal'       => $subtotal,
            'tax'            => $tax,
            'discount'       => $discount,
            'total'          => $total,
            'status'         => $validated['status'] ?? 'issued',
            'issued_at'      => $validated['issued_at'] ?? now(),
        ]);

        $invoice->load('order');

        return response()->json([
            'message' => 'Invoice created successfully.',
            'invoice' => $this->formatInvoice($invoice),
        ], HttpResponse::HTTP_CREATED);
    }

    public function show(string $cafe_slug, int|string $invoice_id): JsonResponse
    {
        Gate::authorize('permission', 'invoice.view');

        $invoice = Invoice::with(['order.orderItems.menuItem', 'cafe'])->findOrFail($invoice_id);

        return response()->json([
            'invoice' => $this->formatInvoiceDetail($invoice),
        ]);
    }

    public function download(string $cafe_slug, int|string $invoice_id): Response
    {
        Gate::authorize('permission', 'invoice.download');

        $invoice = Invoice::with(['order.orderItems.menuItem', 'cafe'])->findOrFail($invoice_id);

        $cafeId = app(TenantContext::class)->getCafeId();
        if ($invoice->cafe_id !== $cafeId) {
            abort(404);
        }

        $html = $this->renderInvoiceHtml($invoice);

        return response($html, 200, [
            'Content-Type'        => 'text/html; charset=utf-8',
            'Content-Disposition' => 'inline; filename="invoice-' . $invoice->invoice_number . '.html"',
        ]);
    }

    private function formatInvoice(Invoice $invoice): array
    {
        return [
            'id'             => $invoice->id,
            'order_id'       => $invoice->order_id,
            'order_number'   => $invoice->order?->order_number,
            'invoice_number' => $invoice->invoice_number,
            'subtotal'       => (float) $invoice->subtotal,
            'tax'            => (float) $invoice->tax,
            'discount'       => (float) $invoice->discount,
            'total'          => (float) $invoice->total,
            'status'         => $invoice->status,
            'issued_at'      => $invoice->issued_at?->toIso8601String(),
            'created_at'     => $invoice->created_at?->toIso8601String(),
        ];
    }

    private function formatInvoiceDetail(Invoice $invoice): array
    {
        $base = $this->formatInvoice($invoice);

        $base['items'] = $invoice->order?->orderItems?->map(fn ($oi) => [
            'id'           => $oi->id,
            'name'         => $oi->menuItem?->name,
            'quantity'     => $oi->quantity,
            'unit_price'   => (float) $oi->unit_price,
            'total'        => (float) $oi->total,
        ])->toArray() ?? [];

        return $base;
    }

    private function renderInvoiceHtml(Invoice $invoice): string
    {
        $cafeName      = e($invoice->cafe?->name ?? 'BrewOS');
        $invoiceNumber = e($invoice->invoice_number);
        $issuedAt      = $invoice->issued_at?->format('d M Y') ?? date('d M Y');
        $status        = e($invoice->status);
        $subtotal      = number_format((float) $invoice->subtotal, 2);
        $tax           = number_format((float) $invoice->tax, 2);
        $discount      = number_format((float) $invoice->discount, 2);
        $total         = number_format((float) $invoice->total, 2);

        $itemRows = '';
        if ($invoice->order?->orderItems) {
            foreach ($invoice->order->orderItems as $item) {
                $name  = e($item->menuItem?->name ?? 'Item');
                $qty   = (int) $item->quantity;
                $price = number_format((float) $item->unit_price, 2);
                $lineTotal = number_format((float) $item->total, 2);
                $itemRows .= "<tr><td>{$name}</td><td>{$qty}</td><td>{$price}</td><td>{$lineTotal}</td></tr>\n";
            }
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice {$invoiceNumber}</title>
<style>
  body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
  h1 { font-size: 24px; margin-bottom: 4px; }
  .meta { color: #666; font-size: 14px; margin-bottom: 24px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  th { background: #f5f5f5; text-align: left; padding: 8px; border-bottom: 2px solid #ddd; }
  td { padding: 8px; border-bottom: 1px solid #eee; }
  .totals { text-align: right; }
  .totals td { border-bottom: none; }
  .grand-total td { font-weight: bold; font-size: 16px; border-top: 2px solid #333; }
  @media print { body { margin: 20px; } button { display: none; } }
</style>
</head>
<body>
<h1>{$cafeName}</h1>
<div class="meta">
  Invoice #: <strong>{$invoiceNumber}</strong> &nbsp;|&nbsp;
  Date: <strong>{$issuedAt}</strong> &nbsp;|&nbsp;
  Status: <strong>{$status}</strong>
</div>
<table>
  <thead>
    <tr><th>Item</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr>
  </thead>
  <tbody>
    {$itemRows}
  </tbody>
</table>
<table class="totals" style="width:300px;margin-left:auto;">
  <tr><td>Subtotal</td><td>&#8377; {$subtotal}</td></tr>
  <tr><td>Tax</td><td>&#8377; {$tax}</td></tr>
  <tr><td>Discount</td><td>- &#8377; {$discount}</td></tr>
  <tr class="grand-total"><td>Total</td><td>&#8377; {$total}</td></tr>
</table>
<button onclick="window.print()">Print / Save PDF</button>
</body>
</html>
HTML;
    }
}
