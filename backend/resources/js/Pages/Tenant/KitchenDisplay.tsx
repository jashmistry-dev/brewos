import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, usePage, router } from '@inertiajs/react';

interface KitchenOrderItem {
    id: number;
    name: string;
    quantity: number;
}

interface KitchenOrder {
    id: number;
    order_number: string;
    table_name: string | null;
    status: string;
    created_at: string;
    elapsed_minutes: number;
    items: KitchenOrderItem[];
}

interface Props {
    orders: KitchenOrder[];
}

export default function KitchenDisplay({ orders }: Props) {
    const { tenant } = usePage<{ tenant: { cafe?: { slug: string; name: string } } }>().props;
    const cafeSlug = tenant.cafe?.slug || '';

    React.useEffect(() => {
        const interval = setInterval(() => {
            router.reload({ only: ['orders'] });
        }, 3000);
        return () => clearInterval(interval);
    }, []);

    const handleUpdateStatus = (orderId: number, status: string) => {
        router.patch(`/cafes/${cafeSlug}/orders/${orderId}/status`, { status }, { preserveScroll: true });
    };

    return (
        <AppLayout title="Kitchen Display System (KDS)" cafeSlug={cafeSlug}>
            <Head title={`Kitchen Display — ${tenant.cafe?.name || 'Workspace'}`} />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-stone-200 pb-4">
                    <div>
                        <h1 className="text-2xl font-bold text-stone-900 tracking-tight">Kitchen Display System</h1>
                        <p className="text-xs text-stone-500 mt-0.5">Real-time order tickets for kitchen staff to prepare and mark orders ready.</p>
                    </div>

                    <div className="flex items-center gap-2 bg-stone-900 text-amber-400 px-3 py-1.5 rounded-xl text-xs font-bold shadow-sm">
                        <span>🔥 Active Kitchen Tickets ({orders.length})</span>
                    </div>
                </div>

                {/* Tickets Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    {orders.length === 0 ? (
                        <div className="col-span-full text-center py-20 bg-white rounded-2xl border border-stone-200 p-6">
                            <p className="text-4xl mb-2">👨‍🍳</p>
                            <h3 className="font-bold text-stone-900 text-lg">Kitchen queue is clear!</h3>
                            <p className="text-xs text-stone-500 mt-1">All incoming orders have been prepared and completed.</p>
                        </div>
                    ) : (
                        orders.map((o) => (
                            <div
                                key={o.id}
                                className={`bg-stone-900 text-stone-100 rounded-2xl p-5 border flex flex-col justify-between shadow-xl space-y-4 ${
                                    o.elapsed_minutes > 15
                                        ? 'border-rose-500 ring-2 ring-rose-500/20'
                                        : 'border-stone-800'
                                }`}
                            >
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between border-b border-stone-800 pb-3">
                                        <div className="space-y-1">
                                            <div className="text-lg font-black text-amber-400 tracking-wider font-mono flex items-center gap-1.5">
                                                <span>#ORD-{o.order_number.replace(/^ORD-/, '')}</span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <span className="bg-stone-800 text-stone-200 text-xs px-2.5 py-1 rounded-lg font-extrabold border border-stone-700 inline-block">
                                                    🪑 {o.table_name ? `Table: ${o.table_name}` : 'Takeaway / Counter'}
                                                </span>
                                            </div>
                                        </div>

                                        <div className="text-right">
                                            <span className={`text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider ${
                                                o.elapsed_minutes > 15 ? 'bg-rose-500 text-white animate-pulse' : 'bg-stone-800 text-stone-300'
                                            }`}>
                                                {o.elapsed_minutes}m ago
                                            </span>
                                        </div>
                                    </div>

                                    {/* Itemized List */}
                                    <div className="space-y-2 text-xs divide-y divide-stone-800/60 pt-1">
                                        {o.items.map((item) => (
                                            <div key={item.id} className="pt-2 first:pt-0 flex items-center justify-between font-semibold">
                                                <span className="text-stone-200">{item.name}</span>
                                                <span className="bg-amber-500 text-stone-950 font-extrabold px-2 py-0.5 rounded text-xs">
                                                    × {item.quantity}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                {/* Status Progress Button */}
                                <div className="pt-3 border-t border-stone-800">
                                    {o.status === 'kitchen_pending' && (
                                        <button
                                            onClick={() => handleUpdateStatus(o.id, 'preparing')}
                                            className="w-full bg-amber-500 hover:bg-amber-400 text-stone-950 font-extrabold text-xs py-2.5 rounded-xl transition-colors shadow-md"
                                        >
                                            👨‍🍳 Start Preparing Order &rarr;
                                        </button>
                                    )}

                                    {o.status === 'preparing' && (
                                        <button
                                            onClick={() => handleUpdateStatus(o.id, 'ready')}
                                            className="w-full bg-emerald-500 hover:bg-emerald-400 text-stone-950 font-extrabold text-xs py-2.5 rounded-xl transition-colors shadow-md"
                                        >
                                            ✓ Mark Ready to Serve &rarr;
                                        </button>
                                    )}

                                    {o.status === 'ready' && (
                                        <button
                                            onClick={() => handleUpdateStatus(o.id, 'completed')}
                                            className="w-full bg-stone-800 hover:bg-stone-700 text-stone-200 font-bold text-xs py-2.5 rounded-xl transition-colors"
                                        >
                                            Complete Ticket
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
