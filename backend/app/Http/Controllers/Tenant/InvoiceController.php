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
    public function index(\Illuminate\Http\Request $request): JsonResponse|\Inertia\Response
    {
        Gate::authorize('permission', 'invoice.view');

        $invoices = Invoice::with(['order'])->orderBy('created_at', 'desc')->get();

        $formattedInvoices = $invoices->map(fn ($inv) => $this->formatInvoice($inv));

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'invoices' => $formattedInvoices,
            ]);
        }

        return \Inertia\Inertia::render('Tenant/Invoices', [
            'invoices' => $formattedInvoices,
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

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Invoice created successfully.',
                'invoice' => $this->formatInvoice($invoice),
            ], HttpResponse::HTTP_CREATED);
        }

        return redirect()->back()->with('success', 'Invoice created successfully.');
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
            'order_number'   => (string) ($invoice->order?->public_order_number ?? $invoice->order?->order_number),
            'customer_name'  => $invoice->order?->customer_name ?? 'Guest Customer',
            'customer_phone' => $invoice->order?->customer_phone ?? 'N/A',
            'table_name'     => $invoice->order?->table?->name ?? 'Table N/A',
            'branch_name'    => $invoice->order?->branch?->name ?? 'Main Branch',
            'payment_method' => $invoice->order?->payments?->first()?->method ?? 'cash',
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
        $cafe = $invoice->cafe ?? $invoice->order?->cafe;
        $cafeName      = e($cafe?->name ?? "BrewOS Cafe");
        $cafeLogo      = $cafe?->logo_url;
        $cafeAddress   = e($cafe?->address ?? "");
        $cafeCityState = e(trim(($cafe?->city ?? "") . " " . ($cafe?->state ?? "") . " " . ($cafe?->postal_code ?? "")));
        $cafePhone     = e($cafe?->phone ?? "");
        $cafeEmail     = e($cafe?->email ?? "");
        $taxNumber     = e($cafe?->tax_number ?? "");

        $invoiceNumber = e($invoice->invoice_number);
        $orderNumber   = e("#" . ($invoice->order?->public_order_number ?? $invoice->order?->order_number ?? $invoice->order_id));
        $issuedAt      = $invoice->issued_at?->format("d M Y, h:i A") ?? date("d M Y, h:i A");
        $status        = strtoupper(e($invoice->status));
        $payMethod     = strtoupper(e($invoice->order?->payments?->first()?->method ?? "CASH"));
        $tableName     = e($invoice->order?->table?->name ?? "Dine-In");
        $customerName  = e($invoice->order?->customer_name ?? "Guest Customer");
        $customerPhone = e($invoice->order?->customer_phone ?? "N/A");

        $subtotal      = number_format((float) $invoice->subtotal, 2);
        $tax           = number_format((float) $invoice->tax, 2);
        $discount      = number_format((float) $invoice->discount, 2);
        $total         = number_format((float) $invoice->total, 2);

        $itemRows = "";
        if ($invoice->order?->orderItems) {
            foreach ($invoice->order->orderItems as $item) {
                $name  = e($item->menuItem?->name ?? "Item");
                $qty   = (int) $item->quantity;
                $price = number_format((float) $item->unit_price, 2);
                $lineTotal = number_format((float) $item->total, 2);
                $itemRows .= "<tr>
                    <td style=\"padding:10px 8px; border-bottom:1px solid #e2e8f0;\">{$name}</td>
                    <td style=\"padding:10px 8px; border-bottom:1px solid #e2e8f0; text-align:center;\">{$qty}</td>
                    <td style=\"padding:10px 8px; border-bottom:1px solid #e2e8f0; text-align:right;\">&#8377; {$price}</td>
                    <td style=\"padding:10px 8px; border-bottom:1px solid #e2e8f0; text-align:right; font-weight:600;\">&#8377; {$lineTotal}</td>
                </tr>\n";
            }
        }

        $logoHtml  = $cafeLogo ? "<img src=\"{$cafeLogo}\" alt=\"{$cafeName}\" style=\"max-height:64px; max-width:160px; object-fit:contain; margin-bottom:8px;\">" : "<div style=\"font-size:32px; margin-bottom:4px;\">☕</div>";
        $addrHtml  = $cafeAddress ? "<p class=\"cafe-meta\">{$cafeAddress}</p>" : "";
        $cityHtml  = $cafeCityState ? "<p class=\"cafe-meta\">{$cafeCityState}</p>" : "";
        $phoneHtml = $cafePhone ? "<p class=\"cafe-meta\">📞 {$cafePhone}</p>" : "";
        $emailHtml = $cafeEmail ? "<p class=\"cafe-meta\">✉️ {$cafeEmail}</p>" : "";
        $gstHtml   = $taxNumber ? "<p style=\"margin:2px 0; color:#64748b; font-size:11px;\"><strong>GSTIN / Tax ID:</strong> {$taxNumber}</p>" : "";
        $statusStyle = $status === 'PAID' ? 'background:#dcfce7; color:#15803d;' : 'background:#fef3c7; color:#b45309;';
        $discountRow = (float)$invoice->discount > 0 ? "<tr><td style=\"color:#64748b;\">Discount</td><td style=\"text-align:right; font-weight:600; color:#dc2626;\">- &#8377; {$discount}</td></tr>" : "";
        $taxRow      = (float)$invoice->tax > 0 ? "<tr><td style=\"color:#64748b;\">Tax / GST</td><td style=\"text-align:right; font-weight:600;\">&#8377; {$tax}</td></tr>" : "";

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice {$invoiceNumber} — {$cafeName}</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 24px; background: #f8fafc; color: #0f172a; }
  .invoice-card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 32px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
  .header-flex { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 20px; }
  .cafe-brand { flex: 1; }
  .cafe-name { font-size: 22px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0; }
  .cafe-meta { font-size: 12px; color: #64748b; margin: 2px 0; }
  .inv-badge { text-align: right; }
  .inv-title { font-size: 20px; font-weight: 800; color: #d97706; text-transform: uppercase; letter-spacing: 0.5px; margin: 0; }
  .badge { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 700; text-transform: uppercase; margin-top: 6px; }
  .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; background: #f8fafc; border-radius: 12px; padding: 16px; margin-bottom: 24px; border: 1px solid #f1f5f9; font-size: 12px; }
  .meta-label { color: #64748b; font-weight: 500; }
  .meta-val { color: #0f172a; font-weight: 700; margin-top: 2px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 13px; }
  th { background: #f8fafc; text-align: left; padding: 10px 8px; border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 700; font-size: 11px; text-transform: uppercase; }
  .totals-table { width: 260px; margin-left: auto; font-size: 13px; margin-bottom: 24px; }
  .totals-table td { padding: 6px 0; }
  .grand-row td { font-size: 16px; font-weight: 800; color: #0f172a; border-top: 2px solid #0f172a; padding-top: 10px; }
  .footer { border-top: 1px dashed #cbd5e1; padding-top: 16px; text-align: center; font-size: 12px; color: #64748b; }
  .btn-bar { text-align: center; margin-top: 20px; }
  .btn-print { background: #d97706; color: #ffffff; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; transition: background 0.2s; }
  .btn-print:hover { background: #b45309; }
  @media print {
    body { background: #ffffff; padding: 0; }
    .invoice-card { border: none; box-shadow: none; padding: 0; max-width: 100%; }
    .btn-bar { display: none; }
  }
</style>
</head>
<body>

<div class="invoice-card">
  <div class="header-flex">
    <div class="cafe-brand">
      {$logoHtml}
      <h1 class="cafe-name">{$cafeName}</h1>
      {$addrHtml}
      {$cityHtml}
      {$phoneHtml}
      {$emailHtml}
      {$gstHtml}
    </div>
    <div class="inv-badge">
      <h2 class="inv-title">Tax Invoice</h2>
      <p style="margin:4px 0 0 0; font-size:13px; font-family:monospace; font-weight:700;">{$invoiceNumber}</p>
      <span class="badge" style="{$statusStyle}">{$status}</span>
    </div>
  </div>

  <div class="meta-grid">
    <div>
      <div class="meta-label">Billed To</div>
      <div class="meta-val">{$customerName}</div>
      <div style="font-size:11px; color:#64748b; margin-top:2px;">📱 {$customerPhone}</div>
    </div>
    <div>
      <div class="meta-label">Invoice Details</div>
      <div class="meta-val">Order {$orderNumber}</div>
      <div style="font-size:11px; color:#64748b; margin-top:2px;">📅 {$issuedAt}</div>
      <div style="font-size:11px; color:#64748b; margin-top:2px;">🪑 {$tableName} • {$payMethod}</div>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Item Description</th>
        <th style="text-align:center;">Qty</th>
        <th style="text-align:right;">Price</th>
        <th style="text-align:right;">Total</th>
      </tr>
    </thead>
    <tbody>
      {$itemRows}
    </tbody>
  </table>

  <table class="totals-table">
    <tr>
      <td style="color:#64748b;">Subtotal</td>
      <td style="text-align:right; font-weight:600;">&#8377; {$subtotal}</td>
    </tr>
    {$discountRow}
    {$taxRow}
    <tr class="grand-row">
      <td>Grand Total</td>
      <td style="text-align:right; color:#d97706;">&#8377; {$total}</td>
    </tr>
  </table>

  <div class="footer">
    <p style="margin:0 0 4px 0; font-weight:700; color:#0f172a;">Thank you for visiting {$cafeName}!</p>
    <p style="margin:0; font-size:11px; color:#94a3b8;">This is a computer-generated receipt.</p>
  </div>
</div>

<div class="btn-bar">
  <button class="btn-print" onclick="window.print()">🖨️ Print / Save PDF</button>
</div>

</body>
</html>
HTML;
    }
}
