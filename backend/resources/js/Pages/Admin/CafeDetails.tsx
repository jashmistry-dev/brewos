import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import StatusBadge from '../../Components/StatusBadge';

export interface BranchItem {
    id: number;
    name: string;
    slug: string;
    status: string;
}

export interface PlanItem {
    id: number;
    name: string;
    slug: string;
    price: string | number;
    billing_interval: string;
}

export interface SubscriptionItem {
    id: number;
    status: string;
    starts_at: string | null;
    ends_at: string | null;
    trial_ends_at: string | null;
    provider: string | null;
    provider_subscription_id: string | null;
    expiry_recommendation: 'active' | 'expiring_soon' | 'expired';
    plan: PlanItem | null;
}

export interface OwnerDetails {
    id: number;
    name: string;
    email: string;
    phone: string | null;
}

export interface CafeDetailsData {
    id: number;
    name: string;
    slug: string;
    email: string;
    phone: string | null;
    status: string;
    timezone: string;
    currency: string;
    branches_count: number;
    users_count: number;
    customers_count: number;
    orders_count: number;
    menu_items_count: number;
    branches: BranchItem[];
    created_at: string;
    updated_at: string;
}

export interface UsageMetric {
    current: number;
    limit: number | 'unlimited';
}

export interface AuditLogEntry {
    id: number;
    action: string;
    entity_type: string;
    entity_id: number | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    created_at: string;
    user: {
        id: number;
        name: string;
        email: string;
    } | null;
}

export interface CafeDetailsProps {
    cafe: CafeDetailsData;
    owner: OwnerDetails | null;
    subscription: SubscriptionItem | null;
    usage: {
        branches: UsageMetric;
        staff: UsageMetric;
        menu: UsageMetric;
    };
    metrics: {
        customers_count: number;
        orders_count: number;
        total_revenue: number;
    };
    audit_logs: AuditLogEntry[];
}

