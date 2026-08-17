import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, usePage, Link } from '@inertiajs/react';

interface CustomerAnalytics {
    total_customers?: number;
    new_customers?: number;
    repeat_customers?: number;
    retention_rate?: number;
}

interface MenuItemAnalytics {
    id: number;
    name: string;
    category_name?: string;
    total_quantity_sold: number;
    total_revenue: number;
}

interface PeakHourAnalytics {
    hour: number;
    hour_formatted?: string;
    total_orders: number;
    total_revenue: number;
}

interface AnalyticsData {
    customers?: CustomerAnalytics;
    menu_items?: MenuItemAnalytics[];
    peak_hours?: PeakHourAnalytics[];
}

interface Props {
    activeTab: 'customers' | 'menu' | 'peakHours';
    analytics: AnalyticsData;
}

export default function Analytics({ activeTab, analytics }: Props) {
    const { tenant } = usePage<{ tenant: { cafe?: { slug: string; name: string } } }>().props;
    const cafeSlug = tenant.cafe?.slug || '';

    return (
        <AppLayout title="Advanced Analytics" cafeSlug={cafeSlug}>
            <Head title={`Analytics (${activeTab}) — ${tenant.cafe?.name || 'Workspace'}`} />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-stone-200 pb-4">
                    <div>
                        <h1 className="text-2xl font-bold text-stone-900 tracking-tight">Advanced Workspace Analytics</h1>
                        <p className="text-xs text-stone-500 mt-0.5">Deep insights into customer behavior, top-performing menu items, and peak dining hours.</p>
                    </div>

                    {/* Analytics Tab Pills */}
                    <div className="flex items-center gap-1.5 bg-stone-100 p-1 rounded-xl">
                        {[
                            { key: 'customers', name: '👥 Customer Insights', href: `/cafes/${cafeSlug}/analytics/customers` },
                            { key: 'menu', name: '☕ Popular Items', href: `/cafes/${cafeSlug}/analytics/menu` },
                            { key: 'peakHours', name: '⏰ Peak Hours', href: `/cafes/${cafeSlug}/analytics/peak-hours` },
                        ].map((t) => (
                            <Link
                                key={t.key}
                                href={t.href}
                                className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-colors ${
                                    activeTab === t.key
                                        ? 'bg-stone-900 text-white shadow-sm'
                                        : 'text-stone-600 hover:text-stone-900'
                                }`}
                            >
                                {t.name}
                            </Link>
                        ))}
                    </div>
                </div>

                {/* Customer Analytics View */}
                {activeTab === 'customers' && analytics.customers && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div className="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm">
                            <span className="text-xs text-stone-400 font-bold uppercase tracking-wider">Total Customers</span>
                            <p className="text-2xl font-extrabold text-stone-900 mt-1">{analytics.customers.total_customers || 0}</p>
                        </div>
                        <div className="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm">
                            <span className="text-xs text-stone-400 font-bold uppercase tracking-wider">New Customers</span>
                            <p className="text-2xl font-extrabold text-emerald-700 mt-1">{analytics.customers.new_customers || 0}</p>
                        </div>
                        <div className="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm">
                            <span className="text-xs text-stone-400 font-bold uppercase tracking-wider">Repeat Customers</span>
                            <p className="text-2xl font-extrabold text-amber-700 mt-1">{analytics.customers.repeat_customers || 0}</p>
                        </div>
                        <div className="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm">
                            <span className="text-xs text-stone-400 font-bold uppercase tracking-wider">Retention Rate</span>
                            <p className="text-2xl font-extrabold text-stone-900 mt-1">{(analytics.customers.retention_rate || 0).toFixed(1)}%</p>
                        </div>
                    </div>
                )}

                {/* Menu Analytics View */}
                {activeTab === 'menu' && analytics.menu_items && (
                    <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
                        <div className="p-4 border-b border-stone-100">
                            <h3 className="text-sm font-bold text-stone-900">Top Selling Menu Items & Revenue Contribution</h3>
                        </div>
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="bg-stone-50 border-b border-stone-200 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                                    <th className="py-3 px-4">Item Name</th>
                                    <th className="py-3 px-4">Quantity Sold</th>
                                    <th className="py-3 px-4">Total Revenue</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-stone-100 text-xs">
                                {analytics.menu_items.map((item) => (
                                    <tr key={item.id} className="hover:bg-stone-50/80">
                                        <td className="py-3.5 px-4 font-bold text-stone-900">{item.name}</td>
                                        <td className="py-3.5 px-4 font-semibold text-stone-700">{item.total_quantity_sold}</td>
                                        <td className="py-3.5 px-4 font-extrabold text-amber-700">${item.total_revenue.toFixed(2)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Peak Hours View */}
                {activeTab === 'peakHours' && analytics.peak_hours && (
                    <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
                        <div className="p-4 border-b border-stone-100">
                            <h3 className="text-sm font-bold text-stone-900">Orders & Revenue by Hour of Day</h3>
                        </div>
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="bg-stone-50 border-b border-stone-200 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                                    <th className="py-3 px-4">Hour</th>
                                    <th className="py-3 px-4">Orders Placed</th>
                                    <th className="py-3 px-4">Revenue Generated</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-stone-100 text-xs">
                                {analytics.peak_hours.map((ph) => (
                                    <tr key={ph.hour} className="hover:bg-stone-50/80">
                                        <td className="py-3.5 px-4 font-bold text-stone-900">{ph.hour_formatted || `${ph.hour}:00`}</td>
                                        <td className="py-3.5 px-4 font-semibold text-stone-700">{ph.total_orders}</td>
                                        <td className="py-3.5 px-4 font-extrabold text-amber-700">${ph.total_revenue.toFixed(2)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
