import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import StatusBadge from '../../Components/StatusBadge';

export interface OwnerInfo {
    name: string;
    email: string;
    phone: string | null;
}

export interface PlanInfo {
    id: number;
    name: string;
    slug: string;
    price: string | number;
    billing_interval: string;
}

export interface SubscriptionInfo {
    id: number;
    status: string;
    starts_at: string | null;
    ends_at: string | null;
    trial_ends_at: string | null;
    expiry_recommendation: 'active' | 'expiring_soon' | 'expired';
}

export interface CafeListItem {
    id: number;
    name: string;
    slug: string;
    email: string;
    phone: string | null;
    status: string;
    owner: OwnerInfo | null;
    plan: PlanInfo | null;
    subscription: SubscriptionInfo | null;
    branches_count: number;
    users_count: number;
    customers_count: number;
    orders_count: number;
    revenue: number;
    created_at: string;
}

export interface PlanOption {
    id: number;
    name: string;
    slug: string;
}

export interface CafesPageProps {
    cafes: CafeListItem[];
    plans: PlanOption[];
    metrics: {
        total_cafes: number;
        active_cafes: number;
        suspended_cafes: number;
        total_revenue: number;
    };
    filters: {
        search?: string;
        status?: string;
        subscription_status?: string;
        plan_id?: string;
    };
}

