import React, { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, useForm } from '@inertiajs/react';

interface Feature {
    id: number;
    feature_key: string;
    value: string;
}

interface Plan {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price: number;
    billing_interval: string;
    status: string;
    features_count: number;
    subscriptions_count: number;
    features: Feature[];
    created_at: string;
}

interface Props {
    plans: Plan[];
}

export default function Plans({ plans }: Props) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingPlan, setEditingPlan] = useState<Plan | null>(null);
    const [managingFeaturesPlan, setManagingFeaturesPlan] = useState<Plan | null>(null);

    // Form for Plan Creation / Editing
    const planForm = useForm({
        name: '',
        slug: '',
        description: '',
        price: '',
        billing_interval: 'monthly',
        status: 'active',
    });

    // Form for Adding Plan Feature
    const featureForm = useForm({
        feature_key: 'staff_limit',
        value: '',
    });

    const openCreateModal = () => {
        planForm.reset();
        setEditingPlan(null);
        setShowCreateModal(true);
    };

    const openEditModal = (plan: Plan) => {
        setEditingPlan(plan);
        planForm.setData({
            name: plan.name,
            slug: plan.slug,
            description: plan.description || '',
            price: plan.price.toString(),
            billing_interval: plan.billing_interval,
            status: plan.status,
        });
        setShowCreateModal(true);
    };

    const handlePlanSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingPlan) {
            planForm.put(`/admin/plans/${editingPlan.id}`, {
                onSuccess: () => {
                    setShowCreateModal(false);
                    planForm.reset();
                },
            });
        } else {
            planForm.post('/admin/plans', {
                onSuccess: () => {
                    setShowCreateModal(false);
                    planForm.reset();
                },
            });
        }
    };

    const handleAddFeature = (e: React.FormEvent) => {
        e.preventDefault();
        if (!managingFeaturesPlan) return;
        featureForm.post(`/admin/plans/${managingFeaturesPlan.id}/features`, {
            onSuccess: () => {
                featureForm.reset();
            },
        });
    };

    const handleDeleteFeature = (planId: number, featureId: number) => {
        if (confirm('Are you sure you want to remove this limit/feature from the plan?')) {
            router.delete(`/admin/plans/${planId}/features/${featureId}`);
        }
    };

    return (
        <AdminLayout title="SaaS Plan Management">
            <Head title="SaaS Plan Management" />

            <div className="space-y-6">
                {/* Top Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 className="text-lg font-bold text-white tracking-tight">Subscription Plans</h2>
                        <p className="text-xs text-stone-400">Configure SaaS tier pricing and resource capacity limits.</p>
                    </div>
                    <button
                        onClick={openCreateModal}
                        className="inline-flex items-center justify-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-stone-950 text-xs font-bold rounded-lg transition-colors shadow-sm"
                    >
                        <span>+</span> Create New Plan
                    </button>
                </div>

                {/* Plans Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {plans.map((plan) => (
                        <div
                            key={plan.id}
                            className="bg-stone-900 border border-stone-800 rounded-xl p-6 flex flex-col justify-between shadow-sm hover:border-stone-700 transition-colors"
                        >
                            <div>
                                <div className="flex items-start justify-between">
                                    <div>
                                        <span className={`inline-block text-[10px] uppercase font-bold px-2 py-0.5 rounded border mb-2 ${
                                            plan.status === 'active'
                                                ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                                : 'bg-stone-800 text-stone-400 border-stone-700'
                                        }`}>
                                            {plan.status}
                                        </span>
                                        <h3 className="text-xl font-bold text-white tracking-tight">{plan.name}</h3>
                                        <p className="text-xs text-stone-400 mt-1">{plan.description || 'No description provided.'}</p>
                                    </div>
                                </div>

                                <div className="mt-4 pt-4 border-t border-stone-800 flex items-baseline gap-1">
                                    <span className="text-2xl font-extrabold text-white">${plan.price.toFixed(2)}</span>
                                    <span className="text-xs text-stone-400">/ {plan.billing_interval}</span>
                                </div>

                                {/* Features List */}
                                <div className="mt-6 space-y-2">
                                    <div className="flex items-center justify-between text-xs font-bold text-stone-400 uppercase tracking-wider mb-3">
                                        <span>Feature / Capacity Limits</span>
                                        <button
                                            onClick={() => setManagingFeaturesPlan(plan)}
                                            className="text-amber-400 hover:text-amber-300 font-semibold"
                                        >
                                            + Manage Limits
                                        </button>
                                    </div>

                                    {plan.features.length === 0 ? (
                                        <p className="text-xs text-stone-500 italic">No feature limits configured.</p>
                                    ) : (
                                        plan.features.map((feature) => (
                                            <div
                                                key={feature.id}
                                                className="flex items-center justify-between text-xs bg-stone-950/60 px-3 py-2 rounded border border-stone-800/80"
                                            >
                                                <span className="font-mono text-stone-300">{feature.feature_key}</span>
                                                <span className="font-bold text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/20">
                                                    {feature.value}
                                                </span>
                                            </div>
                                        ))
                                    )}
                                </div>
                            </div>

                            {/* Plan Card Actions */}
                            <div className="mt-6 pt-4 border-t border-stone-800 flex items-center justify-between">
                                <span className="text-[11px] text-stone-400">
                                    Subscribers: <strong className="text-white">{plan.subscriptions_count}</strong>
                                </span>
                                <button
                                    onClick={() => openEditModal(plan)}
                                    className="text-xs text-stone-300 hover:text-white font-semibold px-3 py-1.5 rounded bg-stone-800 hover:bg-stone-700 transition-colors"
                                >
                                    Edit Plan
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Create/Edit Plan Modal */}
            {showCreateModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4">
                    <div className="bg-stone-900 border border-stone-800 rounded-xl max-w-md w-full p-6 shadow-xl space-y-4">
                        <div className="flex items-center justify-between border-b border-stone-800 pb-3">
                            <h3 className="text-base font-bold text-white">
                                {editingPlan ? 'Edit SaaS Plan' : 'Create SaaS Plan'}
                            </h3>
                            <button
                                onClick={() => setShowCreateModal(false)}
                                className="text-stone-400 hover:text-white text-lg font-bold"
                            >
                                ×
                            </button>
                        </div>

                        <form onSubmit={handlePlanSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-stone-300 mb-1">Plan Name</label>
                                <input
                                    type="text"
                                    required
                                    value={planForm.data.name}
                                    onChange={(e) => {
                                        const name = e.target.value;
                                        planForm.setData({
                                            ...planForm.data,
                                            name,
                                            slug: name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)+/g, ''),
                                        });
                                    }}
                                    className="w-full bg-stone-950 border border-stone-800 rounded-lg px-3 py-2 text-xs text-white focus:border-amber-500 focus:outline-none"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-stone-300 mb-1">Slug</label>
                                <input
                                    type="text"
                                    required
                                    value={planForm.data.slug}
                                    onChange={(e) => planForm.setData('slug', e.target.value)}
                                    className="w-full bg-stone-950 border border-stone-800 rounded-lg px-3 py-2 text-xs text-stone-300 font-mono focus:border-amber-500 focus:outline-none"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-stone-300 mb-1">Price ($)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    required
                                    value={planForm.data.price}
                                    onChange={(e) => planForm.setData('price', e.target.value)}
                                    className="w-full bg-stone-950 border border-stone-800 rounded-lg px-3 py-2 text-xs text-white focus:border-amber-500 focus:outline-none"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-stone-300 mb-1">Billing Interval</label>
                                <select
                                    value={planForm.data.billing_interval}
                                    onChange={(e) => planForm.setData('billing_interval', e.target.value)}
                                    className="w-full bg-stone-950 border border-stone-800 rounded-lg px-3 py-2 text-xs text-white focus:border-amber-500 focus:outline-none"
                                >
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-stone-300 mb-1">Status</label>
                                <select
                                    value={planForm.data.status}
                                    onChange={(e) => planForm.setData('status', e.target.value)}
                                    className="w-full bg-stone-950 border border-stone-800 rounded-lg px-3 py-2 text-xs text-white focus:border-amber-500 focus:outline-none"
                                >
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-stone-300 mb-1">Description</label>
                                <textarea
                                    rows={3}
                                    value={planForm.data.description}
                                    onChange={(e) => planForm.setData('description', e.target.value)}
                                    className="w-full bg-stone-950 border border-stone-800 rounded-lg px-3 py-2 text-xs text-white focus:border-amber-500 focus:outline-none"
                                />
                            </div>

                            <div className="flex justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setShowCreateModal(false)}
                                    className="px-4 py-2 bg-stone-800 hover:bg-stone-700 text-stone-300 text-xs font-semibold rounded-lg transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={planForm.processing}
                                    className="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-stone-950 text-xs font-bold rounded-lg transition-colors shadow-sm disabled:opacity-50"
                                >
                                    {planForm.processing ? 'Saving...' : 'Save Plan'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Manage Limits Modal */}
            {managingFeaturesPlan && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4">
                    <div className="bg-stone-900 border border-stone-800 rounded-xl max-w-lg w-full p-6 shadow-xl space-y-6">
                        <div className="flex items-center justify-between border-b border-stone-800 pb-3">
                            <div>
                                <h3 className="text-base font-bold text-white">Manage Plan Limits</h3>
                                <p className="text-xs text-stone-400">{managingFeaturesPlan.name}</p>
                            </div>
                            <button
                                onClick={() => setManagingFeaturesPlan(null)}
                                className="text-stone-400 hover:text-white text-lg font-bold"
                            >
                                ×
                            </button>
                        </div>

                        {/* Existing Feature List */}
                        <div className="space-y-2">
                            <h4 className="text-xs font-bold text-stone-400 uppercase tracking-wider">Current Feature Limits</h4>
                            {managingFeaturesPlan.features.length === 0 ? (
                                <p className="text-xs text-stone-500 italic">No features configured.</p>
                            ) : (
                                managingFeaturesPlan.features.map((f) => (
                                    <div key={f.id} className="flex items-center justify-between bg-stone-950 px-3 py-2 rounded border border-stone-800">
                                        <div>
                                            <span className="text-xs font-mono font-bold text-white">{f.feature_key}</span>
                                            <span className="ml-3 text-xs text-amber-400 font-bold">{f.value}</span>
                                        </div>
                                        <button
                                            onClick={() => handleDeleteFeature(managingFeaturesPlan.id, f.id)}
                                            className="text-xs text-rose-400 hover:text-rose-300 font-medium"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                ))
                            )}
                        </div>

                        {/* Add Feature Form */}
                        <form onSubmit={handleAddFeature} className="pt-4 border-t border-stone-800 space-y-3">
                            <h4 className="text-xs font-bold text-stone-300">Add New Feature Limit</h4>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-[11px] font-semibold text-stone-400 mb-1">Feature Key</label>
                                    <select
                                        value={featureForm.data.feature_key}
                                        onChange={(e) => featureForm.setData('feature_key', e.target.value)}
                                        className="w-full bg-stone-950 border border-stone-800 rounded-lg px-2.5 py-1.5 text-xs text-white focus:border-amber-500 focus:outline-none"
                                    >
                                        <option value="staff_limit">staff_limit</option>
                                        <option value="table_limit">table_limit</option>
                                        <option value="branch_limit">branch_limit</option>
                                        <option value="menu_item_limit">menu_item_limit</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-[11px] font-semibold text-stone-400 mb-1">Value (e.g. 5 or unlimited)</label>
                                    <input
                                        type="text"
                                        required
                                        placeholder="e.g. 5 or unlimited"
                                        value={featureForm.data.value}
                                        onChange={(e) => featureForm.setData('value', e.target.value)}
                                        className="w-full bg-stone-950 border border-stone-800 rounded-lg px-2.5 py-1.5 text-xs text-white focus:border-amber-500 focus:outline-none"
                                    />
                                </div>
                            </div>
                            <div className="flex justify-end pt-2">
                                <button
                                    type="submit"
                                    disabled={featureForm.processing}
                                    className="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-stone-950 text-xs font-bold rounded-lg transition-colors shadow-sm disabled:opacity-50"
                                >
                                    {featureForm.processing ? 'Adding...' : 'Add Limit'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
