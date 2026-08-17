import React from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head } from '@inertiajs/react';

interface Metrics {
    total_platform_revenue: number;
    mrr: number;
    arr: number;
    active_subscriptions: number;
}

interface SubscriptionPlanRevenue {
    plan_id: number;
    name: string;
    price: number;
    sub_count: number;
    mrr_contrib: number;
}

interface TopCafe {
    id: number;
    name: string;
    slug: string;
    status: string;
    total_revenue: number;
}

interface Props {
    metrics: Metrics;
    revenue_by_plan: SubscriptionPlanRevenue[];
    top_cafes: TopCafe[];
}

export default function Revenue({ metrics, revenue_by_plan, top_cafes }: Props) {
    const totalRev = Number((metrics && metrics.total_platform_revenue) || 0);
    const mrr = Number((metrics && metrics.mrr) || 0);
    const arr = Number((metrics && metrics.arr) || 0);

    return (
        <AdminLayout title="Platform Revenue Insights">
            <Head title="Platform Revenue Insights — Admin" />

            <div className="space-y-6">
                <div className="border-b border-stone-800 pb-4">
                    <h2 className="text-xl font-bold text-white tracking-tight">Financial Overview & Platform Revenue</h2>
                    <p className="text-xs text-stone-400 mt-1">
                        Track platform MRR, ARR, active subscription revenues, and top revenue-generating cafes across BrewOS.
                    </p>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-stone-950 border border-stone-800 rounded-2xl p-5 shadow-sm">
                        <span className="text-xs font-bold uppercase tracking-wider text-stone-400">Total Platform Sales</span>
                        <p className="text-2xl font-extrabold text-amber-400 mt-1">Rs. {totalRev.toFixed(2)}</p>
                    </div>

                    <div className="bg-stone-950 border border-stone-800 rounded-2xl p-5 shadow-sm">
                        <span className="text-xs font-bold uppercase tracking-wider text-stone-400">Monthly Recurring (MRR)</span>
                        <p className="text-2xl font-extrabold text-emerald-400 mt-1">Rs. {mrr.toFixed(2)}</p>
                    </div>

                    <div className="bg-stone-950 border border-stone-800 rounded-2xl p-5 shadow-sm">
                        <span className="text-xs font-bold uppercase tracking-wider text-stone-400">Annual Run-Rate (ARR)</span>
                        <p className="text-2xl font-extrabold text-stone-100 mt-1">Rs. {arr.toFixed(2)}</p>
                    </div>

                    <div className="bg-stone-950 border border-stone-800 rounded-2xl p-5 shadow-sm">
                        <span className="text-xs font-bold uppercase tracking-wider text-stone-400">Active Subscriptions</span>
                        <p className="text-2xl font-extrabold text-amber-400 mt-1">{metrics && metrics.active_subscriptions ? metrics.active_subscriptions : 0}</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div className="bg-stone-950 border border-stone-800 rounded-2xl p-5 shadow-sm space-y-4">
                        <h3 className="text-sm font-bold text-stone-200 uppercase tracking-wider">Subscription Revenue by Plan</h3>
                        <div className="divide-y divide-stone-800">
                            {(revenue_by_plan || []).map((p) => (
                                <div key={p.plan_id} className="py-3 flex items-center justify-between">
                                    <div>
                                        <p className="text-xs font-bold text-stone-100">{p.name}</p>
                                        <p className="text-[10px] text-stone-400 font-mono">Rs. {Number(p.price || 0).toFixed(2)} / month</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-xs font-extrabold text-emerald-400">Rs. {Number(p.mrr_contrib || 0).toFixed(2)} MRR</p>
                                        <p className="text-[10px] text-stone-400 font-semibold">{p.sub_count} Active Subs</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="bg-stone-950 border border-stone-800 rounded-2xl p-5 shadow-sm space-y-4">
                        <h3 className="text-sm font-bold text-stone-200 uppercase tracking-wider">Top Cafes by Gross Sales</h3>
                        <div className="divide-y divide-stone-800">
                            {(top_cafes || []).map((c) => (
                                <div key={c.id} className="py-3 flex items-center justify-between">
                                    <div>
                                        <p className="text-xs font-bold text-stone-100">{c.name}</p>
                                        <p className="text-[10px] text-stone-400 font-mono">slug: {c.slug}</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-xs font-extrabold text-amber-400">Rs. {Number(c.total_revenue || 0).toFixed(2)}</p>
                                        <span className="text-[9px] font-bold uppercase text-emerald-400 px-1.5 py-0.5 rounded bg-emerald-950/60 border border-emerald-800">
                                            {c.status}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
