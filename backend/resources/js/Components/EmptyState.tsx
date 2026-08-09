import React, { ReactNode } from 'react';

export interface EmptyStateProps {
    title: string;
    description?: string;
    action?: ReactNode;
    icon?: ReactNode;
}

export const EmptyState: React.FC<EmptyStateProps> = ({
    title,
    description,
    action,
    icon,
}) => {
    return (
        <div className="flex flex-col items-center justify-center text-center py-12 px-4">
            <div className="w-12 h-12 rounded-full bg-stone-100 flex items-center justify-center text-stone-400 mb-4 text-xl">
                {icon || '☕'}
            </div>
            <h3 className="text-base font-semibold text-stone-900">{title}</h3>
            {description && (
                <p className="mt-1 text-sm text-stone-500 max-w-sm">{description}</p>
            )}
            {action && <div className="mt-6">{action}</div>}
        </div>
    );
};

export default EmptyState;
