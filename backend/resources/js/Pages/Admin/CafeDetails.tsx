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
    notes: string | null;
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
    plans?: PlanItem[];
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

export default function AdminCafeDetails({ cafe, owner, subscription, plans = [], usage, metrics, audit_logs }: CafeDetailsProps) {
    const [confirmingStatus, setConfirmingStatus] = useState<string | null>(null);

    // Notes State
    const [notesText, setNotesText] = useState(cafe.notes || '');
    const [savingNotes, setSavingNotes] = useState(false);

    // Subscription Override Modals State
    const [showExtendModal, setShowExtendModal] = useState(false);
    const [extendEndsAt, setExtendEndsAt] = useState('');
    const [extendReason, setExtendReason] = useState('');

    const [showChangePlanModal, setShowChangePlanModal] = useState(false);
    const [targetPlanId, setTargetPlanId] = useState<string | number>(plans[0]?.id || '');
    const [changePlanReason, setChangePlanReason] = useState('');

    const [showReactivateModal, setShowReactivateModal] = useState(false);
    const [reactivateEndsAt, setReactivateEndsAt] = useState('');
    const [reactivateReason, setReactivateReason] = useState('');

    const handleStatusUpdate = (targetStatus: string) => {
        router.patch(`/admin/cafes/${cafe.id}/status`, {
            status: targetStatus,
        }, {
            onSuccess: () => setConfirmingStatus(null),
        });
    };

    const handleSaveNotes = (e: React.FormEvent) => {
        e.preventDefault();
        setSavingNotes(true);
        router.patch(`/admin/cafes/${cafe.id}/notes`, {
            notes: notesText,
        }, {
            onFinish: () => setSavingNotes(false),
        });
    };

    const handleExtendSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(`/admin/cafes/${cafe.id}/subscription/extend`, {
            new_ends_at: extendEndsAt,
            reason: extendReason,
        }, {
            onSuccess: () => {
                setShowExtendModal(false);
                setExtendEndsAt('');
                setExtendReason('');
            },
        });
    };

    const handleChangePlanSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(`/admin/cafes/${cafe.id}/subscription/change-plan`, {
            plan_id: targetPlanId,
            reason: changePlanReason,
        }, {
            onSuccess: () => {
                setShowChangePlanModal(false);
                setChangePlanReason('');
            },
        });
    };

    const handleReactivateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(`/admin/cafes/${cafe.id}/subscription/reactivate`, {
            new_ends_at: reactivateEndsAt,
            reason: reactivateReason,
        }, {
            onSuccess: () => {
                setShowReactivateModal(false);
                setReactivateEndsAt('');
                setReactivateReason('');
            },
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

            {/* Expiry Recommendation Banner */}
            {subscription && subscription.expiry_recommendation === 'expired' && (
                <div className="mb-8 p-4 bg-rose-950/80 border border-rose-700/80 rounded-xl text-rose-200 flex items-start justify-between gap-4 shadow-sm">
                    <div className="flex gap-3">
                        <span className="text-2xl">⚠️</span>
                        <div>
                            <h4 className="font-bold text-sm text-white">Subscription Expired</h4>
                            <p className="text-xs text-rose-300 mt-0.5">
                                This cafe's subscription expired on {subscription.ends_at ? new Date(subscription.ends_at).toLocaleDateString() : 'N/A'}.
                                Use the Subscription Override panel below to extend, change plan, or reactivate.
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
                </div>
            </div>

            {/* Grid 1: Details, Owner, & Subscription Overrides */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                {/* Business Profile */}
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

                {/* Subscription Details & Override Actions */}
                <div className="bg-stone-800/80 p-6 rounded-xl border border-stone-700/60 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 className="text-sm font-bold text-white uppercase tracking-wider mb-4 border-b border-stone-700/60 pb-2 flex items-center justify-between">
                            <span>Active Subscription</span>
                            {subscription && <StatusBadge status={subscription.status} />}
                        </h3>
                        {subscription ? (
                            <dl className="space-y-3 text-xs">
                                <div>
                                    <dt className="text-stone-400">Plan</dt>
                                    <dd className="font-bold text-amber-400 mt-0.5 text-sm">{subscription.plan?.name || 'Custom Plan'}</dd>
                                </div>
                                <div>
                                    <dt className="text-stone-400">Expiry Date</dt>
                                    <dd className="font-semibold text-stone-200 mt-0.5">
                                        {subscription.ends_at ? new Date(subscription.ends_at).toLocaleDateString() : 'Continuous'}
                                    </dd>
                                </div>
                            </dl>
                        ) : (
                            <p className="text-xs text-stone-500 italic mb-4">No active subscription found.</p>
                        )}
                    </div>

                    {/* Admin Subscription Override Controls */}
                    <div className="pt-4 mt-4 border-t border-stone-700/60 space-y-2">
                        <p className="text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-2">Admin Overrides:</p>
                        <div className="grid grid-cols-2 gap-2">
                            <button
                                onClick={() => setShowExtendModal(true)}
                                className="px-2.5 py-1.5 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 text-xs font-bold rounded transition-colors text-center"
                            >
                                Extend Expiry
                            </button>
                            <button
                                onClick={() => setShowChangePlanModal(true)}
                                className="px-2.5 py-1.5 bg-stone-700 hover:bg-stone-600 text-stone-200 border border-stone-600 text-xs font-bold rounded transition-colors text-center"
                            >
                                Change Plan
                            </button>
                        </div>
                        {subscription && subscription.status !== 'active' && (
                            <button
                                onClick={() => setShowReactivateModal(true)}
                                className="w-full mt-2 px-2.5 py-1.5 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-xs font-bold rounded transition-colors text-center"
                            >
                                Reactivate Subscription
                            </button>
                        )}
                    </div>
                </div>
            </div>

            {/* Grid 2: Internal Admin Notes & Resource Usage */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">
                {/* Internal Super Admin Notes */}
                <div className="bg-stone-800/80 p-6 rounded-xl border border-stone-700/60 shadow-sm flex flex-col justify-between">
                    <form onSubmit={handleSaveNotes} className="space-y-3">
                        <div className="flex items-center justify-between border-b border-stone-700/60 pb-2">
                            <h3 className="text-sm font-bold text-white uppercase tracking-wider">
                                Internal Admin Notes <span className="text-[10px] text-amber-400 font-semibold normal-case">(Super Admin Only)</span>
                            </h3>
                        </div>
                        <p className="text-[11px] text-stone-400">
                            Private SaaS CRM notes regarding this cafe. Never visible to tenant users.
                        </p>
                        <textarea
                            rows={5}
                            value={notesText}
                            onChange={(e) => setNotesText(e.target.value)}
                            placeholder="Add administrative notes, support history, or billing adjustments..."
                            className="w-full bg-stone-900 border border-stone-700 rounded-lg p-3 text-xs text-white placeholder-stone-500 focus:border-amber-500 focus:outline-none"
                        />
                        <div className="flex justify-end">
                            <button
                                type="submit"
                                disabled={savingNotes}
                                className="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-stone-950 text-xs font-bold rounded-lg transition-colors shadow-sm disabled:opacity-50"
                            >
                                {savingNotes ? 'Saving...' : 'Save Internal Notes'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Resource Usage vs Plan Limits */}
                <div className="bg-stone-800/80 p-6 rounded-xl border border-stone-700/60 shadow-sm">
                    <h3 className="text-sm font-bold text-white uppercase tracking-wider mb-5 border-b border-stone-700/60 pb-2">
                        Plan Usage & Capacity Limits
                    </h3>
                    {renderProgressBar('Branches Allocated', usage.branches.current, usage.branches.limit)}
                    {renderProgressBar('Staff Members', usage.staff.current, usage.staff.limit)}
                    {renderProgressBar('Menu Items Catalog', usage.menu.current, usage.menu.limit)}
                </div>
            </div>

            {/* Lifecycle Audit Log Timeline */}
            <div className="bg-stone-800/80 rounded-xl border border-stone-700/60 shadow-sm overflow-hidden mb-8">
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

            {/* Modals for Override Actions */}

            {/* Extend Subscription Modal */}
            {showExtendModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
                    <div className="bg-stone-800 border border-stone-700 rounded-xl p-6 max-w-md w-full shadow-2xl space-y-4">
                        <h3 className="text-base font-bold text-white">Extend Subscription Expiry</h3>
                        <form onSubmit={handleExtendSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs text-stone-300 font-semibold mb-1">New Expiry Date</label>
                                <input
                                    type="date"
                                    required
                                    value={extendEndsAt}
                                    onChange={(e) => setExtendEndsAt(e.target.value)}
                                    className="w-full bg-stone-900 border border-stone-700 rounded-lg p-2 text-xs text-white focus:border-amber-500 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="block text-xs text-stone-300 font-semibold mb-1">Reason for Extension (Required)</label>
                                <textarea
                                    required
                                    rows={3}
                                    placeholder="Enter administrative reason for extension..."
                                    value={extendReason}
                                    onChange={(e) => setExtendReason(e.target.value)}
                                    className="w-full bg-stone-900 border border-stone-700 rounded-lg p-2 text-xs text-white focus:border-amber-500 focus:outline-none"
                                />
                            </div>
                            <div className="flex justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setShowExtendModal(false)}
                                    className="px-4 py-2 bg-stone-700 hover:bg-stone-600 text-stone-300 text-xs font-semibold rounded-lg"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-stone-950 text-xs font-bold rounded-lg"
                                >
                                    Extend Expiry
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Change Plan Modal */}
            {showChangePlanModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
                    <div className="bg-stone-800 border border-stone-700 rounded-xl p-6 max-w-md w-full shadow-2xl space-y-4">
                        <h3 className="text-base font-bold text-white">Reassign Subscription Plan</h3>
                        <form onSubmit={handleChangePlanSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs text-stone-300 font-semibold mb-1">Select New SaaS Plan</label>
                                <select
                                    value={targetPlanId}
                                    onChange={(e) => setTargetPlanId(e.target.value)}
                                    className="w-full bg-stone-900 border border-stone-700 rounded-lg p-2 text-xs text-white focus:border-amber-500 focus:outline-none"
                                >
                                    {plans.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name} (${p.price}/{p.billing_interval})
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs text-stone-300 font-semibold mb-1">Reason for Plan Change (Required)</label>
                                <textarea
                                    required
                                    rows={3}
                                    placeholder="Enter administrative reason for plan override..."
                                    value={changePlanReason}
                                    onChange={(e) => setChangePlanReason(e.target.value)}
                                    className="w-full bg-stone-900 border border-stone-700 rounded-lg p-2 text-xs text-white focus:border-amber-500 focus:outline-none"
                                />
                            </div>
                            <div className="flex justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setShowChangePlanModal(false)}
                                    className="px-4 py-2 bg-stone-700 hover:bg-stone-600 text-stone-300 text-xs font-semibold rounded-lg"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-stone-950 text-xs font-bold rounded-lg"
                                >
                                    Update Plan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Reactivate Subscription Modal */}
            {showReactivateModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
                    <div className="bg-stone-800 border border-stone-700 rounded-xl p-6 max-w-md w-full shadow-2xl space-y-4">
                        <h3 className="text-base font-bold text-white">Reactivate Subscription</h3>
                        <form onSubmit={handleReactivateSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs text-stone-300 font-semibold mb-1">New Expiry Date (Required)</label>
                                <input
                                    type="date"
                                    required
                                    value={reactivateEndsAt}
                                    onChange={(e) => setReactivateEndsAt(e.target.value)}
                                    className="w-full bg-stone-900 border border-stone-700 rounded-lg p-2 text-xs text-white focus:border-amber-500 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="block text-xs text-stone-300 font-semibold mb-1">Reason for Reactivation (Required)</label>
                                <textarea
                                    required
                                    rows={3}
                                    placeholder="Enter administrative reason for reactivation..."
                                    value={reactivateReason}
                                    onChange={(e) => setReactivateReason(e.target.value)}
                                    className="w-full bg-stone-900 border border-stone-700 rounded-lg p-2 text-xs text-white focus:border-amber-500 focus:outline-none"
                                />
                            </div>
                            <div className="flex justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setShowReactivateModal(false)}
                                    className="px-4 py-2 bg-stone-700 hover:bg-stone-600 text-stone-300 text-xs font-semibold rounded-lg"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg"
                                >
                                    Reactivate Subscription
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
