import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import Button from '@/Components/Button';

interface PlanFeature {
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
    features: PlanFeature[];
}

interface SubscriptionOverview {
    subscription: {
        id: number;
        status: string;
        starts_at: string | null;
        ends_at: string | null;
        trial_ends_at: string | null;
        provider: string | null;
        provider_subscription_id: string | null;
    } | null;
    plan: Plan | null;
    usage: {
        branches: { current: number; limit: number | string };
        staff: { current: number; limit: number | string };
        tables?: { current: number; limit: number | string };
        menu?: { current: number; limit: number | string };
        menu_items?: { current: number; limit: number | string };
    };
    is_active: boolean;
}

interface Props {
    overview: SubscriptionOverview;
    plans: Plan[];
}

export default function Subscription({ overview, plans }: Props) {
    const { cafe } = usePage<{ cafe?: { slug: string; name: string } }>().props;
    const cafeSlug = cafe?.slug || '';
    const [submittingPlanId, setSubmittingPlanId] = useState<number | null>(null);

    const handleSubscribe = (planId: number) => {
        setSubmittingPlanId(planId);
        router.post(
            `/cafes/${cafeSlug}/subscription/subscribe`,
            { plan_id: planId, provider: 'system' },
            {
                onFinish: () => setSubmittingPlanId(null),
                preserveScroll: true,
            }
        );
    };

    const handleCancel = () => {
        if (confirm('Are you sure you want to cancel your subscription?')) {
            router.post(`/cafes/${cafeSlug}/subscription/cancel`, {}, { preserveScroll: true });
        }
    };

    const sub = overview.subscription;
    const currentPlan = overview.plan;

    return (
        <AppLayout title="Subscription & Billing" cafeSlug={cafeSlug}>
            <Head title={`Subscription & Plans — ${cafe?.name || 'Workspace'}`} />

            <div className="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-8">
                {/* Header */}
                <div className="border-b border-stone-800 pb-5">
                    <h1 className="text-2xl font-bold text-stone-100 tracking-tight">Subscription & Billing</h1>
                    <p className="text-sm text-stone-400 mt-1">
                        Manage your cafe plan, review usage limits, and upgrade your subscription.
                    </p>
                </div>

                {/* Current Plan Overview Card */}
                <div className="bg-stone-900 border border-stone-800 rounded-2xl p-6 shadow-sm">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <div>
                            <div className="flex items-center gap-3 mb-2">
                                <h2 className="text-xl font-extrabold text-stone-100">
                                    {currentPlan ? currentPlan.name : 'No Active Plan'}
                                </h2>
                                {sub && (
                                    <span
                                        className={`px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider ${
                                            sub.status === 'active'
                                                ? 'bg-emerald-950 text-emerald-400 border border-emerald-800'
                                                : sub.status === 'trialing'
                                                ? 'bg-blue-950 text-blue-400 border border-blue-800'
                                                : sub.status === 'cancelled'
                                                ? 'bg-rose-950 text-rose-400 border border-rose-800'
                                                : 'bg-stone-800 text-stone-400'
                                        }`}
                                    >
                                        {sub.status === 'trialing' ? '14-Day Free Trial' : sub.status}
                                    </span>
                                )}
                            </div>

                            <p className="text-sm text-stone-400">
                                {currentPlan ? currentPlan.description || 'Full POS and workspace features' : 'Subscribe to a plan to start using BrewOS.'}
                            </p>

                            {sub && sub.trial_ends_at && sub.status === 'trialing' && (
                                <p className="text-xs text-amber-400 font-semibold mt-2">
                                    ⏳ Free trial ends on {new Date(sub.trial_ends_at).toLocaleDateString()}
                                </p>
                            )}

                            {sub && sub.ends_at && sub.status === 'active' && (
                                <p className="text-xs text-stone-400 mt-2">
                                    Renews on {new Date(sub.ends_at).toLocaleDateString()}
                                </p>
                            )}
                        </div>

                        {sub && sub.status !== 'cancelled' && (
                            <Button variant="danger" size="sm" onClick={handleCancel}>
                                Cancel Subscription
                            </Button>
                        )}
                    </div>

                    {/* Usage Progress Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 pt-6 border-t border-stone-800">
                        <div className="bg-stone-950 p-4 rounded-xl border border-stone-800">
                            <div className="text-xs text-stone-400 font-semibold uppercase">Branches</div>
                            <div className="text-lg font-bold text-stone-100 mt-1">
                                {overview.usage.branches.current} / {overview.usage.branches.limit}
                            </div>
                        </div>

                        <div className="bg-stone-950 p-4 rounded-xl border border-stone-800">
                            <div className="text-xs text-stone-400 font-semibold uppercase">Staff Members</div>
                            <div className="text-lg font-bold text-stone-100 mt-1">
                                {overview.usage.staff.current} / {overview.usage.staff.limit}
                            </div>
                        </div>

                        <div className="bg-stone-950 p-4 rounded-xl border border-stone-800">
                            <div className="text-xs text-stone-400 font-semibold uppercase">Menu Items</div>
                            <div className="text-lg font-bold text-stone-100 mt-1">
                                {(overview.usage.menu_items || overview.usage.menu)?.current || 0} / {(overview.usage.menu_items || overview.usage.menu)?.limit || 'unlimited'}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Available Plans Grid */}
                <div>
                    <h3 className="text-lg font-bold text-stone-100 mb-4">Available Subscription Plans</h3>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {plans.map((plan) => {
                            const isCurrent = currentPlan?.id === plan.id;

                            return (
                                <div
                                    key={plan.id}
                                    className={`bg-stone-900 border rounded-2xl p-6 flex flex-col justify-between transition ${
                                        isCurrent
                                            ? 'border-amber-500 ring-2 ring-amber-500/20 shadow-lg'
                                            : 'border-stone-800 hover:border-stone-700'
                                    }`}
                                >
                                    <div>
                                        <div className="flex justify-between items-start mb-2">
                                            <h4 className="text-lg font-bold text-stone-100">{plan.name}</h4>
                                            {isCurrent && (
                                                <span className="text-xs bg-amber-500 text-stone-950 font-bold px-2.5 py-0.5 rounded-full">
                                                    Current
                                                </span>
                                            )}
                                        </div>

                                        <div className="text-3xl font-extrabold text-stone-100 my-3">
                                            ${plan.price.toFixed(2)}
                                            <span className="text-sm font-normal text-stone-400">/{plan.billing_interval}</span>
                                        </div>

                                        <p className="text-xs text-stone-400 mb-4">{plan.description || 'Standard plan features'}</p>

                                        <div className="space-y-2 border-t border-stone-800 pt-4 text-xs text-stone-300">
                                            {plan.features.map((f) => (
                                                <div key={f.id} className="flex items-center gap-2">
                                                    <span className="text-amber-400 font-bold">✓</span>
                                                    <span>{f.feature_key.replace('_', ' ')}: <strong>{f.value}</strong></span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    <div className="mt-6 pt-4 border-t border-stone-800">
                                        <Button
                                            variant={isCurrent ? 'secondary' : 'primary'}
                                            disabled={isCurrent || submittingPlanId === plan.id}
                                            isLoading={submittingPlanId === plan.id}
                                            onClick={() => handleSubscribe(plan.id)}
                                            className="w-full"
                                        >
                                            {isCurrent ? 'Active Plan' : `Upgrade to ${plan.name}`}
                                        </Button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
