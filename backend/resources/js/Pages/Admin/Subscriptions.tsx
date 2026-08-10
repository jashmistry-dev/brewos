import React, { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';

interface SubscriptionItem {
    id: number;
    cafe_id: number;
    cafe_name?: string;
    cafe_slug?: string;
    plan_id: number;
    plan_name?: string;
    plan_price?: number;
    status: string;
    starts_at: string | null;
    ends_at: string | null;
    trial_ends_at: string | null;
    provider: string | null;
    provider_subscription_id: string | null;
    created_at: string;
}

interface Props {
    subscriptions: SubscriptionItem[];
    filters?: {
        status?: string;
    };
}

export default function Subscriptions({ subscriptions, filters }: Props) {
    const [selectedStatus, setSelectedStatus] = useState(filters?.status || '');

    const handleFilterChange = (status: string) => {
        setSelectedStatus(status);
        router.get('/admin/subscriptions', { status: status || undefined }, { preserveState: true });
    };

    const handleCancel = (subId: number) => {
        if (confirm('Are you sure you want to cancel this subscription?')) {
            router.post(`/admin/subscriptions/${subId}/cancel`, {}, { preserveScroll: true });
        }
    };

    return (
        <AdminLayout title="Platform Subscriptions">
            <Head title="Platform Subscriptions — Super Admin" />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-stone-800 pb-5">
                    <div>
                        <h1 className="text-2xl font-bold text-stone-100 tracking-tight">Platform Subscriptions</h1>
                        <p className="text-sm text-stone-400 mt-1">
                            Monitor and manage all tenant cafe subscriptions across the platform.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <select
                            value={selectedStatus}
                            onChange={(e) => handleFilterChange(e.target.value)}
                            className="bg-stone-800 text-stone-200 border border-stone-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none"
                        >
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="trialing">Trialing</option>
                            <option value="past_due">Past Due</option>
                            <option value="expired">Expired</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                {/* Subscriptions Table */}
                <div className="bg-stone-900 border border-stone-800 rounded-xl overflow-hidden shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-stone-300">
                            <thead className="bg-stone-950 border-b border-stone-800 text-xs uppercase font-semibold text-stone-400 tracking-wider">
                                <tr>
                                    <th className="px-6 py-4">Cafe</th>
                                    <th className="px-6 py-4">Plan</th>
                                    <th className="px-6 py-4">Status</th>
                                    <th className="px-6 py-4">Provider</th>
                                    <th className="px-6 py-4">Period / Expiry</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-stone-800">
                                {subscriptions.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-8 text-center text-stone-500">
                                            No subscriptions found matching the criteria.
                                        </td>
                                    </tr>
                                ) : (
                                    subscriptions.map((sub) => (
                                        <tr key={sub.id} className="hover:bg-stone-800/50 transition">
                                            <td className="px-6 py-4 font-medium text-stone-100">
                                                {sub.cafe_name ? (
                                                    <a
                                                        href={`/admin/cafes/${sub.cafe_id}`}
                                                        className="text-amber-400 hover:text-amber-300 font-semibold"
                                                    >
                                                        {sub.cafe_name}
                                                    </a>
                                                ) : (
                                                    <span className="text-stone-500">Cafe #{sub.cafe_id}</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="font-medium text-stone-200">{sub.plan_name || 'N/A'}</div>
                                                {sub.plan_price !== undefined && (
                                                    <div className="text-xs text-stone-400">${sub.plan_price.toFixed(2)}/mo</div>
                                                )}
                                            </td>
                                            <td className="px-6 py-4">
                                                <span
                                                    className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wide ${
                                                        sub.status === 'active'
                                                            ? 'bg-emerald-950 text-emerald-400 border border-emerald-800'
                                                            : sub.status === 'trialing'
                                                            ? 'bg-blue-950 text-blue-400 border border-blue-800'
                                                            : sub.status === 'cancelled'
                                                            ? 'bg-rose-950 text-rose-400 border border-rose-800'
                                                            : 'bg-stone-800 text-stone-400 border border-stone-700'
                                                    }`}
                                                >
                                                    {sub.status}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-stone-400 text-xs font-mono">
                                                {sub.provider ? (
                                                    <div>
                                                        <span className="uppercase text-stone-300 font-sans font-bold">{sub.provider}</span>
                                                        {sub.provider_subscription_id && (
                                                            <div className="truncate max-w-[120px]">{sub.provider_subscription_id}</div>
                                                        )}
                                                    </div>
                                                ) : (
                                                    'Manual / Admin'
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-xs text-stone-400">
                                                <div>Starts: {sub.starts_at ? new Date(sub.starts_at).toLocaleDateString() : 'N/A'}</div>
                                                <div>Ends: {sub.ends_at ? new Date(sub.ends_at).toLocaleDateString() : 'N/A'}</div>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                {sub.status !== 'cancelled' && (
                                                    <button
                                                        onClick={() => handleCancel(sub.id)}
                                                        className="text-xs text-rose-400 hover:text-rose-300 font-medium px-2.5 py-1 bg-rose-950/50 border border-rose-900 rounded-md transition"
                                                    >
                                                        Cancel
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
