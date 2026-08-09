import React from 'react';

export interface StatusBadgeProps {
    status: string;
    className?: string;
}

export const StatusBadge: React.FC<StatusBadgeProps> = ({ status, className = '' }) => {
    const normalized = (status || '').toLowerCase();

    let colorStyles = 'bg-stone-100 text-stone-700 border-stone-200';

    switch (normalized) {
        case 'active':
        case 'completed':
        case 'paid':
        case 'ready':
            colorStyles = 'bg-emerald-50 text-emerald-700 border-emerald-200';
            break;
        case 'inactive':
        case 'cancelled':
        case 'unpaid':
            colorStyles = 'bg-rose-50 text-rose-700 border-rose-200';
            break;
        case 'pending':
        case 'preparing':
            colorStyles = 'bg-amber-50 text-amber-700 border-amber-200';
            break;
        case 'trialing':
            colorStyles = 'bg-sky-50 text-sky-700 border-sky-200';
            break;
    }

    return (
        <span
            className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${colorStyles} ${className}`}
        >
            {status}
        </span>
    );
};

export default StatusBadge;
