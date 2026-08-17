import React from 'react';
import Button from './Button';

interface Props {
    isOpen: boolean;
    onClose: () => void;
    title?: string;
    description?: string;
    cafeSlug?: string;
}

export default function EntitlementModal({
    isOpen,
    onClose,
    title = 'Subscription Limit Reached',
    description = 'You have reached the maximum allowed limit for your current subscription plan. Upgrade your plan to add more resources.',
    cafeSlug,
}: Props) {
    if (!isOpen) return null;

    const handleUpgrade = () => {
        if (cafeSlug) {
            window.location.href = `/cafes/${cafeSlug}/subscription`;
        } else {
            onClose();
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-stone-950/80 backdrop-blur-sm animate-fade-in">
            <div className="bg-stone-900 border border-stone-800 rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4">
                <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full bg-amber-500/10 text-amber-500 flex items-center justify-center text-xl font-bold">
                        ⚡
                    </div>
                    <div>
                        <h3 className="text-lg font-bold text-stone-100">{title}</h3>
                        <p className="text-xs text-stone-400">Plan upgrade required</p>
                    </div>
                </div>

                <p className="text-sm text-stone-300 leading-relaxed">
                    {description}
                </p>

                <div className="flex items-center justify-end gap-3 pt-4 border-t border-stone-800">
                    <Button variant="secondary" size="sm" onClick={onClose}>
                        Dismiss
                    </Button>
                    <Button variant="primary" size="sm" onClick={handleUpgrade}>
                        View Subscription Plans &rarr;
                    </Button>
                </div>
            </div>
        </div>
    );
}
