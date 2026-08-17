import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, usePage } from '@inertiajs/react';

interface InvoiceData {
    id: number;
    order_id: number;
    order_number: string | null;
    invoice_number: string;
    subtotal: number;
    tax: number;
    discount: number;
    total: number;
    status: string;
    issued_at: string | null;
    created_at: string;
}

interface Props {
    invoices: InvoiceData[];
}

export default function Invoices({ invoices }: Props) {
    const { tenant } = usePage<{ tenant: { cafe?: { slug: string; name: string } } }>().props;
    const cafeSlug = tenant.cafe?.slug || '';

    return (
        <AppLayout title="Invoices Management" cafeSlug={cafeSlug}>
            <Head title={`Invoices — ${tenant.cafe?.name || 'Workspace'}`} />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-stone-200 pb-4">
                    <div>
                        <h1 className="text-2xl font-bold text-stone-900 tracking-tight">Customer Invoices</h1>
                        <p className="text-xs text-stone-500 mt-0.5">Generate, view, and print compliance tax invoices for customer orders.</p>
                    </div>
                </div>

                {/* Invoices Table */}
                <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
                    {invoices.length === 0 ? (
                        <div className="text-center py-16 p-6">
                            <p className="text-3xl mb-2">🧾</p>
                            <h3 className="font-bold text-stone-900 text-base">No invoices created yet</h3>
                            <p className="text-xs text-stone-500 mt-1">Tax invoices generated for orders will appear here.</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="bg-stone-50 border-b border-stone-200 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                                        <th className="py-3 px-4">Invoice #</th>
                                        <th className="py-3 px-4">Order #</th>
                                        <th className="py-3 px-4">Subtotal</th>
                                        <th className="py-3 px-4">Tax</th>
                                        <th className="py-3 px-4">Total</th>
                                        <th className="py-3 px-4">Status</th>
                                        <th className="py-3 px-4">Issued Date</th>
                                        <th className="py-3 px-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-stone-100 text-xs">
                                    {invoices.map((inv) => (
                                        <tr key={inv.id} className="hover:bg-stone-50/80 transition-colors">
                                            <td className="py-3.5 px-4 font-mono font-bold text-stone-900">
                                                {inv.invoice_number}
                                            </td>
                                            <td className="py-3.5 px-4 font-mono text-stone-600">
                                                {inv.order_number || `#${inv.order_id}`}
                                            </td>
                                            <td className="py-3.5 px-4 font-medium text-stone-700">${inv.subtotal.toFixed(2)}</td>
                                            <td className="py-3.5 px-4 font-medium text-stone-700">${inv.tax.toFixed(2)}</td>
                                            <td className="py-3.5 px-4 font-extrabold text-stone-900">${inv.total.toFixed(2)}</td>
                                            <td className="py-3.5 px-4">
                                                <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-stone-100 text-stone-800">
                                                    {inv.status}
                                                </span>
                                            </td>
                                            <td className="py-3.5 px-4 text-stone-500">
                                                {inv.issued_at ? new Date(inv.issued_at).toLocaleDateString() : '—'}
                                            </td>
                                            <td className="py-3.5 px-4 text-right">
                                                <a
                                                    href={`/cafes/${cafeSlug}/invoices/${inv.id}/download`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-block bg-stone-900 hover:bg-stone-800 text-amber-400 font-bold text-xs px-3 py-1.5 rounded-lg transition-colors"
                                                >
                                                    🖨️ View / Print
                                                </a>
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
