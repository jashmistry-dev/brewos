import React, { ReactNode } from 'react';
import Toast from '../Components/Toast';

export interface CustomerLayoutProps {
    children: ReactNode;
    cafeName?: string;
    tableNumber?: string;
}

export const CustomerLayout: React.FC<CustomerLayoutProps> = ({
    children,
    cafeName = 'BrewOS Cafe',
    tableNumber,
}) => {
    return (
        <div className="min-h-screen bg-stone-50 text-stone-900 flex flex-col max-w-md mx-auto shadow-2xl border-x border-stone-200">
            <Toast />

            {/* Mobile Header */}
            <header className="bg-amber-700 text-white p-4 sticky top-0 z-40 shadow-md flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <span className="text-xl">☕</span>
                    <div>
                        <h1 className="font-bold text-base leading-tight">{cafeName}</h1>
                        {tableNumber && (
                            <p className="text-xs text-amber-200 font-medium">Table #{tableNumber}</p>
                        )}
                    </div>
                </div>
                <span className="bg-amber-800 text-amber-100 text-[10px] uppercase font-bold px-2 py-0.5 rounded-full border border-amber-600">
                    QR Menu
                </span>
            </header>

            {/* Main Ordering Body */}
            <main className="flex-1 p-4 overflow-y-auto">{children}</main>
        </div>
    );
};

export default CustomerLayout;
