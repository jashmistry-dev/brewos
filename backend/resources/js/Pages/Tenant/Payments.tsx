import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, usePage } from '@inertiajs/react';

interface PaymentData {
    id: number;
    order_id: number;
    order_number: string | null;
    amount: number;
    method: string;
    status: string;
    transaction_reference: string | null;
    paid_at: string | null;
    created_at: string;
}

interface Props {
    payments: PaymentData[];
}

export default function Payments({ payments }: Props) {
    const { tenant } = usePage<{ tenant: { cafe?: { slug: string; name: string } } }>().props;
    const cafeSlug = tenant.cafe?.slug || '';

    const totalCollected = payments
        .filter((p) => p.status === 'completed' || p.status === 'paid')
        .reduce((sum, p) => sum + p.amount, 0);

    return (
        <AppLayout title="Payments History & Audit" cafeSlug={cafeSlug}>
            <Head title={`Payments — ${tenant.cafe?.name || 'Workspace'}`} />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header & Metrics */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-stone-200 pb-4">
                    <div>
                        <h1 className="text-2xl font-bold text-stone-900 tracking-tight">Payment Audit & History</h1>
                        <p className="text-xs text-stone-500 mt-0.5">Complete record of cash, UPI, card, and online payment transactions.</p>
                    </div>

                    <div className="bg-white border border-stone-200 rounded-2xl px-4 py-2 shadow-sm text-right">
                        <span className="text-[10px] text-stone-400 font-bold uppercase tracking-wider">Total Revenue Recorded</span>
                        <p className="text-lg font-extrabold text-amber-700">${totalCollected.toFixed(2)}</p>
                    </div>
                </div>

                {/* Payments Table */}
                <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
                    {payments.length === 0 ? (
                        <div className="text-center py-16 p-6">
                            <p className="text-3xl mb-2">💵</p>
                            <h3 className="font-bold text-stone-900 text-base">No payments recorded yet</h3>
                            <p className="text-xs text-stone-500 mt-1">Payment transactions will appear here as orders are paid.</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="bg-stone-50 border-b border-stone-200 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                                        <th className="py-3 px-4">Payment ID</th>
                                        <th className="py-3 px-4">Order #</th>
                                        <th className="py-3 px-4">Method</th>
                                        <th className="py-3 px-4">Amount</th>
                                        <th className="py-3 px-4">Status</th>
                                        <th className="py-3 px-4">Reference</th>
                                        <th className="py-3 px-4">Date</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-stone-100 text-xs">
                                    {payments.map((p) => (
                                        <tr key={p.id} className="hover:bg-stone-50/80 transition-colors">
                                            <td className="py-3.5 px-4 font-mono text-stone-500">#{p.id}</td>
                                            <td className="py-3.5 px-4 font-mono font-bold text-stone-900">
                                                {p.order_number || `Order #${p.order_id}`}
                                            </td>
                                            <td className="py-3.5 px-4 font-bold capitalize text-stone-800">
                                                {p.method}
                                            </td>
                                            <td className="py-3.5 px-4 font-extrabold text-stone-900">
                                                ${p.amount.toFixed(2)}
                                            </td>
                                            <td className="py-3.5 px-4">
                                                <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-emerald-100 text-emerald-900">
                                                    {p.status}
                                                </span>
                                            </td>
                                            <td className="py-3.5 px-4 font-mono text-stone-400 text-[11px]">
                                                {p.transaction_reference || '—'}
                                            </td>
                                            <td className="py-3.5 px-4 text-stone-500">
                                                {new Date(p.created_at).toLocaleString()}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
