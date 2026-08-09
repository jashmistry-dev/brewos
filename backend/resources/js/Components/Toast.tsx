import React, { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { PageProps } from '../types';

export const Toast: React.FC = () => {
    const { flash } = usePage<PageProps>().props;
    const [visible, setVisible] = useState(false);
    const [message, setMessage] = useState<string | null>(null);
    const [type, setType] = useState<'success' | 'error'>('success');

    useEffect(() => {
        if (flash?.success) {
            setMessage(flash.success);
            setType('success');
            setVisible(true);
        } else if (flash?.error) {
            setMessage(flash.error);
            setType('error');
            setVisible(true);
        }
    }, [flash]);

    useEffect(() => {
        if (visible) {
            const timer = setTimeout(() => {
                setVisible(false);
            }, 5000);
            return () => clearTimeout(timer);
        }
    }, [visible]);

    if (!visible || !message) return null;

    const isSuccess = type === 'success';

    return (
        <div className="fixed bottom-5 right-5 z-50 max-w-sm w-full animate-bounce-once">
            <div
                className={`flex items-center justify-between p-4 rounded-xl shadow-lg border text-sm font-medium ${
                    isSuccess
                        ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                        : 'bg-rose-50 text-rose-800 border-rose-200'
                }`}
            >
                <div className="flex items-center gap-2">
                    <span>{isSuccess ? '✓' : '⚠️'}</span>
                    <span>{message}</span>
                </div>
                <button
                    onClick={() => setVisible(false)}
                    className="ml-4 text-stone-400 hover:text-stone-600 focus:outline-none"
                >
                    ✕
                </button>
            </div>
        </div>
    );
};

export default Toast;
