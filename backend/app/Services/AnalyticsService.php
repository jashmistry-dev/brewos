<?php

namespace App\Services;

use App\Models\Cafe;
use App\Models\Customer;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    protected function parseDateRange(?string $startDate, ?string $endDate): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->subDays(30)->startOfDay();
        $end   = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        return [$start, $end];
    }

    public function getCustomerAnalytics(int $cafeId, ?string $startDate, ?string $endDate, ?int $branchId = null): array
    {
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        $totalRegisteredCustomers = Customer::where('cafe_id', $cafeId)->count();

        $totalCustomersWithOrders = Order::where('cafe_id', $cafeId)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('customer_id')
            ->whereBetween('created_at', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->distinct()
            ->count('customer_id');

        $repeatCustomers = Order::where('cafe_id', $cafeId)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('customer_id')
            ->whereBetween('created_at', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->select('customer_id', DB::raw('COUNT(*) as order_count'))
            ->groupBy('customer_id')
            ->having('order_count', '>=', 2)
            ->get()
            ->count();

        $repeatCustomerRate = $totalCustomersWithOrders > 0
            ? round(($repeatCustomers / $totalCustomersWithOrders) * 100, 2)
            : 0.00;

        $registeredCustomerSales = (float) Order::where('cafe_id', $cafeId)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('customer_id')
            ->whereBetween('created_at', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->sum('total');

        $averageSpendPerCustomer = $totalCustomersWithOrders > 0
            ? round($registeredCustomerSales / $totalCustomersWithOrders, 2)
            : 0.00;

        $guestOrders = Order::where('cafe_id', $cafeId)
            ->where('status', '!=', 'cancelled')
            ->whereNull('customer_id')
            ->whereBetween('created_at', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();

        return [
            'summary' => [
                'total_registered_customers'  => $totalRegisteredCustomers,
                'total_customers_with_orders' => $totalCustomersWithOrders,
                'repeat_customers'            => $repeatCustomers,
                'repeat_customer_rate'        => $repeatCustomerRate,
                'average_spend_per_customer'  => $averageSpendPerCustomer,
                'guest_orders'                => $guestOrders,
            ],
            'filters' => [
                'start_date' => $start->toIso8601String(),
                'end_date'   => $end->toIso8601String(),
                'branch_id'  => $branchId,
            ],
        ];
    }

    public function getMenuAnalytics(int $cafeId, ?string $startDate, ?string $endDate, ?int $branchId = null, ?int $limit = null): array
    {
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->leftJoin('categories', 'menu_items.category_id', '=', 'categories.id')
            ->where('orders.cafe_id', $cafeId)
            ->where('orders.status', '!=', 'cancelled')
            ->whereBetween('orders.created_at', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('orders.branch_id', $branchId))
            ->select([
                'order_items.menu_item_id',
                DB::raw('COALESCE(menu_items.name, "Unknown Item") as menu_item_name'),
                'menu_items.category_id',
                DB::raw('COALESCE(categories.name, "Unknown Category") as category_name'),
                DB::raw('CAST(SUM(order_items.quantity) AS UNSIGNED) as quantity_sold'),
                DB::raw('ROUND(CAST(SUM(order_items.total) AS DECIMAL(12,2)), 2) as revenue'),
            ])
            ->groupBy(
                'order_items.menu_item_id',
                'menu_items.name',
                'menu_items.category_id',
                'categories.name'
            )
            ->orderBy(DB::raw('SUM(order_items.quantity)'), 'desc')
            ->orderBy(DB::raw('SUM(order_items.total)'), 'desc')
            ->orderBy('order_items.menu_item_id', 'asc');

        if ($limit) {
            $query->limit($limit);
        }

        $menuPerformance = $query->get()->map(fn ($row) => [
            'menu_item_id'   => (int) $row->menu_item_id,
            'menu_item_name' => (string) $row->menu_item_name,
            'category_id'    => $row->category_id !== null ? (int) $row->category_id : null,
            'category_name'  => $row->category_name !== null ? (string) $row->category_name : null,
            'quantity_sold'  => (int) $row->quantity_sold,
            'revenue'        => (float) $row->revenue,
        ]);

        return [
            'menu_performance' => $menuPerformance,
            'filters' => [
                'start_date' => $start->toIso8601String(),
                'end_date'   => $end->toIso8601String(),
                'branch_id'  => $branchId,
                'limit'      => $limit,
            ],
        ];
    }

    public function getPeakHourAnalytics(int $cafeId, ?string $startDate, ?string $endDate, ?int $branchId = null): array
    {
        [$start, $end] = $this->parseDateRange($startDate, $endDate);

        $cafe     = Cafe::find($cafeId);
        $timezone = $cafe?->timezone ?? 'Asia/Kolkata';
        $tzOffset = Carbon::now($timezone)->format('P');

        $hourlyData = Order::where('cafe_id', $cafeId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->select([
                DB::raw("DATE_FORMAT(created_at, '%H') as hour_str"),
                DB::raw('COUNT(*) as order_count'),
                DB::raw('ROUND(SUM(total), 2) as revenue'),
            ])
            ->groupBy('hour_str')
            ->get()
            ->keyBy('hour_str');

        $buckets        = [];
        $peakHour       = '00';
        $peakOrderCount = 0;

        for ($h = 0; $h < 24; $h++) {
            $hourStr = sprintf('%02d', $h);
            $row     = $hourlyData->get($hourStr);

            $count   = $row ? (int) $row->order_count : 0;
            $revenue = $row ? (float) $row->revenue : 0.00;

            if ($count > $peakOrderCount) {
                $peakOrderCount = $count;
                $peakHour       = $hourStr;
            }

            $buckets[] = [
                'hour'        => $hourStr,
                'order_count' => $count,
                'revenue'     => $revenue,
            ];
        }

        return [
            'summary' => [
                'peak_hour'        => $peakHour,
                'peak_order_count' => $peakOrderCount,
            ],
            'hourly_breakdown' => $buckets,
            'filters' => [
                'start_date' => $start->toIso8601String(),
                'end_date'   => $end->toIso8601String(),
                'branch_id'  => $branchId,
                'timezone'   => $timezone,
            ],
        ];
    }
}
