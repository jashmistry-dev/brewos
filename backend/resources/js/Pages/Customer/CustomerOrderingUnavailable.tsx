import React from 'react';
import { Head } from '@inertiajs/react';

export interface CustomerOrderingUnavailableProps {
    cafeName?: string;
    message?: string;
}

export default function CustomerOrderingUnavailable({
    cafeName = 'Cafe',
    message = 'Online ordering for this cafe is temporarily unavailable. Please contact the cafe staff for assistance.',
}: CustomerOrderingUnavailableProps) {
    return (
        <div className="min-h-screen bg-stone-950 text-stone-100 flex items-center justify-center p-4 text-center font-sans">
            <Head title="Ordering Unavailable — BrewOS" />
            <div className="max-w-md w-full bg-stone-900 border border-stone-800 rounded-3xl p-8 shadow-2xl space-y-6">
                <div className="w-16 h-16 bg-amber-500/10 text-amber-500 border border-amber-500/20 rounded-full flex items-center justify-center mx-auto text-3xl font-bold">
                    ☕
                </div>

                <div className="space-y-2">
                    <h1 className="text-2xl font-extrabold text-stone-100 tracking-tight">
                        Ordering Unavailable
                    </h1>
                    <p className="text-xs font-semibold text-amber-500">
                        {cafeName}
                    </p>
                    <p className="text-xs text-stone-400 leading-relaxed pt-2">
                        {message}
                    </p>
                </div>

                <div className="pt-2">
                    <button
                        onClick={() => window.history.back()}
                        className="w-full bg-stone-800 hover:bg-stone-700 text-stone-200 font-bold text-xs py-3 rounded-xl transition-colors"
                    >
                        &larr; Back
                    </button>
                </div>
            </div>
        </div>
    );
}
