import React, { useState, useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';

interface OrderItem {
    id: number;
    name: string;
    quantity: number;
    unit_price: number;
    total: number;
}

interface OrderDetails {
    id: number;
    order_number: string;
    cafe_name: string;
    cafe_slug: string;
    branch_name: string;
    table_name: string;
    status: string;
    payment_status: string;
    subtotal: number;
    tax: number;
    total: number;
    customer_name?: string;
    customer_phone?: string;
    created_at: string;
    items: OrderItem[];
}

interface Props {
    order: OrderDetails;
}

export default function CustomerOrderStatus({ order: initialOrder }: Props) {
    const [order, setOrder] = useState<OrderDetails>(initialOrder);
    const [isRefreshing, setIsRefreshing] = useState(false);

    useEffect(() => {
        const isTerminal = ['completed', 'cancelled', 'served'].includes(order?.status);
        if (isTerminal) return;

        const interval = setInterval(async () => {
            try {
                const res = await fetch(`/order/status-json/${order.order_number}`);
                if (res.ok) {
                    const data = await res.json();
                    if (data.order) {
                        setOrder(data.order);
                    }
                }
            } catch (err) {
                console.error('Failed to poll status:', err);
            }
        }, 3000);

        return () => clearInterval(interval);
    }, [order?.order_number, order?.status]);

    const handleManualRefresh = async () => {
        setIsRefreshing(true);
        try {
            const res = await fetch(`/order/status-json/${order.order_number}`);
            if (res.ok) {
                const data = await res.json();
                if (data.order) setOrder(data.order);
            }
        } catch (err) {
            console.error('Manual refresh error:', err);
        } finally {
            setIsRefreshing(false);
        }
    };

    const statusSteps = [
        { key: 'placed', label: 'Order Placed', icon: '📝' },
        { key: 'kitchen_pending', label: 'Sent to Kitchen', icon: '👨‍🍳' },
        { key: 'preparing', label: 'Preparing', icon: '🍳' },
        { key: 'ready', label: 'Ready for Serve', icon: '🔔' },
        { key: 'completed', label: 'Completed', icon: '🎉' },
    ];

    const getCurrentStepIndex = () => {
        const idx = statusSteps.findIndex((s) => s.key === order.status);
        if (idx !== -1) return idx;
        if (order.status === 'served') return 4;
        return 0;
    };

    const currentStepIdx = getCurrentStepIndex();

    return (
        <div className="min-h-screen bg-stone-950 text-stone-100 flex flex-col font-sans">
            <Head title={`Order #${order.order_number} Status — ${order.cafe_name}`} />

            {/* Top Navigation */}
            <header className="bg-stone-900 border-b border-stone-800 px-4 py-3 sticky top-0 z-10 shadow-md">
                <div className="max-w-md mx-auto flex items-center justify-between">
                    <div>
                        <h1 className="font-bold text-sm text-stone-100">{order.cafe_name}</h1>
                        <p className="text-[11px] text-stone-400">{order.branch_name} • Table: {order.table_name}</p>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            onClick={handleManualRefresh}
                            className={`p-1.5 bg-stone-800 rounded-lg text-stone-300 text-xs transition ${isRefreshing ? 'animate-spin' : 'hover:text-white'}`}
                            title="Refresh status"
                        >
                            🔄
                        </button>

                        <a
                            href={`/public/qr/${order.cafe_slug}/menu`}
                            className="bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs px-3 py-1.5 rounded-lg transition-colors"
                        >
                            Menu
                        </a>
                    </div>
                </div>
            </header>

            <main className="max-w-md mx-auto w-full px-4 py-6 flex-1 space-y-6">
                {/* Status Hero Card */}
                <div className="bg-stone-900 border border-stone-800 rounded-2xl p-6 text-center shadow-xl relative overflow-hidden">
                    <div className="absolute top-3 right-3">
                        <span className="text-[10px] bg-stone-800 text-stone-400 px-2 py-0.5 rounded-full font-mono">
                            LIVE SYNC
                        </span>
                    </div>

                    <div className="text-4xl mb-2 animate-bounce">
                        {statusSteps[currentStepIdx]?.icon || '☕'}
                    </div>

                    <h2 className="text-xl font-black text-amber-400 tracking-tight capitalize">
                        {order.status.replace(/_/g, ' ')}
                    </h2>

                    <p className="text-xs text-stone-400 mt-1">
                        Order <span className="font-mono font-bold text-stone-200">#{order.order_number}</span>
                    </p>

                    {/* Progress Timeline */}
                    <div className="mt-6 pt-6 border-t border-stone-800 flex justify-between items-center relative">
                        <div className="absolute left-4 right-4 top-1/2 h-1 bg-stone-800 -translate-y-1/2 z-0" />
                        <div
                            className="absolute left-4 top-1/2 h-1 bg-amber-500 -translate-y-1/2 z-0 transition-all duration-500"
                            style={{ width: `${(currentStepIdx / (statusSteps.length - 1)) * 88}%` }}
                        />

                        {statusSteps.map((step, i) => {
                            const isDone = i <= currentStepIdx;
                            return (
                                <div key={step.key} className="relative z-10 flex flex-col items-center">
                                    <div
                                        className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all ${
                                            isDone
                                                ? 'bg-amber-500 text-stone-950 ring-4 ring-amber-500/20'
                                                : 'bg-stone-800 text-stone-500 border border-stone-700'
                                        }`}
                                    >
                                        {isDone ? '✓' : i + 1}
                                    </div>
                                    <span className="text-[9px] text-stone-400 font-semibold mt-1 max-w-[50px] leading-tight hidden sm:block">
                                        {step.label}
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Customer Details & Actions */}
                <div className="bg-stone-900 border border-stone-800 rounded-2xl p-4 space-y-3">
                    <div className="flex justify-between items-center text-xs">
                        <span className="text-stone-400 font-semibold">Customer Details:</span>
                        <span className="text-stone-200 font-bold">{order.customer_name || 'Guest Customer'} ({order.customer_phone || 'N/A'})</span>
                    </div>

                    <div className="flex justify-between items-center text-xs pt-2 border-t border-stone-800">
                        <span className="text-stone-400 font-semibold">Payment Status:</span>
                        <span className={`font-bold uppercase text-[11px] px-2 py-0.5 rounded ${order.payment_status === 'paid' ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-amber-950 text-amber-400 border border-amber-800'}`}>
                            {order.payment_status}
                        </span>
                    </div>

                    {/* View Invoice Action Button */}
                    <div className="pt-2">
                        <a
                            href={`/order/invoice/${order.order_number}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="w-full bg-stone-800 hover:bg-stone-700 text-amber-400 border border-stone-700 text-xs font-bold py-2.5 px-4 rounded-xl flex items-center justify-center gap-2 transition-colors shadow-sm"
                        >
                            📄 View & Download Tax Invoice / POS Receipt &rarr;
                        </a>
                    </div>
                </div>

                {/* Itemized Order Breakdown */}
                <div className="bg-stone-900 border border-stone-800 rounded-2xl p-4 space-y-3">
                    <h3 className="text-xs font-bold text-stone-300 uppercase tracking-wider border-b border-stone-800 pb-2">
                        Order Items Summary
                    </h3>

                    <div className="divide-y divide-stone-800/60">
                        {order.items && order.items.map((item) => (
                            <div key={item.id} className="py-2 flex justify-between items-center text-xs">
                                <div>
                                    <span className="font-bold text-stone-200">{item.name}</span>
                                    <span className="text-stone-500 ml-2">x{item.quantity}</span>
                                </div>
                                <span className="font-mono text-stone-300">₹{item.total.toFixed(2)}</span>
                            </div>
                        ))}
                    </div>

                    <div className="pt-3 border-t border-stone-800 space-y-1 text-xs">
                        <div className="flex justify-between text-stone-400">
                            <span>Subtotal</span>
                            <span>₹{order.subtotal.toFixed(2)}</span>
                        </div>
                        {order.tax > 0 && (
                            <div className="flex justify-between text-stone-400">
                                <span>Tax / GST</span>
                                <span>₹{order.tax.toFixed(2)}</span>
                            </div>
                        )}
                        <div className="flex justify-between font-extrabold text-sm text-amber-400 pt-2 border-t border-stone-800">
                            <span>Total</span>
                            <span>₹{order.total.toFixed(2)}</span>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    );
}