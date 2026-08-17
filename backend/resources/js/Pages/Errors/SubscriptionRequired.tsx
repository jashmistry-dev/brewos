import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import Button from '../../Components/Button';

export interface SubscriptionRequiredProps {
    cafeSlug?: string;
    cafeName?: string;
    message?: string;
}

export default function SubscriptionRequired({
    cafeSlug = '',
    cafeName = 'Workspace',
    message = 'Your cafe subscription is expired or inactive. Your cafe operations are temporarily unavailable until you renew or choose a plan.',
}: SubscriptionRequiredProps) {
    const handleLogout = (e: React.MouseEvent) => {
        e.preventDefault();
        router.post('/logout');
    };

    return (
        <div className="min-h-screen bg-stone-900 flex items-center justify-center p-4 text-center font-sans">
            <Head title="Subscription Required — BrewOS" />
            <div className="max-w-md w-full bg-white rounded-3xl p-8 shadow-2xl border border-stone-200 space-y-6">
                <div className="w-16 h-16 bg-amber-500/10 text-amber-600 rounded-full flex items-center justify-center mx-auto text-3xl font-bold border border-amber-500/20">
                    💳
                </div>

                <div>
                    <h1 className="text-2xl font-extrabold text-stone-900 tracking-tight">
                        Subscription Required
                    </h1>
                    <p className="text-sm text-stone-600 mt-2 leading-relaxed">
                        {message}
                    </p>
                </div>

                <div className="bg-stone-50 p-4 rounded-2xl border border-stone-200 text-left text-xs space-y-2 text-stone-700">
                    <div className="flex justify-between">
                        <span className="text-stone-500 font-medium">Cafe Workspace:</span>
                        <span className="font-semibold text-stone-900">{cafeName}</span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-stone-500 font-medium">Status:</span>
                        <span className="font-bold text-amber-700 uppercase">Subscription Needed</span>
                    </div>
                </div>

                <div className="flex flex-col gap-3 pt-2">
                    {cafeSlug ? (
                        <Link href={`/cafes/${cafeSlug}/subscription`}>
                            <Button variant="primary" size="lg" className="w-full">
                                View Plans & Upgrade →
                            </Button>
                        </Link>
                    ) : (
                        <Link href="/register-cafe">
                            <Button variant="primary" size="lg" className="w-full">
                                Select Plan / Register Cafe
                            </Button>
                        </Link>
                    )}

                    <button
                        onClick={handleLogout}
                        className="text-xs text-stone-500 hover:text-stone-800 font-medium pt-2 transition-colors"
                    >
                        Log Out &rarr;
                    </button>
                </div>
            </div>
        </div>
    );
}
