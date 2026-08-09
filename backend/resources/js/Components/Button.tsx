import React, { ButtonHTMLAttributes } from 'react';
import LoadingSpinner from './LoadingSpinner';

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: 'primary' | 'secondary' | 'outline' | 'danger' | 'ghost';
    size?: 'sm' | 'md' | 'lg';
    isLoading?: boolean;
}

export const Button: React.FC<ButtonProps> = ({
    variant = 'primary',
    size = 'md',
    isLoading = false,
    disabled = false,
    children,
    className = '',
    ...props
}) => {
    const baseStyles = 'inline-flex items-center justify-center font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';

    const variantStyles = {
        primary: 'bg-amber-600 hover:bg-amber-700 text-white focus:ring-amber-500',
        secondary: 'bg-stone-800 hover:bg-stone-900 text-white focus:ring-stone-700',
        outline: 'border border-stone-300 bg-white text-stone-700 hover:bg-stone-50 focus:ring-amber-500',
        danger: 'bg-red-600 hover:bg-red-700 text-white focus:ring-red-500',
        ghost: 'text-stone-600 hover:bg-stone-100 focus:ring-stone-500',
    };

    const sizeStyles = {
        sm: 'px-3 py-1.5 text-xs',
        md: 'px-4 py-2 text-sm',
        lg: 'px-5 py-2.5 text-base',
    };

    return (
        <button
            disabled={disabled || isLoading}
            className={`${baseStyles} ${variantStyles[variant]} ${sizeStyles[size]} ${className}`}
            {...props}
        >
            {isLoading && <LoadingSpinner size="sm" className="mr-2 text-current" />}
            {children}
        </button>
    );
};

export default Button;
