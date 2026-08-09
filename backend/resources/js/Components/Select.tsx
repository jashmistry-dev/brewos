import React, { SelectHTMLAttributes, forwardRef } from 'react';

export interface SelectOption {
    label: string;
    value: string | number;
}

export interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
    label?: string;
    options: SelectOption[];
    error?: string;
    helperText?: string;
    placeholder?: string;
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(({
    label,
    options,
    error,
    helperText,
    placeholder,
    id,
    className = '',
    ...props
}, ref) => {
    const selectId = id || (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);

    return (
        <div className="w-full">
            {label && (
                <label htmlFor={selectId} className="block text-sm font-medium text-stone-700 mb-1">
                    {label}
                </label>
            )}
            <select
                id={selectId}
                ref={ref}
                className={`w-full px-3.5 py-2 text-sm bg-white border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-500 transition-colors ${
                    error
                        ? 'border-red-500 text-red-900 focus:border-red-500 focus:ring-red-500'
                        : 'border-stone-300 text-stone-900 focus:border-amber-500'
                } ${className}`}
                {...props}
            >
                {placeholder && (
                    <option value="" disabled>
                        {placeholder}
                    </option>
                )}
                {options.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                        {opt.label}
                    </option>
                ))}
            </select>
            {error ? (
                <p className="mt-1 text-xs text-red-600 font-medium">{error}</p>
            ) : helperText ? (
                <p className="mt-1 text-xs text-stone-500">{helperText}</p>
            ) : null}
        </div>
    );
});

Select.displayName = 'Select';

export default Select;
