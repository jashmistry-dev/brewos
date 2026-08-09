<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CafeUser;
use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    protected function parseDateRange(?string $startDate, ?string $endDate): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->subDays(30)->startOfDay();
        $end   = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        return [$start, $end];
    }

    public function getSalesReport(int $cafeId, ?string $startDate, ?string $endDate, ?int $branchId = null): array
    {
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        $stats = Order::where('cafe_id', $cafeId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('
                COUNT(*) as total_orders,
                COALESCE(SUM(total), 0) as total_sales
            ')
            ->first();

        $totalOrders = (int) ($stats->total_orders ?? 0);
        $totalSales  = round((float) ($stats->total_sales ?? 0.00), 2);
        $avgOrderValue = $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0.00;

        // Daily breakdown
        $dailyBreakdown = Order::select([
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as order_count'),
            DB::raw('ROUND(SUM(total), 2) as sales_amount'),
        ])
            ->where('cafe_id', $cafeId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->map(fn ($row) => [
                'date'         => (string) $row->date,
                'order_count'  => (int) $row->order_count,
                'sales_amount' => (float) $row->sales_amount,
            ]);

        return [
            'summary' => [
                'total_orders'        => $totalOrders,
                'total_sales'         => $totalSales,
                'average_order_value' => $avgOrderValue,
            ],
            'daily_breakdown' => $dailyBreakdown,
            'filters' => [
                'start_date' => $start->toIso8601String(),
                'end_date'   => $end->toIso8601String(),
                'branch_id'  => $branchId,
            ],
        ];
    }

    public function getRevenueReport(int $cafeId, ?string $startDate, ?string $endDate, ?int $branchId = null): array
    {
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        $stats = Order::where('cafe_id', $cafeId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('
                COALESCE(SUM(subtotal), 0) as gross_subtotal,
                COALESCE(SUM(tax), 0) as total_tax,
                COALESCE(SUM(discount), 0) as total_discount,
                COALESCE(SUM(total), 0) as net_revenue
            ')
            ->first();

        $grossSubtotal = round((float) ($stats->gross_subtotal ?? 0.00), 2);
        $totalTax      = round((float) ($stats->total_tax ?? 0.00), 2);
        $totalDiscount = round((float) ($stats->total_discount ?? 0.00), 2);
        $netRevenue    = round((float) ($stats->net_revenue ?? 0.00), 2);

        // Payment method breakdown from payments table
        $paymentQuery = Payment::select([
            'method',
            DB::raw('ROUND(SUM(amount), 2) as total_amount'),
        ])
            ->where('cafe_id', $cafeId)
            ->whereBetween('created_at', [$start, $end]);

        if ($branchId) {
            $paymentQuery->whereHas('order', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }

        $paymentMethods = $paymentQuery->groupBy('method')->get();

        $byMethod = [
            'cash' => 0.00,
            'upi'  => 0.00,
            'card' => 0.00,
        ];

        foreach ($paymentMethods as $pm) {
            if (array_key_exists($pm->method, $byMethod)) {
                $byMethod[$pm->method] = (float) $pm->total_amount;
            }
        }

        return [
            'overview' => [
                'gross_subtotal' => $grossSubtotal,
                'total_tax'      => $totalTax,
                'total_discount' => $totalDiscount,
                'net_revenue'    => $netRevenue,
            ],
            'payment_methods' => $byMethod,
            'filters' => [
                'start_date' => $start->toIso8601String(),
                'end_date'   => $end->toIso8601String(),
                'branch_id'  => $branchId,
            ],
        ];
    }

    public function getStaffReport(int $cafeId, ?string $startDate, ?string $endDate, ?int $branchId = null): array
    {
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        $staffQuery = CafeUser::where('cafe_id', $cafeId)
            ->with(['user:id,name,email', 'role:id,name,slug', 'branch:id,name']);

        if ($branchId) {
            $staffQuery->where('branch_id', $branchId);
        }

        $staffMembers = $staffQuery->get();

        $staffPerformance = $staffMembers->map(function ($cu) use ($cafeId, $start, $end) {
            $activityCount = AuditLog::where('cafe_id', $cafeId)
                ->where('user_id', $cu->user_id)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            return [
                'id'                  => $cu->id,
                'user_id'             => $cu->user_id,
                'name'                => $cu->user?->name,
                'email'               => $cu->user?->email,
                'role'                => $cu->role?->name,
                'branch'              => $cu->branch?->name,
                'status'              => $cu->status,
                'recorded_activities' => $activityCount,
            ];
        });

        return [
            'staff_activity' => $staffPerformance,
            'filters' => [
                'start_date' => $start->toIso8601String(),
                'end_date'   => $end->toIso8601String(),
                'branch_id'  => $branchId,
            ],
        ];
    }
}
