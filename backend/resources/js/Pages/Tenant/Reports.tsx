import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, usePage, Link } from '@inertiajs/react';

interface ReportData {
    period?: { start_date: string | null; end_date: string | null };
    summary?: {
        total_orders?: number;
        total_sales?: number;
        gross_sales?: number;
        total_tax?: number;
        total_discount?: number;
        net_revenue?: number;
        average_order_value?: number;
    };
    payment_methods?: { [method: string]: number };
    staff_activity?: Array<{
        user_id: number;
        name: string;
        orders_processed: number;
        sales_generated: number;
    }>;
}

interface Props {
    activeTab: 'sales' | 'revenue' | 'staff';
    report: ReportData;
}

export default function Reports({ activeTab, report }: Props) {
    const { tenant } = usePage<{ tenant: { cafe?: { slug: string; name: string } } }>().props;
    const cafeSlug = tenant.cafe?.slug || '';

    const summary = report.summary || {};

    return (
        <AppLayout title="Operational & Sales Reports" cafeSlug={cafeSlug}>
            <Head title={`Reports (${activeTab}) — ${tenant.cafe?.name || 'Workspace'}`} />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-stone-200 pb-4">
                    <div>
                        <h1 className="text-2xl font-bold text-stone-900 tracking-tight">Business Reports</h1>
                        <p className="text-xs text-stone-500 mt-0.5">Analyze revenue trends, sales breakdowns, and staff operational efficiency.</p>
                    </div>

                    {/* Report Tab Pills */}
                    <div className="flex items-center gap-1.5 bg-stone-100 p-1 rounded-xl">
                        {[
                            { key: 'sales', name: '📊 Sales Summary', href: `/cafes/${cafeSlug}/reports/sales` },
                            { key: 'revenue', name: '💵 Revenue Breakdown', href: `/cafes/${cafeSlug}/reports/revenue` },
                            { key: 'staff', name: '👥 Staff Performance', href: `/cafes/${cafeSlug}/reports/staff` },
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

                {/* Key Metrics Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm">
                        <span className="text-xs text-stone-400 font-bold uppercase tracking-wider">Total Sales</span>
                        <p className="text-2xl font-extrabold text-stone-900 mt-1">${(summary.total_sales || summary.gross_sales || 0).toFixed(2)}</p>
                    </div>

                    <div className="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm">
                        <span className="text-xs text-stone-400 font-bold uppercase tracking-wider">Net Revenue</span>
                        <p className="text-2xl font-extrabold text-amber-700 mt-1">${(summary.net_revenue || summary.total_sales || 0).toFixed(2)}</p>
                    </div>

                    <div className="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm">
                        <span className="text-xs text-stone-400 font-bold uppercase tracking-wider">Orders Completed</span>
                        <p className="text-2xl font-extrabold text-stone-900 mt-1">{summary.total_orders || 0}</p>
                    </div>

                    <div className="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm">
                        <span className="text-xs text-stone-400 font-bold uppercase tracking-wider">Avg Order Value (AOV)</span>
                        <p className="text-2xl font-extrabold text-stone-900 mt-1">${(summary.average_order_value || 0).toFixed(2)}</p>
                    </div>
                </div>

                {/* Report Content Details */}
                {activeTab === 'revenue' && report.payment_methods && (
                    <div className="bg-white rounded-2xl border border-stone-200 p-6 shadow-sm space-y-4">
                        <h3 className="text-sm font-bold text-stone-900 uppercase tracking-wider">Revenue Breakdown by Payment Method</h3>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            {Object.entries(report.payment_methods).map(([m, amt]) => (
                                <div key={m} className="bg-stone-50 p-4 rounded-xl border border-stone-200">
                                    <span className="text-xs text-stone-500 font-bold uppercase">{m}</span>
                                    <p className="text-lg font-extrabold text-stone-900 mt-1">${amt.toFixed(2)}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {activeTab === 'staff' && report.staff_activity && (
                    <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
                        <div className="p-4 border-b border-stone-100">
                            <h3 className="text-sm font-bold text-stone-900">Staff Performance Activity</h3>
                        </div>
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="bg-stone-50 border-b border-stone-200 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                                    <th className="py-3 px-4">Staff Member</th>
                                    <th className="py-3 px-4">Orders Processed</th>
                                    <th className="py-3 px-4">Sales Generated</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-stone-100 text-xs">
                                {report.staff_activity.map((s) => (
                                    <tr key={s.user_id} className="hover:bg-stone-50/80">
                                        <td className="py-3.5 px-4 font-bold text-stone-900">{s.name}</td>
                                        <td className="py-3.5 px-4 font-semibold text-stone-700">{s.orders_processed}</td>
                                        <td className="py-3.5 px-4 font-extrabold text-amber-700">${s.sales_generated ? s.sales_generated.toFixed(2) : 0}</td>
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