export default function AdminCafes({ cafes, plans, metrics, filters }: CafesPageProps) {
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [subStatusFilter, setSubStatusFilter] = useState(filters.subscription_status || '');
    const [planFilter, setPlanFilter] = useState(filters.plan_id || '');

    const handleFilterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/cafes', {
            search: search || undefined,
            status: statusFilter || undefined,
            subscription_status: subStatusFilter || undefined,
            plan_id: planFilter || undefined,
        }, { preserveState: true });
    };

    const handleReset = () => {
        setSearch('');
        setStatusFilter('');
        setSubStatusFilter('');
        setPlanFilter('');
        router.get('/admin/cafes', {}, { preserveState: true });
    };

    const formatCurrency = (val: number) => {
        return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(val);
    };

    return (
        <AdminLayout title="Cafe Management">
            <Head title="Customer Cafes - Super Admin" />

            {/* Top Metrics Row */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                <div className="bg-stone-800/80 p-5 rounded-xl border border-stone-700/60 shadow-sm flex items-center justify-between">
                    <div>
                        <p className="text-xs font-semibold text-stone-400 uppercase tracking-wider">Total Customer Cafes</p>
                        <h3 className="text-3xl font-extrabold text-white mt-1">{metrics.total_cafes}</h3>
                    </div>
                    <span className="text-3xl">☕</span>
                </div>

                <div className="bg-stone-800/80 p-5 rounded-xl border border-stone-700/60 shadow-sm flex items-center justify-between">
                    <div>
                        <p className="text-xs font-semibold text-stone-400 uppercase tracking-wider">Active Cafes</p>
                        <h3 className="text-3xl font-extrabold text-emerald-400 mt-1">{metrics.active_cafes}</h3>
                    </div>
                    <span className="text-3xl">✅</span>
                </div>

                <div className="bg-stone-800/80 p-5 rounded-xl border border-stone-700/60 shadow-sm flex items-center justify-between">
                    <div>
                        <p className="text-xs font-semibold text-stone-400 uppercase tracking-wider">Suspended Cafes</p>
                        <h3 className="text-3xl font-extrabold text-rose-400 mt-1">{metrics.suspended_cafes}</h3>
                    </div>
                    <span className="text-3xl">⚠️</span>
                </div>

                <div className="bg-stone-800/80 p-5 rounded-xl border border-stone-700/60 shadow-sm flex items-center justify-between">
                    <div>
                        <p className="text-xs font-semibold text-stone-400 uppercase tracking-wider">Total System Revenue</p>
                        <h3 className="text-2xl font-extrabold text-amber-400 mt-1">{formatCurrency(metrics.total_revenue)}</h3>
                    </div>
                    <span className="text-3xl">💰</span>
                </div>
            </div>

            {/* Filter Controls Bar */}
            <div className="bg-stone-800/80 p-5 rounded-xl border border-stone-700/60 shadow-sm mb-6">
                <form onSubmit={handleFilterSubmit} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                    {/* Search */}
                    <div>
                        <label className="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-1.5">Search</label>
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Name, slug, or email..."
                            className="w-full px-3 py-2 bg-stone-900 border border-stone-700 rounded-lg text-xs text-stone-200 placeholder-stone-500 focus:outline-none focus:border-amber-500"
                        />
                    </div>

                    {/* Status Filter */}
                    <div>
                        <label className="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-1.5">Cafe Status</label>
                        <select
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                            className="w-full px-3 py-2 bg-stone-900 border border-stone-700 rounded-lg text-xs text-stone-200 focus:outline-none focus:border-amber-500"
                        >
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>

                    {/* Subscription Status Filter */}
                    <div>
                        <label className="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-1.5">Sub Status</label>
                        <select
                            value={subStatusFilter}
                            onChange={(e) => setSubStatusFilter(e.target.value)}
                            className="w-full px-3 py-2 bg-stone-900 border border-stone-700 rounded-lg text-xs text-stone-200 focus:outline-none focus:border-amber-500"
                        >
                            <option value="">All Subscriptions</option>
                            <option value="active">Active</option>
                            <option value="trialing">Trialing</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>

                    {/* Plan Filter */}
                    <div>
                        <label className="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-1.5">Plan</label>
                        <select
                            value={planFilter}
                            onChange={(e) => setPlanFilter(e.target.value)}
                            className="w-full px-3 py-2 bg-stone-900 border border-stone-700 rounded-lg text-xs text-stone-200 focus:outline-none focus:border-amber-500"
                        >
                            <option value="">All Plans</option>
                            {plans.map((p) => (
                                <option key={p.id} value={p.id}>{p.name}</option>
                            ))}
                        </select>
                    </div>

                    {/* Buttons */}
                    <div className="flex gap-2">
                        <button
                            type="submit"
                            className="flex-1 py-2 bg-amber-500 hover:bg-amber-600 text-stone-950 font-bold text-xs rounded-lg transition-colors"
                        >
                            Apply
                        </button>
                        <button
                            type="button"
                            onClick={handleReset}
                            className="px-3 py-2 bg-stone-700 hover:bg-stone-600 text-stone-300 font-semibold text-xs rounded-lg transition-colors"
                        >
                            Reset
                        </button>
                    </div>
                </form>
            </div>

            {/* Cafes List (Desktop Table + Mobile Cards) */}
            <div className="bg-stone-800/80 rounded-xl border border-stone-700/60 shadow-sm overflow-hidden">
                {cafes.length === 0 ? (
                    <div className="p-12 text-center text-stone-400 text-sm">
                        No customer cafes match your current search or filter criteria.
                    </div>
                ) : (
                    <>
                        {/* Desktop Table View */}
                        <div className="hidden md:block overflow-x-auto">
                            <table className="w-full text-left text-sm text-stone-300 min-w-[1050px]">
                                <thead className="bg-stone-900/60 text-xs font-semibold uppercase tracking-wider text-stone-400 border-b border-stone-700/60">
                                    <tr>
                                        <th className="px-5 py-3.5">Cafe / Slug</th>
                                        <th className="px-5 py-3.5">Owner</th>
                                        <th className="px-5 py-3.5">Status</th>
                                        <th className="px-5 py-3.5">Plan & Subscription</th>
                                        <th className="px-4 py-3.5 text-center">Branches</th>
                                        <th className="px-4 py-3.5 text-center">Staff</th>
                                        <th className="px-4 py-3.5 text-center">Orders</th>
                                        <th className="px-5 py-3.5 text-right">Revenue</th>
                                        <th className="px-5 py-3.5 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-stone-700/40">
                                    {cafes.map((cafe) => (
                                        <tr key={cafe.id} className="hover:bg-stone-700/30 transition-colors">
                                            {/* Name & Slug */}
                                            <td className="px-5 py-4">
                                                <div className="font-bold text-white text-sm">{cafe.name}</div>
                                                <div className="text-xs font-mono text-amber-400/90">{cafe.slug}</div>
                                                <div className="text-[11px] text-stone-400">{cafe.email}</div>
                                            </td>

                                            {/* Owner */}
                                            <td className="px-5 py-4">
                                                {cafe.owner ? (
                                                    <div>
                                                        <div className="font-medium text-stone-200 text-xs">{cafe.owner.name}</div>
                                                        <div className="text-[11px] text-stone-400">{cafe.owner.email}</div>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-stone-500 italic">No Owner Assigned</span>
                                                )}
                                            </td>

                                            {/* Status */}
                                            <td className="px-5 py-4">
                                                <StatusBadge status={cafe.status} />
                                            </td>

                                            {/* Plan & Sub */}
                                            <td className="px-5 py-4">
                                                <div>
                                                    <span className="font-semibold text-xs text-amber-400">{cafe.plan?.name || 'No Active Plan'}</span>
                                                    {cafe.subscription && (
                                                        <div className="mt-1 flex items-center gap-1.5 flex-wrap">
                                                            <StatusBadge status={cafe.subscription.status} />
                                                            {cafe.subscription.expiry_recommendation === 'expired' && (
                                                                <span className="bg-rose-900/60 text-rose-300 text-[10px] px-1.5 py-0.5 rounded font-bold border border-rose-700/50">
                                                                    Expired
                                                                </span>
                                                            )}
                                                            {cafe.subscription.expiry_recommendation === 'expiring_soon' && (
                                                                <span className="bg-amber-900/60 text-amber-300 text-[10px] px-1.5 py-0.5 rounded font-bold border border-amber-700/50">
                                                                    Expiring Soon
                                                                </span>
                                                            )}
                                                        </div>
                                                    )}
                                                </div>
                                            </td>

                                            {/* Counts */}
                                            <td className="px-4 py-4 text-center font-bold text-stone-200">{cafe.branches_count}</td>
                                            <td className="px-4 py-4 text-center font-bold text-stone-200">{cafe.users_count}</td>
                                            <td className="px-4 py-4 text-center font-bold text-stone-200">{cafe.orders_count}</td>

                                            {/* Revenue */}
                                            <td className="px-5 py-4 text-right font-mono font-bold text-emerald-400 whitespace-nowrap">
                                                {formatCurrency(cafe.revenue)}
                                            </td>

                                            {/* Actions */}
                                            <td className="px-5 py-4 text-right whitespace-nowrap">
                                                <Link
                                                    href={`/admin/cafes/${cafe.id}`}
                                                    className="inline-block text-xs font-bold px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-stone-950 rounded-lg shadow-sm transition-colors"
                                                >
                                                    Manage Cafe &rarr;
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Mobile Card Layout */}
                        <div className="block md:hidden divide-y divide-stone-700/60">
                            {cafes.map((cafe) => (
                                <div key={cafe.id} className="p-4 space-y-3">
                                    <div className="flex justify-between items-start">
                                        <div>
                                            <h4 className="font-extrabold text-base text-white">{cafe.name}</h4>
                                            <p className="text-xs font-mono text-amber-400">{cafe.slug}</p>
                                            <p className="text-xs text-stone-400">{cafe.email}</p>
                                        </div>
                                        <StatusBadge status={cafe.status} />
                                    </div>

                                    <div className="grid grid-cols-2 gap-2 text-xs bg-stone-900/60 p-3 rounded-xl border border-stone-700/40">
                                        <div>
                                            <span className="text-stone-500 block text-[10px] uppercase font-bold">Owner</span>
                                            <span className="text-stone-200 font-medium">{cafe.owner?.name || 'N/A'}</span>
                                        </div>
                                        <div>
                                            <span className="text-stone-500 block text-[10px] uppercase font-bold">Plan</span>
                                            <span className="text-amber-400 font-bold">{cafe.plan?.name || 'No Plan'}</span>
                                        </div>
                                        <div>
                                            <span className="text-stone-500 block text-[10px] uppercase font-bold">Branches / Staff</span>
                                            <span className="text-stone-200">{cafe.branches_count} b / {cafe.users_count} s</span>
                                        </div>
                                        <div>
                                            <span className="text-stone-500 block text-[10px] uppercase font-bold">Revenue</span>
                                            <span className="text-emerald-400 font-bold font-mono">{formatCurrency(cafe.revenue)}</span>
                                        </div>
                                    </div>

                                    <div className="pt-1 flex justify-end">
                                        <Link
                                            href={`/admin/cafes/${cafe.id}`}
                                            className="w-full text-center text-xs font-bold px-4 py-2 bg-amber-500 text-stone-950 rounded-xl shadow transition-colors"
                                        >
                                            Manage Cafe &rarr;
                                        </Link>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </>
                )}
            </div>
        </AdminLayout>
    );
}
