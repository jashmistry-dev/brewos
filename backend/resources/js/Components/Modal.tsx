import React, { ReactNode, useEffect } from 'react';

export interface ModalProps {
    isOpen: boolean;
    onClose: () => void;
    title?: string;
    children: ReactNode;
    footer?: ReactNode;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
}

export const Modal: React.FC<ModalProps> = ({
    isOpen,
    onClose,
    title,
    children,
    footer,
    maxWidth = 'md',
}) => {
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && isOpen) {
                onClose();
            }
        };

        if (isOpen) {
            document.body.style.overflow = 'hidden';
            window.addEventListener('keydown', handleKeyDown);
        }

        return () => {
            document.body.style.overflow = 'unset';
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [isOpen, onClose]);

    if (!isOpen) return null;

    const maxWidthClasses = {
        sm: 'max-w-sm',
        md: 'max-w-md',
        lg: 'max-w-lg',
        xl: 'max-w-xl',
        '2xl': 'max-w-2xl',
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-stone-900/50 backdrop-blur-sm transition-opacity"
                onClick={onClose}
            ></div>

            {/* Modal Dialog */}
            <div className="flex min-h-full items-center justify-center p-4 text-center">
                <div
                    className={`w-full ${maxWidthClasses[maxWidth]} transform overflow-hidden rounded-xl bg-white p-6 text-left align-middle shadow-xl transition-all z-10 border border-stone-100`}
                >
                    {title && (
                        <div className="flex items-center justify-between pb-3 mb-4 border-b border-stone-100">
                            <h3 className="text-lg font-semibold text-stone-900">{title}</h3>
                            <button
                                onClick={onClose}
                                className="text-stone-400 hover:text-stone-600 p-1 rounded-lg transition-colors"
                            >
                                ✕
                            </button>
                        </div>
                    )}
                    <div className="mt-2">{children}</div>
                    {footer && <div className="mt-6 flex justify-end gap-3 pt-3 border-t border-stone-100">{footer}</div>}
                </div>
            </div>
        </div>
    );
};

export default Modal;
