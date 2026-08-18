import React from 'react';
import { Head, Link, router } from '@inertiajs/react';

export interface CustomerOrderStatusProps {
    qr_url?: string | null;
    order: {
        id: number;
        order_number: string;
        cafe_slug?: string;
        qr_token?: string;
        cafe_name: string;
        branch_name: string;
        table_name: string;
        status: string;
        payment_status: string;
        subtotal: number;
        tax: number;
        total: number;
        created_at: string;
        items: Array<{
            id: number;
            name: string;
            quantity: number;
            unit_price: number;
            total: number;
        }>;
    };
}

export default function CustomerOrderStatus({ order, qr_url }: CustomerOrderStatusProps) {
    React.useEffect(() => {
        const isTerminal = ['completed', 'cancelled', 'served'].includes(order?.status);
        if (isTerminal) return;

        const timer = setInterval(() => {
            router.reload({ only: ['order'] });
        }, 4000);

        return () => clearInterval(timer);
    }, [order?.status]);

    React.useEffect(() => {
        if (order?.order_number && order?.cafe_slug && order?.qr_token) {
            const storageKey = `brewos_active_order_${order.cafe_slug}_${order.qr_token}`;
            localStorage.setItem(
                storageKey,
                JSON.stringify({
                    order_number: order.order_number,
                    created_at: Date.now(),
                })
            );
        }
    }, [order?.order_number, order?.cafe_slug, order?.qr_token]);

    const steps = [
        { key: 'placed', label: 'Order Placed', done: true },
        {
            key: 'payment',
            label: order.payment_status === 'paid' ? 'Payment Confirmed' : 'Payment Pending at Counter',
            done: order.payment_status === 'paid',
            active: order.payment_status !== 'paid',
        },
        {
            key: 'kitchen',
            label: 'Kitchen Preparing',
            done: ['preparing', 'ready', 'served', 'completed'].includes(order.status),
            active: ['kitchen_pending', 'preparing', 'confirmed'].includes(order.status) && order.payment_status === 'paid',
        },
        {
            key: 'ready',
            label: 'Ready / Served',
            done: ['ready', 'served', 'completed'].includes(order.status),
            active: order.status === 'ready',
        },
    ];

    return (
        <div className="min-h-screen bg-stone-950 text-stone-100 flex flex-col font-sans p-4">
            <Head title={`Order #${order.order_number} Status — ${order.cafe_name}`} />

            <div className="max-w-md mx-auto w-full flex-1 flex flex-col justify-center space-y-6 my-auto">
                <div className="text-center space-y-1">
                    <span className="text-3xl">☕</span>
                    <h1 className="text-xl font-extrabold text-stone-100">{order.cafe_name}</h1>
                    <p className="text-xs text-amber-500 font-semibold">{order.branch_name} • {order.table_name}</p>
                    <p className="text-xs text-stone-400 font-mono pt-1">Order #{order.order_number}</p>
                </div>

                {/* Timeline Card */}
                <div className="bg-stone-900 border border-stone-800 rounded-3xl p-6 shadow-2xl space-y-5">
                    <h2 className="text-sm font-bold text-stone-200 border-b border-stone-800 pb-3">Live Order Status</h2>

                    <div className="space-y-4">
                        {steps.map((step, idx) => (
                            <div key={step.key} className="flex items-center gap-3">
                                <div
                                    className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 transition-colors ${
                                        step.done
                                            ? 'bg-emerald-500 text-stone-950 shadow-md shadow-emerald-500/20'
                                            : step.active
                                            ? 'bg-amber-500 text-stone-950 animate-pulse'
                                            : 'bg-stone-800 text-stone-500 border border-stone-700'
                                    }`}
                                >
                                    {step.done ? '✓' : idx + 1}
                                </div>
                                <span className={`text-xs font-semibold ${step.done ? 'text-emerald-400' : step.active ? 'text-amber-400 font-bold' : 'text-stone-500'}`}>
                                    {step.label}
                                </span>
                            </div>
                        ))}
                    </div>

                    {/* Payment Warning if Counter Pending */}
                    {order.payment_status !== 'paid' && (
                        <div className="bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs p-3.5 rounded-2xl leading-relaxed">
                            💡 <strong>Pay at Counter Required:</strong> Please proceed to the cashier counter to complete payment for Order #{order.order_number}. Once paid, your order will enter the kitchen.
                        </div>
                    )}
                </div>

                {/* Order Summary Breakdown */}
                <div className="bg-stone-900 border border-stone-800 rounded-3xl p-6 shadow-xl space-y-3">
                    <h3 className="text-xs font-bold text-stone-300 uppercase tracking-wider">Ordered Items</h3>
                    <div className="divide-y divide-stone-800/50 space-y-2 text-xs text-stone-300">
                        {order.items.map((item) => (
                            <div key={item.id} className="pt-2 first:pt-0 flex justify-between">
                                <span>{item.quantity} × {item.name}</span>
                                <span className="font-semibold text-stone-200">₹{item.total.toFixed(2)}</span>
                            </div>
                        ))}
                    </div>
                    <div className="border-t border-stone-800 pt-3 flex justify-between items-center text-sm font-extrabold">
                        <span>Total Paid / Due</span>
                        <span className="text-amber-400">₹{order.total.toFixed(2)}</span>
                    </div>
                </div>

                <div className="text-center pt-2">
                    <Link
                        href={
                            qr_url ||
                            (order.cafe_slug && order.qr_token
                                ? `/order/c/${order.cafe_slug}/t/${order.qr_token}`
                                : '/')
                        }
                        className="inline-block bg-amber-500 hover:bg-amber-600 text-stone-950 font-bold text-xs px-6 py-2.5 rounded-xl shadow-lg transition-colors"
                    >
                        ← Back to Customer Menu
                    </Link>
                </div>
            </div>
        </div>
    );
}