export default function AdminCafeDetails({ cafe, owner, subscription, usage, metrics, audit_logs }: CafeDetailsProps) {
    const [confirmingStatus, setConfirmingStatus] = useState<string | null>(null);

    const handleStatusUpdate = (targetStatus: string) => {
        router.patch(`/admin/cafes/${cafe.id}/status`, {
            status: targetStatus,
        }, {
            onSuccess: () => setConfirmingStatus(null),
        });
    };

    const formatCurrency = (val: number) => {
        return new Intl.NumberFormat('en-IN', { style: 'currency', currency: cafe.currency || 'INR', maximumFractionDigits: 0 }).format(val);
    };

    const renderProgressBar = (label: string, current: number, limit: number | 'unlimited') => {
        const isUnlimited = limit === 'unlimited';
        const pct = isUnlimited ? 20 : Math.min(100, Math.round((current / (limit as number)) * 100));

        return (
            <div className="mb-4">
                <div className="flex justify-between items-center text-xs mb-1.5">
                    <span className="font-semibold text-stone-300">{label}</span>
                    <span className="font-mono text-stone-400">
                        {current} / {isUnlimited ? '∞ (Unlimited)' : limit}
                    </span>
                </div>
                <div className="w-full h-2 bg-stone-900 rounded-full overflow-hidden border border-stone-700">
                    <div
                        className={`h-full transition-all duration-500 ${
                            pct >= 90 ? 'bg-rose-500' : pct >= 75 ? 'bg-amber-500' : 'bg-emerald-500'
                        }`}
                        style={{ width: `${pct}%` }}
                    ></div>
                </div>
            </div>
        );
    };

    return (
        <AdminLayout title={`Cafe Profile: ${cafe.name}`}>
            <Head title={`${cafe.name} - Super Admin Details`} />

            {/* Back Breadcrumb */}
            <div className="mb-6">
                <Link href="/admin/cafes" className="text-xs font-semibold text-amber-400 hover:underline inline-flex items-center gap-1">
                    ← Back to All Cafes
                </Link>
            </div>

            {/* Expiry Recommendation Banner (Non-disruptive recommendation) */}
            {subscription && subscription.expiry_recommendation === 'expired' && (
                <div className="mb-8 p-4 bg-rose-950/80 border border-rose-700/80 rounded-xl text-rose-200 flex items-start justify-between gap-4 shadow-sm">
                    <div className="flex gap-3">
                        <span className="text-2xl">⚠️</span>
                        <div>
                            <h4 className="font-bold text-sm text-white">Subscription Expired</h4>
                            <p className="text-xs text-rose-300 mt-0.5">
                                This cafe's subscription expired on {subscription.ends_at ? new Date(subscription.ends_at).toLocaleDateString() : 'N/A'}.
                                Recommendation: Review account standing and deactivate or suspend this cafe if necessary.
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {subscription && subscription.expiry_recommendation === 'expiring_soon' && (
                <div className="mb-8 p-4 bg-amber-950/80 border border-amber-700/80 rounded-xl text-amber-200 flex items-start justify-between gap-4 shadow-sm">
                    <div className="flex gap-3">
                        <span className="text-2xl">⏳</span>
                        <div>
                            <h4 className="font-bold text-sm text-white">Subscription Expiring Soon</h4>
                            <p className="text-xs text-amber-300 mt-0.5">
                                Subscription expires within 7 days ({subscription.ends_at ? new Date(subscription.ends_at).toLocaleDateString() : 'N/A'}).
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {/* Main Header / Status Control */}
            <div className="bg-stone-800/90 p-6 rounded-xl border border-stone-700/60 shadow-sm mb-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                <div>
                    <div className="flex items-center gap-3">
                        <h2 className="text-2xl font-extrabold text-white tracking-tight">{cafe.name}</h2>
                        <StatusBadge status={cafe.status} />
                    </div>
                    <p className="text-xs font-mono text-amber-400 mt-1">Slug: {cafe.slug} | Registered: {new Date(cafe.created_at).toLocaleDateString()}</p>
                </div>

                {/* Status Lifecycle Actions */}
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-xs text-stone-400 font-semibold uppercase tracking-wider mr-2">Lifecycle Control:</span>
                    {cafe.status !== 'active' && (
                        <button
                            onClick={() => setConfirmingStatus('active')}
                            className="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-lg transition-colors shadow-sm"
                        >
                            Activate Cafe
                        </button>
                    )}
                    {cafe.status === 'active' && (
                        <button
                            onClick={() => setConfirmingStatus('inactive')}
                            className="px-3.5 py-1.5 bg-stone-700 hover:bg-stone-600 text-stone-200 font-bold text-xs rounded-lg transition-colors"
                        >
                            Deactivate
                        </button>
                    )}
                    {cafe.status !== 'suspended' && (
                        <button
                            onClick={() => setConfirmingStatus('suspended')}
                            className="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-lg transition-colors shadow-sm"
                        >
                            Suspend Cafe
                        </button>
                    )}
                    {cafe.status === 'suspended' && (
                        <button
                            onClick={() => setConfirmingStatus('active')}
                            className="px-3.5 py-1.5 bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs rounded-lg transition-colors shadow-sm"
                        >
                            Reactivate
                        </button>
                    )}
                </div>
            </div>

            {/* Confirmation Modal */}
            {confirmingStatus && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
                    <div className="bg-stone-800 border border-stone-700 rounded-xl p-6 max-w-md w-full shadow-2xl">
                        <h3 className="text-lg font-bold text-white mb-2 capitalize">
                            Confirm Cafe Status Change: {confirmingStatus}
                        </h3>
                        <p className="text-xs text-stone-300 mb-6">
                            Are you sure you want to change status of <strong>{cafe.name}</strong> to <span className="uppercase font-mono text-amber-400">{confirmingStatus}</span>? This action will be recorded in the audit log.
                        </p>
                        <div className="flex justify-end gap-3">
                            <button
                                onClick={() => setConfirmingStatus(null)}
                                className="px-4 py-2 bg-stone-700 hover:bg-stone-600 text-stone-300 text-xs font-semibold rounded-lg"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={() => handleStatusUpdate(confirmingStatus)}
                                className="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-stone-950 text-xs font-bold rounded-lg"
                            >
                                Confirm Change
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Grid 1: Details & Owner */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                {/* Cafe Profile */}
                <div className="bg-stone-800/80 p-6 rounded-xl border border-stone-700/60 shadow-sm">
                    <h3 className="text-sm font-bold text-white uppercase tracking-wider mb-4 border-b border-stone-700/60 pb-2">
                        Business Profile
                    </h3>
                    <dl className="space-y-3 text-xs">
                        <div>
                            <dt className="text-stone-400">Business Email</dt>
                            <dd className="font-semibold text-stone-200 mt-0.5">{cafe.email || 'N/A'}</dd>
                        </div>
                        <div>
                            <dt className="text-stone-400">Phone</dt>
                            <dd className="font-semibold text-stone-200 mt-0.5">{cafe.phone || 'N/A'}</dd>
                        </div>
                        <div>
                            <dt className="text-stone-400">Timezone / Currency</dt>
                            <dd className="font-semibold text-stone-200 mt-0.5">{cafe.timezone} ({cafe.currency})</dd>
                        </div>
                    </dl>
                </div>

                {/* Owner Information */}
                <div className="bg-stone-800/80 p-6 rounded-xl border border-stone-700/60 shadow-sm">
                    <h3 className="text-sm font-bold text-white uppercase tracking-wider mb-4 border-b border-stone-700/60 pb-2">
                        Account Owner
                    </h3>
                    {owner ? (
                        <dl className="space-y-3 text-xs">
                            <div>
                                <dt className="text-stone-400">Full Name</dt>
                                <dd className="font-semibold text-stone-200 mt-0.5">{owner.name}</dd>
                            </div>
                            <div>
                                <dt className="text-stone-400">Email Address</dt>
                                <dd className="font-semibold text-amber-400 mt-0.5">{owner.email}</dd>
                            </div>
                            <div>
                                <dt className="text-stone-400">Contact Phone</dt>
                                <dd className="font-semibold text-stone-200 mt-0.5">{owner.phone || 'N/A'}</dd>
                            </div>
                        </dl>
                    ) : (
                        <p className="text-xs text-stone-500 italic">No owner user record associated with this cafe.</p>
                    )}
                </div>

                {/* Subscription Details */}
                <div className="bg-stone-800/80 p-6 rounded-xl border border-stone-700/60 shadow-sm">
                    <h3 className="text-sm font-bold text-white uppercase tracking-wider mb-4 border-b border-stone-700/60 pb-2">
                        Active Subscription
                    </h3>
                    {subscription ? (
                        <dl className="space-y-3 text-xs">
                            <div>
                                <dt className="text-stone-400">Plan</dt>
                                <dd className="font-bold text-amber-400 mt-0.5 text-sm">{subscription.plan?.name || 'Custom Plan'}</dd>
                            </div>
                            <div>
                                <dt className="text-stone-400">Subscription Status</dt>
                                <dd className="mt-1"><StatusBadge status={subscription.status} /></dd>
                            </div>
                            <div>
                                <dt className="text-stone-400">Billing Term</dt>
                                <dd className="font-semibold text-stone-200 mt-0.5">
                                    {subscription.starts_at ? new Date(subscription.starts_at).toLocaleDateString() : 'N/A'} — {subscription.ends_at ? new Date(subscription.ends_at).toLocaleDateString() : 'Continuous'}
                                </dd>
                            </div>
                        </dl>
                    ) : (
                        <p className="text-xs text-stone-500 italic">No subscription found for this cafe.</p>
                    )}
                </div>
            </div>

            {/* Grid 2: Resource Usage & Business Metrics */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">
                {/* Resource Usage vs Plan Limits */}
                <div className="bg-stone-800/80 p-6 rounded-xl border border-stone-700/60 shadow-sm">
                    <h3 className="text-sm font-bold text-white uppercase tracking-wider mb-5 border-b border-stone-700/60 pb-2">
                        Plan Usage & Capacity Limits
                    </h3>
                    {renderProgressBar('Branches Allocated', usage.branches.current, usage.branches.limit)}
                    {renderProgressBar('Staff Members', usage.staff.current, usage.staff.limit)}
                    {renderProgressBar('Menu Items Catalog', usage.menu.current, usage.menu.limit)}
                </div>

                {/* Business Metrics */}
                <div className="bg-stone-800/80 p-6 rounded-xl border border-stone-700/60 shadow-sm flex flex-col justify-between">
                    <h3 className="text-sm font-bold text-white uppercase tracking-wider mb-5 border-b border-stone-700/60 pb-2">
                        Business & Financial Metrics
                    </h3>
                    <div className="grid grid-cols-3 gap-4 text-center my-auto">
                        <div className="p-4 bg-stone-900/60 rounded-xl border border-stone-700/40">
                            <p className="text-xs text-stone-400 uppercase font-semibold">Customers</p>
                            <p className="text-2xl font-extrabold text-white mt-1">{metrics.customers_count}</p>
                        </div>
                        <div className="p-4 bg-stone-900/60 rounded-xl border border-stone-700/40">
                            <p className="text-xs text-stone-400 uppercase font-semibold">Orders</p>
                            <p className="text-2xl font-extrabold text-white mt-1">{metrics.orders_count}</p>
                        </div>
                        <div className="p-4 bg-stone-900/60 rounded-xl border border-stone-700/40">
                            <p className="text-xs text-stone-400 uppercase font-semibold">Total Revenue</p>
                            <p className="text-xl font-extrabold text-emerald-400 mt-1">{formatCurrency(metrics.total_revenue)}</p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Lifecycle Audit Log Timeline */}
            <div className="bg-stone-800/80 rounded-xl border border-stone-700/60 shadow-sm overflow-hidden">
                <div className="p-5 border-b border-stone-700/60">
                    <h3 className="text-sm font-bold text-white uppercase tracking-wider">
                        Lifecycle Audit History Log
                    </h3>
                </div>
                {audit_logs.length === 0 ? (
                    <div className="p-8 text-center text-stone-500 text-xs italic">
                        No audit log entries recorded for this cafe yet.
                    </div>
                ) : (
                    <div className="divide-y divide-stone-700/40">
                        {audit_logs.map((log) => (
                            <div key={log.id} className="p-4 hover:bg-stone-700/20 transition-colors flex items-center justify-between text-xs">
                                <div>
                                    <span className="font-mono font-bold text-amber-400">{log.action}</span>
                                    <span className="text-stone-400 ml-2">by {log.user?.name || 'System'} ({log.user?.email || 'N/A'})</span>
                                </div>
                                <span className="text-stone-500 font-mono">
                                    {new Date(log.created_at).toLocaleString()}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
