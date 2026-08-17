import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, usePage, router } from '@inertiajs/react';
import Button from '@/Components/Button';
import { unlockAudio, playNotificationSound } from '@/Utils/soundNotifications';

interface OrderItem {
    id: number;
    menu_item_id?: number;
    name?: string;
    quantity: number;
    unit_price: number;
    total: number;
}

interface OrderData {
    id: number;
    branch_id: number;
    table_id: number | null;
    table_name: string | null;
    order_number: string;
    status: string;
    payment_status: string;
    subtotal: number;
    tax: number;
    total: number;
    created_at: string;
    items_count?: number;
    items?: OrderItem[];
}

interface CustomerRequestData {
    id: number;
    table_name: string | null;
    branch_name: string | null;
    request_type: string;
    status: string;
    notes: string | null;
    created_at: string;
}

interface Props {
    orders: OrderData[];
    customer_requests?: CustomerRequestData[];
}

export default function Orders({ orders, customer_requests = [] }: Props) {
    const { tenant, auth } = usePage<{ tenant: { cafe?: { slug: string; name: string } }; auth: { permissions: string[] } }>().props;
    const cafeSlug = tenant.cafe?.slug || '';
    const [selectedStatusFilter, setSelectedStatusFilter] = useState<string>('all');
    const [confirmingOrder, setConfirmingOrder] = useState<OrderData | null>(null);
    const [paymentMethod, setPaymentMethod] = useState<'cash' | 'upi' | 'card'>('cash');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const [liveOrders, setLiveOrders] = useState<OrderData[]>(orders);
    const [liveRequests, setLiveRequests] = useState<CustomerRequestData[]>(customer_requests);

    React.useEffect(() => {
        setLiveOrders(orders);
    }, [orders]);

    React.useEffect(() => {
        setLiveRequests(customer_requests);
    }, [customer_requests]);

    const [soundEnabled, setSoundEnabled] = useState<boolean>(() => {
        return localStorage.getItem('brewos_sound_enabled') === 'true';
    });
    const seenRequestIdsRef = React.useRef<Set<number>>(new Set(customer_requests.map((r) => r.id)));

    const toggleSound = () => {
        const next = !soundEnabled;
        setSoundEnabled(next);
        localStorage.setItem('brewos_sound_enabled', String(next));
        console.log(`[Notification] Sound ${next ? 'ENABLED' : 'DISABLED'}`);
        if (next) {
            unlockAudio();
            playNotificationSound('call_staff');
        }
    };

    // Lightweight Realtime Background Polling Every 2.5 Seconds
    React.useEffect(() => {
        if (!cafeSlug) return;
        console.log('[Realtime] Polling initialized for cafe:', cafeSlug);

        const fetchRealtimeUpdates = async () => {
            try {
                const res = await fetch(`/cafes/${cafeSlug}/orders`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!res.ok) return;
                const data = await res.json();
                if (data.orders) setLiveOrders(data.orders);
                if (data.customer_requests) {
                    const newRequests: CustomerRequestData[] = data.customer_requests;
                    setLiveRequests(newRequests);

                    newRequests.forEach((r) => {
                        if ((r.status === 'pending' || r.status === 'acknowledged') && !seenRequestIdsRef.current.has(r.id)) {
                            console.log(`[CustomerRequest] Detected new request #${r.id} (${r.request_type}) for ${r.table_name || 'Table'}`);
                            seenRequestIdsRef.current.add(r.id);
                            if (soundEnabled) {
                                console.log(`[Notification] Playing sound alert for request #${r.id}...`);
                                unlockAudio();
                                playNotificationSound(r.request_type);
                            } else {
                                console.log(`[Notification] Sound disabled. Enable sound via header button.`);
                            }
                        }
                    });
                }
            } catch (err) {
                console.warn('[Realtime] Polling error:', err);
            }
        };

        const interval = setInterval(fetchRealtimeUpdates, 2500);
        return () => {
            console.log('[Realtime] Polling unmounted.');
            clearInterval(interval);
        };
    }, [cafeSlug, soundEnabled]);

    const pendingRequests = liveRequests.filter((r) => r.status === 'pending' || r.status === 'acknowledged');

    const handleAcknowledgeRequest = (requestId: number, status: string = 'completed') => {
        // Optimistic UI update
        setLiveRequests((prev) => prev.map((r) => (r.id === requestId ? { ...r, status } : r)));
        router.post(`/cafes/${cafeSlug}/customer-requests/${requestId}/acknowledge`, { status }, { preserveScroll: true });
    };

    const filteredOrders = liveOrders.filter((o) => {
        if (selectedStatusFilter === 'all') return true;
        return o.status === selectedStatusFilter || o.payment_status === selectedStatusFilter;
    });

    const handleConfirmCounterPayment = (order: OrderData) => {
        setConfirmingOrder(order);
    };

    const submitConfirmPayment = (e: React.FormEvent) => {
        e.preventDefault();
        if (!confirmingOrder || isSubmitting) return;

        setIsSubmitting(true);
        router.post(
            `/cafes/${cafeSlug}/orders/${confirmingOrder.id}/confirm-payment`,
            { payment_method: paymentMethod },
            {
                onSuccess: () => {
                    setConfirmingOrder(null);
                    setIsSubmitting(false);
                },
                onError: () => setIsSubmitting(false),
            }
        );
    };

    const handleStatusUpdate = (orderId: number, newStatus: string) => {
        router.patch(`/cafes/${cafeSlug}/orders/${orderId}/status`, { status: newStatus }, { preserveScroll: true });
    };

    const getStatusBadgeClass = (status: string) => {
        switch (status) {
            case 'completed':
            case 'served':
            case 'paid':
                return 'bg-emerald-950 text-emerald-400 border-emerald-800';
            case 'kitchen_pending':
            case 'preparing':
            case 'confirmed':
                return 'bg-blue-950 text-blue-400 border-blue-800';
            case 'payment_pending':
            case 'pending_counter_confirmation':
                return 'bg-amber-950 text-amber-400 border-amber-800 animate-pulse';
            case 'cancelled':
                return 'bg-rose-950 text-rose-400 border-rose-800';
            default:
                return 'bg-stone-800 text-stone-300 border-stone-700';
        }
    };

    return (
        <AppLayout title="Orders Management" cafeSlug={cafeSlug}>
            <Head title={`Orders — ${tenant.cafe?.name || 'Workspace'}`} />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header & Status Filters */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-stone-200 pb-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold text-stone-900 tracking-tight">Dine-In & Counter Orders</h1>
                            <button
                                onClick={toggleSound}
                                type="button"
                                className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border transition-all ${
                                    soundEnabled
                                        ? 'bg-amber-100 text-amber-800 border-amber-300 shadow-sm'
                                        : 'bg-stone-100 text-stone-500 border-stone-200 hover:bg-stone-200'
                                }`}
                            >
                                <span>{soundEnabled ? '🔔 Sound ON' : '🔕 Sound OFF'}</span>
                            </button>
                        </div>
                        <p className="text-xs text-stone-500 mt-0.5">Real-time status updates, payment confirmations, and kitchen order tracking.</p>
                    </div>

                    {/* Filter Pills */}
                    <div className="flex items-center gap-1.5 overflow-x-auto py-1">
                        {['all', 'payment_pending', 'kitchen_pending', 'preparing', 'ready', 'completed'].map((st) => (
                            <button
                                key={st}
                                onClick={() => setSelectedStatusFilter(st)}
                                className={`px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors capitalize ${
                                    selectedStatusFilter === st
                                        ? 'bg-stone-900 text-white shadow-sm'
                                        : 'bg-white text-stone-600 border border-stone-200 hover:bg-stone-100'
                                }`}
                            >
                                {st.replace(/_/g, ' ')}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Active Table Service Requests Banner */}
                {pendingRequests.length > 0 && (
                    <div className="bg-amber-50 rounded-2xl border border-amber-200 p-5 shadow-sm space-y-3">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <span className="text-xl">🔔</span>
                                <h2 className="font-bold text-amber-900 text-sm">
                                    Active Table Service Requests ({pendingRequests.length})
                                </h2>
                            </div>
                            <span className="text-xs bg-amber-200 text-amber-900 px-2.5 py-0.5 rounded-full font-semibold">
                                Requires Staff Attention
                            </span>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            {pendingRequests.map((req) => (
                                <div key={req.id} className="bg-white rounded-xl p-3.5 border border-amber-200 shadow-sm flex items-center justify-between gap-3">
                                    <div className="space-y-0.5">
                                        <div className="text-xs font-bold text-stone-900">
                                            {req.table_name || 'Counter'} {req.branch_name ? `(${req.branch_name})` : ''}
                                        </div>
                                        <div className="text-xs font-semibold text-amber-700 capitalize">
                                            {req.request_type === 'call_staff' && '🔔 Call Staff Member'}
                                            {req.request_type === 'water' && '💧 Request Drinking Water'}
                                            {req.request_type === 'request_bill' && '🧾 Request Final Bill'}
                                            {req.request_type === 'assistance' && '❓ General Assistance'}
                                        </div>
                                        {req.created_at && (
                                            <div className="text-[10px] text-stone-400">
                                                {new Date(req.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                            </div>
                                        )}
                                    </div>
                                    <button
                                        onClick={() => handleAcknowledgeRequest(req.id, 'completed')}
                                        className="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold transition-colors whitespace-nowrap"
                                    >
                                        Complete ✓
                                    </button>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Orders List Table */}
                <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
                    {filteredOrders.length === 0 ? (
                        <div className="text-center py-16 p-6">
                            <p className="text-3xl mb-2">📋</p>
                            <h3 className="font-bold text-stone-900 text-base">No orders found</h3>
                            <p className="text-xs text-stone-500 mt-1">No orders match the selected filter criteria.</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="bg-stone-50 border-b border-stone-200 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                                        <th className="py-3 px-4">Order #</th>
                                        <th className="py-3 px-4">Table</th>
                                        <th className="py-3 px-4">Order Status</th>
                                        <th className="py-3 px-4">Payment</th>
                                        <th className="py-3 px-4">Total</th>
                                        <th className="py-3 px-4">Time</th>
                                        <th className="py-3 px-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-stone-100 text-xs">
                                    {filteredOrders.map((o) => (
                                        <tr key={o.id} className="hover:bg-stone-50/80 transition-colors">
                                            <td className="py-3.5 px-4 font-mono font-bold text-stone-900">
                                                {o.order_number}
                                            </td>
                                            <td className="py-3.5 px-4 font-semibold text-stone-700">
                                                {o.table_name ? (
                                                    <span className="bg-amber-100 text-amber-900 px-2 py-0.5 rounded font-bold">{o.table_name}</span>
                                                ) : (
                                                    <span className="text-stone-400">Counter</span>
                                                )}
                                            </td>
                                            <td className="py-3.5 px-4">
                                                <span className={`px-2.5 py-1 rounded-full text-[11px] font-bold border uppercase tracking-wider ${getStatusBadgeClass(o.status)}`}>
                                                    {o.status.replace(/_/g, ' ')}
                                                </span>
                                            </td>
                                            <td className="py-3.5 px-4">
                                                <span className={`px-2.5 py-1 rounded-full text-[11px] font-bold border uppercase tracking-wider ${getStatusBadgeClass(o.payment_status)}`}>
                                                    {o.payment_status.replace(/_/g, ' ')}
                                                </span>
                                            </td>
                                            <td className="py-3.5 px-4 font-extrabold text-stone-900">
                                                ${o.total.toFixed(2)}
                                            </td>
                                            <td className="py-3.5 px-4 text-stone-500">
                                                {new Date(o.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                            </td>
                                            <td className="py-3.5 px-4 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    {o.payment_status === 'pending_counter_confirmation' && (
                                                        <Button
                                                            variant="primary"
                                                            size="sm"
                                                            onClick={() => handleConfirmCounterPayment(o)}
                                                            className="bg-amber-600 hover:bg-amber-700 text-white font-bold"
                                                        >
                                                            💵 Confirm Payment
                                                        </Button>
                                                    )}

                                                    {o.status === 'kitchen_pending' && (
                                                        <Button
                                                            variant="secondary"
                                                            size="sm"
                                                            onClick={() => handleStatusUpdate(o.id, 'preparing')}
                                                        >
                                                            Start Preparing
                                                        </Button>
                                                    )}

                                                    {o.status === 'preparing' && (
                                                        <Button
                                                            variant="secondary"
                                                            size="sm"
                                                            onClick={() => handleStatusUpdate(o.id, 'ready')}
                                                        >
                                                            Mark Ready
                                                        </Button>
                                                    )}

                                                    {o.status === 'ready' && (
                                                        <Button
                                                            variant="primary"
                                                            size="sm"
                                                            onClick={() => handleStatusUpdate(o.id, 'completed')}
                                                        >
                                                            Complete
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* Confirm Counter Payment Modal */}
            {confirmingOrder && (
                <div className="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4 border border-stone-200">
                        <div className="flex justify-between items-center border-b border-stone-100 pb-3">
                            <h3 className="font-bold text-base text-stone-900">Confirm Counter Payment</h3>
                            <button onClick={() => setConfirmingOrder(null)} className="text-stone-400 hover:text-stone-600">✕</button>
                        </div>

                        <div className="bg-stone-50 p-4 rounded-xl text-xs space-y-2 border border-stone-200">
                            <div className="flex justify-between">
                                <span className="text-stone-500">Order Number</span>
                                <span className="font-mono font-bold text-stone-900">{confirmingOrder.order_number}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-stone-500">Table</span>
                                <span className="font-bold text-stone-900">{confirmingOrder.table_name || 'Counter'}</span>
                            </div>
                            <div className="flex justify-between text-sm font-extrabold border-t border-stone-200 pt-2">
                                <span>Amount Due</span>
                                <span className="text-amber-700">${confirmingOrder.total.toFixed(2)}</span>
                            </div>
                        </div>

                        <form onSubmit={submitConfirmPayment} className="space-y-4">
                            <div>
                                <label className="block text-xs font-bold text-stone-700 mb-1">Select Payment Method Received</label>
                                <div className="grid grid-cols-3 gap-2">
                                    {(['cash', 'upi', 'card'] as const).map((m) => (
                                        <button
                                            key={m}
                                            type="button"
                                            onClick={() => setPaymentMethod(m)}
                                            className={`p-2.5 rounded-xl border text-xs font-bold capitalize transition-colors ${
                                                paymentMethod === m
                                                    ? 'bg-amber-500 text-stone-950 border-amber-500 shadow-sm'
                                                    : 'bg-stone-50 text-stone-700 border-stone-200 hover:bg-stone-100'
                                            }`}
                                        >
                                            {m}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <div className="flex justify-end gap-2 pt-2 border-t border-stone-100">
                                <Button type="button" variant="secondary" onClick={() => setConfirmingOrder(null)}>
                                    Cancel
                                </Button>
                                <Button type="submit" variant="primary" disabled={isSubmitting} isLoading={isSubmitting}>
                                    Confirm Paid & Send to Kitchen &rarr;
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
