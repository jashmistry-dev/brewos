import React, { InputHTMLAttributes, forwardRef } from 'react';

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    error?: string;
    helperText?: string;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(({
    label,
    error,
    helperText,
    id,
    className = '',
    ...props
}, ref) => {
    const inputId = id || (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);

    return (
        <div className="w-full">
            {label && (
                <label htmlFor={inputId} className="block text-sm font-medium text-stone-700 mb-1">
                    {label}
                </label>
            )}
            <input
                id={inputId}
                ref={ref}
                className={`w-full px-3.5 py-2 text-sm bg-white border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-500 transition-colors ${
                    error
                        ? 'border-red-500 text-red-900 placeholder-red-300 focus:border-red-500 focus:ring-red-500'
                        : 'border-stone-300 text-stone-900 placeholder-stone-400 focus:border-amber-500'
                } ${className}`}
                {...props}
            />
            {error ? (
                <p className="mt-1 text-xs text-red-600 font-medium">{error}</p>
            ) : helperText ? (
                <p className="mt-1 text-xs text-stone-500">{helperText}</p>
            ) : null}
        </div>
    );
});

Input.displayName = 'Input';

export default Input;
