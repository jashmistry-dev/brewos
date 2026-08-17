import React, { useState, useEffect } from 'react';
import { Head, usePage } from '@inertiajs/react';

export interface MenuItemData {
    id: number;
    category_id: number;
    name: string;
    description: string | null;
    price: number;
    image_url: string | null;
    status: string;
}

export interface CategoryData {
    id: number;
    name: string;
}

export interface CustomerOrderProps {
    cafe: {
        id: number;
        name: string;
        slug: string;
        logo_url: string | null;
        currency: string;
        tax_rate: number;
        pay_at_counter_enabled: boolean;
        online_payment_enabled: boolean;
        allow_customer_reorder: boolean;
        call_staff_enabled: boolean;
        request_bill_enabled: boolean;
        require_location: boolean;
        location_radius_meters: number;
    };
    branch: {
        id: number;
        name: string;
        slug: string;
    };
    table: {
        id: number;
        name: string;
        capacity: number;
        qr_token: string;
    };
    session: {
        token: string;
        expires_at: string;
    };
    categories: CategoryData[];
    menu_items: MenuItemData[];
}

export interface CartItem {
    menu_item: MenuItemData;
    quantity: number;
    notes?: string;
}

export default function CustomerOrder({
    cafe,
    branch,
    table,
    session,
    categories,
    menu_items,
}: CustomerOrderProps) {
    const [selectedCategoryId, setSelectedCategoryId] = useState<number | 'all'>('all');
    const [searchQuery, setSearchQuery] = useState('');
    const [cart, setCart] = useState<{ [id: number]: CartItem }>({});
    const [isCartOpen, setIsCartOpen] = useState(false);
    const [paymentMethod, setPaymentMethod] = useState<'pay_at_counter' | 'online'>('pay_at_counter');
    const [customerNotes, setCustomerNotes] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [orderSuccess, setOrderSuccess] = useState<{ order_number: string; status: string } | null>(null);
    const [requestModalOpen, setRequestModalOpen] = useState(false);
    const [requestType, setRequestType] = useState<'call_staff' | 'water' | 'request_bill' | 'assistance'>('call_staff');
    const [requestSuccessMsg, setRequestSuccessMsg] = useState<string | null>(null);

    const pageProps = usePage().props as any;
    const serverActiveOrder = pageProps?.active_order?.order_number;

    const [activeOrderNumber, setActiveOrderNumber] = useState<string | null>(() => {
        if (serverActiveOrder) return serverActiveOrder;
        if (typeof window === 'undefined') return null;
        const cafeSlug = cafe?.slug;
        const tableToken = table?.qr_token;
        if (!cafeSlug) return null;

        const specificKey = `brewos_active_order_${cafeSlug}_${tableToken}`;
        const genericKey = `brewos_active_order_${cafeSlug}`;
        const saved = localStorage.getItem(specificKey) || localStorage.getItem(genericKey);

        if (saved) {
            try {
                const parsed = JSON.parse(saved);
                if (parsed.order_number && (Date.now() - (parsed.created_at || 0)) < 86400000) {
                    console.log('[TrackOrder] Context key:', specificKey);
                    console.log('[TrackOrder] Parsed order:', parsed.order_number);
                    return parsed.order_number;
                }
            } catch (e) {}
        }
        return null;
    });

    // Sync active order on prop / context update
    useEffect(() => {
        if (serverActiveOrder) {
            setActiveOrderNumber(serverActiveOrder);
        } else if (pageProps?.active_order === null) {
            setActiveOrderNumber(null);
        } else if (cafe?.slug) {
            const specificKey = `brewos_active_order_${cafe.slug}_${table?.qr_token}`;
            const genericKey = `brewos_active_order_${cafe.slug}`;
            const saved = localStorage.getItem(specificKey) || localStorage.getItem(genericKey);
            if (saved) {
                try {
                    const parsed = JSON.parse(saved);
                    if (parsed.order_number && (Date.now() - (parsed.created_at || 0)) < 86400000) {
                        setActiveOrderNumber(parsed.order_number);
                    }
                } catch (e) {}
            }
        }
    }, [serverActiveOrder, pageProps?.active_order, cafe?.slug, table?.qr_token]);

    // Filter items
    const filteredItems = menu_items.filter((item) => {
        const matchesCategory = selectedCategoryId === 'all' || item.category_id === selectedCategoryId;
        const matchesSearch = item.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            (item.description && item.description.toLowerCase().includes(searchQuery.toLowerCase()));
        return matchesCategory && matchesSearch;
    });

    const updateQuantity = (item: MenuItemData, delta: number) => {
        setCart((prev) => {
            const currentQty = prev[item.id]?.quantity || 0;
            const newQty = currentQty + delta;
            if (newQty <= 0) {
                const next = { ...prev };
                delete next[item.id];
                return next;
            }
            return {
                ...prev,
                [item.id]: {
                    menu_item: item,
                    quantity: newQty,
                    notes: prev[item.id]?.notes || '',
                },
            };
        });
    };

    const cartItemList = Object.values(cart);
    const cartItemCount = cartItemList.reduce((sum, ci) => sum + ci.quantity, 0);
    const subtotal = cartItemList.reduce((sum, ci) => sum + ci.menu_item.price * ci.quantity, 0);
    const taxAmount = (subtotal * cafe.tax_rate) / 100;
    const grandTotal = subtotal + taxAmount;

    const [isSendingRequest, setIsSendingRequest] = useState(false);

    const getCsrfToken = () => {
        if (pageProps?.csrf_token) return pageProps.csrf_token;
        const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (metaToken) return metaToken;
        return '';
    };

    const handleCheckout = async (e: React.FormEvent) => {
        e.preventDefault();
        if (cartItemCount === 0 || isSubmitting) return;

        setIsSubmitting(true);
        const csrfToken = getCsrfToken();
        try {
            const res = await fetch('/order/submit', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    _token: csrfToken,
                    session_token: session.token,
                    payment_method: paymentMethod,
                    customer_notes: customerNotes,
                    items: cartItemList.map((ci) => ({
                        menu_item_id: ci.menu_item.id,
                        quantity: ci.quantity,
                    })),
                }),
            });

            const data = await res.json();
            if (res.ok) {
                setCart({});
                setIsCartOpen(false);
                setOrderSuccess(data.order);
                if (data.order?.order_number) {
                    const activePayload = JSON.stringify({
                        order_number: data.order.order_number,
                        created_at: Date.now(),
                    });
                    localStorage.setItem(`brewos_active_order_${cafe.slug}_${table.qr_token}`, activePayload);
                    localStorage.setItem(`brewos_active_order_${cafe.slug}`, activePayload);
                    setActiveOrderNumber(data.order.order_number);
                }
            } else {
                alert(data.message || 'Failed to submit order. Please try again.');
            }
        } catch (err) {
            alert('An error occurred while submitting order.');
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleSendRequest = async (type: 'call_staff' | 'water' | 'request_bill' | 'assistance') => {
        if (isSendingRequest) return;
        setIsSendingRequest(true);
        const csrfToken = getCsrfToken();
        try {
            const res = await fetch('/order/request', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    _token: csrfToken,
                    session_token: session.token,
                    request_type: type,
                }),
            });

            const data = await res.json();
            if (res.ok) {
                setRequestModalOpen(false);
                setRequestSuccessMsg(data.message || 'Staff notified! Someone will assist you shortly.');
                setTimeout(() => setRequestSuccessMsg(null), 4000);
            } else {
                alert(data.message || 'Unable to send request right now.');
            }
        } catch (e) {
            alert('An error occurred while sending staff request.');
        } finally {
            setIsSendingRequest(false);
        }
    };

    return (
        <div className="min-h-screen bg-stone-950 text-stone-100 flex flex-col font-sans pb-28">
            <Head title={`${cafe.name} — Order at ${table.name}`} />

            {/* Mobile Header Banner */}
            <header className="bg-stone-900 border-b border-stone-800 p-4 sticky top-0 z-30 shadow-lg">
                <div className="max-w-md mx-auto flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        {cafe.logo_url ? (
                            <img src={cafe.logo_url} alt={cafe.name} className="w-10 h-10 rounded-full object-cover border border-amber-500/30" />
                        ) : (
                            <div className="w-10 h-10 rounded-full bg-amber-500/10 text-amber-500 flex items-center justify-center font-bold text-lg border border-amber-500/20">
                                ☕
                            </div>
                        )}
                        <div>
                            <h1 className="font-bold text-base text-stone-100 leading-tight">{cafe.name}</h1>
                            <p className="text-xs text-amber-500 font-medium">
                                {branch.name} • <span className="bg-amber-500/10 text-amber-400 px-1.5 py-0.5 rounded text-[11px] font-semibold">{table.name}</span>
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {cafe.call_staff_enabled && (
                            <button
                                onClick={() => setRequestModalOpen(true)}
                                className="bg-stone-800 hover:bg-stone-700 text-amber-400 text-xs px-2.5 py-1.5 rounded-lg border border-stone-700 flex items-center gap-1 font-medium transition-colors"
                            >
                                🔔 Call Staff
                            </button>
                        )}
                    </div>
                </div>
            </header>

            {/* Success Toast for Staff Notification */}
            {requestSuccessMsg && (
                <div className="bg-emerald-950 border border-emerald-800 text-emerald-300 text-xs p-3 text-center sticky top-16 z-30 animate-fade-in">
                    {requestSuccessMsg}
                </div>
            )}

            {/* Main Ordering Area */}
            <main className="max-w-md mx-auto w-full px-4 pt-4 flex-1 space-y-4">
                {/* Active Order Banner */}
                {activeOrderNumber && (
                    <div className="bg-gradient-to-r from-amber-600 via-amber-500 to-amber-600 text-stone-950 p-3.5 rounded-2xl shadow-lg border border-amber-400/80 flex items-center justify-between gap-3 animate-fade-in">
                        <div className="flex items-center gap-2.5">
                            <span className="text-xl">📦</span>
                            <div>
                                <p className="font-extrabold text-xs tracking-tight uppercase">Active Order Placed</p>
                                <p className="text-[11px] font-mono font-bold opacity-90">#{activeOrderNumber}</p>
                            </div>
                        </div>
                        <a
                            href={`/order/status/${activeOrderNumber}`}
                            className="bg-stone-950 hover:bg-stone-900 text-amber-400 font-extrabold text-xs px-3.5 py-2 rounded-xl shadow-md transition-all whitespace-nowrap flex items-center gap-1 border border-stone-800"
                        >
                            Track My Order &rarr;
                        </a>
                    </div>
                )}

                {/* Search Bar */}
                <div className="relative">
                    <input
                        type="text"
                        placeholder="Search delicious dishes, drinks..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="w-full bg-stone-900 border border-stone-800 rounded-xl px-4 py-2.5 text-sm text-stone-100 placeholder-stone-500 focus:outline-none focus:border-amber-500 transition-colors"
                    />
                </div>

                {/* Category Pills Slider */}
                <div className="flex items-center gap-2 overflow-x-auto no-scrollbar py-1">
                    <button
                        onClick={() => setSelectedCategoryId('all')}
                        className={`px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-colors ${
                            selectedCategoryId === 'all'
                                ? 'bg-amber-500 text-stone-950 shadow-md shadow-amber-500/10'
                                : 'bg-stone-900 text-stone-400 border border-stone-800 hover:text-stone-200'
                        }`}
                    >
                        All Items ({menu_items.length})
                    </button>
                    {categories.map((cat) => (
                        <button
                            key={cat.id}
                            onClick={() => setSelectedCategoryId(cat.id)}
                            className={`px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-colors ${
                                selectedCategoryId === cat.id
                                    ? 'bg-amber-500 text-stone-950 shadow-md shadow-amber-500/10'
                                    : 'bg-stone-900 text-stone-400 border border-stone-800 hover:text-stone-200'
                            }`}
                        >
                            {cat.name}
                        </button>
                    ))}
                </div>

                {/* Menu Item List */}
                <div className="space-y-3 pt-2">
                    {filteredItems.length === 0 ? (
                        <div className="text-center py-12 bg-stone-900/50 rounded-2xl border border-stone-800 p-6">
                            <p className="text-2xl mb-2">🍽️</p>
                            <p className="text-sm font-semibold text-stone-300">No menu items found</p>
                            <p className="text-xs text-stone-500 mt-1">Try selecting another category or search term.</p>
                        </div>
                    ) : (
                        filteredItems.map((item) => {
                            const currentQty = cart[item.id]?.quantity || 0;
                            return (
                                <div
                                    key={item.id}
                                    className="bg-stone-900 border border-stone-800/80 rounded-2xl p-4 flex gap-4 items-center shadow-sm hover:border-stone-700 transition-colors"
                                >
                                    {item.image_url ? (
                                        <img src={item.image_url} alt={item.name} className="w-20 h-20 rounded-xl object-cover border border-stone-800 shrink-0" />
                                    ) : (
                                        <div className="w-20 h-20 rounded-xl bg-stone-950 border border-stone-800 flex items-center justify-center text-2xl shrink-0">
                                            ☕
                                        </div>
                                    )}

                                    <div className="flex-1 min-w-0">
                                        <h3 className="font-bold text-sm text-stone-100 truncate">{item.name}</h3>
                                        {item.description && (
                                            <p className="text-xs text-stone-400 line-clamp-2 mt-0.5 leading-relaxed">{item.description}</p>
                                        )}
                                        <p className="text-sm font-extrabold text-amber-400 mt-2">
                                            {cafe.currency} {item.price.toFixed(2)}
                                        </p>
                                    </div>

                                    {/* Add / Qty Control */}
                                    <div className="shrink-0">
                                        {currentQty === 0 ? (
                                            <button
                                                onClick={() => updateQuantity(item, 1)}
                                                className="bg-amber-500 hover:bg-amber-400 text-stone-950 text-xs font-bold px-3 py-1.5 rounded-lg transition-colors shadow-sm"
                                            >
                                                + Add
                                            </button>
                                        ) : (
                                            <div className="flex items-center gap-2 bg-stone-950 border border-stone-800 rounded-lg p-1">
                                                <button
                                                    onClick={() => updateQuantity(item, -1)}
                                                    className="w-6 h-6 rounded bg-stone-800 hover:bg-stone-700 text-stone-200 font-bold text-xs flex items-center justify-center"
                                                >
                                                    -
                                                </button>
                                                <span className="text-xs font-bold text-amber-400 min-w-[14px] text-center">{currentQty}</span>
                                                <button
                                                    onClick={() => updateQuantity(item, 1)}
                                                    className="w-6 h-6 rounded bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs flex items-center justify-center"
                                                >
                                                    +
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>
            </main>

            {/* Floating Bottom Cart Bar */}
            {cartItemCount > 0 && !isCartOpen && (
                <div className="fixed bottom-4 left-0 right-0 px-4 z-40">
                    <div className="max-w-md mx-auto bg-amber-500 text-stone-950 rounded-2xl p-3.5 shadow-2xl flex items-center justify-between border border-amber-400 animate-slide-up">
                        <div className="flex items-center gap-3">
                            <div className="w-8 h-8 rounded-full bg-stone-950 text-amber-400 flex items-center justify-center font-bold text-xs">
                                {cartItemCount}
                            </div>
                            <div>
                                <p className="text-xs font-semibold text-stone-900 leading-tight">{cartItemCount} item{cartItemCount > 1 ? 's' : ''} in cart</p>
                                <p className="text-sm font-extrabold">{cafe.currency} {grandTotal.toFixed(2)}</p>
                            </div>
                        </div>
                        <button
                            onClick={() => setIsCartOpen(true)}
                            className="bg-stone-950 hover:bg-stone-900 text-amber-400 text-xs font-bold px-4 py-2 rounded-xl transition-colors shadow-md"
                        >
                            View Cart & Checkout &rarr;
                        </button>
                    </div>
                </div>
            )}

            {/* Cart & Checkout Sheet Modal */}
            {isCartOpen && (
                <div className="fixed inset-0 z-50 bg-stone-950/80 backdrop-blur-sm flex justify-center items-end sm:items-center p-0 sm:p-4">
                    <div className="bg-stone-900 border border-stone-800 rounded-t-3xl sm:rounded-2xl w-full max-w-md max-h-[90vh] flex flex-col shadow-2xl animate-slide-up overflow-hidden">
                        {/* Cart Header */}
                        <div className="p-4 border-b border-stone-800 flex items-center justify-between">
                            <div>
                                <h2 className="font-bold text-base text-stone-100">Review Your Order</h2>
                                <p className="text-xs text-stone-400">{table.name} • {cafe.name}</p>
                            </div>
                            <button
                                onClick={() => setIsCartOpen(false)}
                                className="w-8 h-8 rounded-full bg-stone-800 text-stone-400 hover:text-stone-200 flex items-center justify-center font-bold text-sm"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Cart Itemized List */}
                        <div className="p-4 flex-1 overflow-y-auto space-y-3 divide-y divide-stone-800/50">
                            {cartItemList.map(({ menu_item, quantity }) => (
                                <div key={menu_item.id} className="pt-3 first:pt-0 flex items-center justify-between">
                                    <div className="flex-1 pr-2">
                                        <p className="text-xs font-semibold text-stone-200">{menu_item.name}</p>
                                        <p className="text-xs text-amber-400 font-bold mt-0.5">
                                            {cafe.currency} {(menu_item.price * quantity).toFixed(2)}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2 bg-stone-950 border border-stone-800 rounded-lg p-1">
                                        <button
                                            onClick={() => updateQuantity(menu_item, -1)}
                                            className="w-5 h-5 rounded bg-stone-800 text-stone-200 text-xs font-bold flex items-center justify-center"
                                        >
                                            -
                                        </button>
                                        <span className="text-xs font-bold text-amber-400 min-w-[14px] text-center">{quantity}</span>
                                        <button
                                            onClick={() => updateQuantity(menu_item, 1)}
                                            className="w-5 h-5 rounded bg-amber-500 text-stone-950 text-xs font-bold flex items-center justify-center"
                                        >
                                            +
                                        </button>
                                    </div>
                                </div>
                            ))}

                            {/* Customer Notes */}
                            <div className="pt-4">
                                <label className="block text-xs font-semibold text-stone-400 mb-1">Kitchen Instructions / Notes</label>
                                <input
                                    type="text"
                                    placeholder="e.g. Extra hot, no sugar, allergic to nuts"
                                    value={customerNotes}
                                    onChange={(e) => setCustomerNotes(e.target.value)}
                                    className="w-full bg-stone-950 border border-stone-800 rounded-xl px-3 py-2 text-xs text-stone-200 placeholder-stone-600 focus:outline-none focus:border-amber-500"
                                />
                            </div>

                            {/* Payment Method Choice */}
                            <div className="pt-4">
                                <label className="block text-xs font-semibold text-stone-400 mb-2">Payment Method</label>
                                <div className="grid grid-cols-2 gap-2">
                                    {cafe.pay_at_counter_enabled && (
                                        <button
                                            type="button"
                                            onClick={() => setPaymentMethod('pay_at_counter')}
                                            className={`p-3 rounded-xl border text-xs font-bold flex flex-col items-center gap-1 transition-colors ${
                                                paymentMethod === 'pay_at_counter'
                                                    ? 'bg-amber-500/10 border-amber-500 text-amber-400'
                                                    : 'bg-stone-950 border-stone-800 text-stone-400 hover:text-stone-200'
                                            }`}
                                        >
                                            <span>💵 Pay at Counter</span>
                                            <span className="text-[10px] font-normal opacity-80">Pay cashier when ready</span>
                                        </button>
                                    )}
                                    {cafe.online_payment_enabled && (
                                        <button
                                            type="button"
                                            onClick={() => setPaymentMethod('online')}
                                            className={`p-3 rounded-xl border text-xs font-bold flex flex-col items-center gap-1 transition-colors ${
                                                paymentMethod === 'online'
                                                    ? 'bg-amber-500/10 border-amber-500 text-amber-400'
                                                    : 'bg-stone-950 border-stone-800 text-stone-400 hover:text-stone-200'
                                            }`}
                                        >
                                            <span>💳 Online Payment</span>
                                            <span className="text-[10px] font-normal opacity-80">UPI / Card / NetBanking</span>
                                        </button>
                                    )}
                                </div>
                            </div>

                            {/* Summary Breakdown */}
                            <div className="pt-4 space-y-1.5 text-xs text-stone-400">
                                <div className="flex justify-between">
                                    <span>Subtotal</span>
                                    <span>{cafe.currency} {subtotal.toFixed(2)}</span>
                                </div>
                                {cafe.tax_rate > 0 && (
                                    <div className="flex justify-between">
                                        <span>Tax ({cafe.tax_rate}%)</span>
                                        <span>{cafe.currency} {taxAmount.toFixed(2)}</span>
                                    </div>
                                )}
                                <div className="flex justify-between text-sm font-extrabold text-stone-100 pt-2 border-t border-stone-800">
                                    <span>Grand Total</span>
                                    <span className="text-amber-400">{cafe.currency} {grandTotal.toFixed(2)}</span>
                                </div>
                            </div>
                        </div>

                        {/* Submit Button */}
                        <div className="p-4 border-t border-stone-800 bg-stone-900">
                            <button
                                onClick={handleCheckout}
                                disabled={isSubmitting}
                                className="w-full bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-stone-950 font-extrabold text-sm py-3 rounded-xl transition-colors shadow-lg flex items-center justify-center gap-2"
                            >
                                {isSubmitting ? 'Submitting Order...' : `Place Order • ${cafe.currency} ${grandTotal.toFixed(2)}`}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Order Submitted Confirmation Screen */}
            {orderSuccess && (
                <div className="fixed inset-0 z-50 bg-stone-950 flex items-center justify-center p-4">
                    <div className="bg-stone-900 border border-stone-800 rounded-3xl p-6 max-w-md w-full text-center space-y-5 shadow-2xl">
                        <div className="w-16 h-16 rounded-full bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-3xl font-bold mx-auto border border-emerald-500/20">
                            ✓
                        </div>
                        <div>
                            <h2 className="text-xl font-extrabold text-stone-100">Order Placed Successfully!</h2>
                            <p className="text-xs text-amber-400 font-mono mt-1 font-bold">Order #{orderSuccess.order_number}</p>
                        </div>

                        <div className="bg-stone-950 p-4 rounded-2xl border border-stone-800 text-xs text-stone-300 space-y-2 text-left">
                            <div className="flex justify-between border-b border-stone-800 pb-2">
                                <span className="text-stone-500">Table</span>
                                <span className="font-bold text-stone-100">{table.name}</span>
                            </div>
                            <div className="flex justify-between border-b border-stone-800 pb-2">
                                <span className="text-stone-500">Payment Method</span>
                                <span className="font-bold text-stone-100 capitalize">{paymentMethod.replace(/_/g, ' ')}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-stone-500">Status</span>
                                <span className="font-bold text-amber-400">{orderSuccess.status === 'payment_pending' ? 'Please pay at counter' : 'Sent to Kitchen'}</span>
                            </div>
                        </div>

                        <div className="space-y-2 pt-2">
                            <a
                                href={`/order/status/${orderSuccess.order_number}`}
                                className="block w-full bg-amber-500 hover:bg-amber-400 text-stone-950 font-bold text-xs py-3 rounded-xl transition-colors shadow-md"
                            >
                                Track Live Order Status &rarr;
                            </a>
                            <button
                                onClick={() => setOrderSuccess(null)}
                                className="block w-full bg-stone-800 hover:bg-stone-700 text-stone-300 font-semibold text-xs py-2.5 rounded-xl transition-colors"
                            >
                                + Order More Items
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Call Staff / Request Bill Modal */}
            {requestModalOpen && (
                <div className="fixed inset-0 z-50 bg-stone-950/80 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-stone-900 border border-stone-800 rounded-2xl p-5 max-w-xs w-full shadow-2xl space-y-4">
                        <div className="flex justify-between items-center border-b border-stone-800 pb-3">
                            <h3 className="font-bold text-sm text-stone-100">Table Service Request</h3>
                            <button onClick={() => setRequestModalOpen(false)} className="text-stone-400 text-xs">✕</button>
                        </div>
                        <div className="grid grid-cols-1 gap-2">
                            <button
                                onClick={() => handleSendRequest('call_staff')}
                                className="p-3 rounded-xl bg-stone-950 border border-stone-800 hover:border-amber-500 text-xs font-semibold text-stone-200 flex items-center gap-2 transition-colors"
                            >
                                🔔 Call Staff Member
                            </button>
                            <button
                                onClick={() => handleSendRequest('water')}
                                className="p-3 rounded-xl bg-stone-950 border border-stone-800 hover:border-amber-500 text-xs font-semibold text-stone-200 flex items-center gap-2 transition-colors"
                            >
                                💧 Request Drinking Water
                            </button>
                            {cafe.request_bill_enabled && (
                                <button
                                    onClick={() => handleSendRequest('request_bill')}
                                    className="p-3 rounded-xl bg-stone-950 border border-stone-800 hover:border-amber-500 text-xs font-semibold text-stone-200 flex items-center gap-2 transition-colors"
                                >
                                    🧾 Request Final Bill
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
