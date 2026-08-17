import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, usePage, router } from '@inertiajs/react';
import Button from '@/Components/Button';
import QRCodeSVG from '@/Components/QRCodeSVG';
import QRCode from 'qrcode';

interface Branch {
    id: number;
    name: string;
}

interface TableData {
    id: number;
    branch_id: number;
    branch_name?: string;
    name: string;
    capacity: number;
    status: string;
    qr_token: string;
}

interface Props {
    tables: TableData[];
    branches: Branch[];
}

export default function Tables({ tables, branches }: Props) {
    const { tenant } = usePage<{ tenant: { cafe?: { slug: string; name: string } } }>().props;
    const cafeSlug = tenant.cafe?.slug || '';
    const [selectedBranchId, setSelectedBranchId] = useState<number | 'all'>('all');
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [name, setName] = useState('');
    const [capacity, setCapacity] = useState('4');
    const [branchId, setBranchId] = useState<number>(branches[0]?.id || 0);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [qrPreviewTable, setQrPreviewTable] = useState<TableData | null>(null);

    const filteredTables = tables.filter((t) => {
        if (selectedBranchId === 'all') return true;
        return t.branch_id === selectedBranchId;
    });

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        if (!name || isSubmitting) return;

        setIsSubmitting(true);
        router.post(
            `/cafes/${cafeSlug}/tables`,
            { name, capacity: parseInt(capacity, 10), branch_id: branchId },
            {
                onSuccess: () => {
                    setShowCreateModal(false);
                    setName('');
                    setCapacity('4');
                    setIsSubmitting(false);
                },
                onError: () => setIsSubmitting(false),
            }
        );
    };

    const handleRegenerateQr = (tableId: number) => {
        if (confirm('Regenerating QR will invalidate existing physical QR prints for this table. Continue?')) {
            router.post(`/cafes/${cafeSlug}/tables/${tableId}/regenerate-qr`, {}, { preserveScroll: true });
        }
    };

    const handleDelete = (tableId: number) => {
        if (confirm('Are you sure you want to delete this table?')) {
            router.delete(`/cafes/${cafeSlug}/tables/${tableId}`, { preserveScroll: true });
        }
    };

    const handlePrintTableSticker = async (table: TableData) => {
        const qrUrl = `${window.location.origin}/order/c/${cafeSlug}/t/${table.qr_token}`;
        let qrDataUrl = '';
        try {
            qrDataUrl = await QRCode.toDataURL(qrUrl, { width: 400, margin: 2 });
        } catch (e) {
            console.error('Failed to generate printable QR code:', e);
        }

        const w = window.open('', '_blank');
        if (w) {
            w.document.write(`
                <html>
                    <head>
                        <title>Print QR Sticker — ${table.name}</title>
                        <style>
                            body { font-family: system-ui, -apple-system, sans-serif; text-align: center; padding: 40px; background: #fafaf9; }
                            .sticker { border: 3px solid #1c1917; border-radius: 24px; padding: 32px; background: #ffffff; display: inline-block; max-width: 320px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
                            h1 { font-size: 26px; font-weight: 900; margin: 8px 0 4px; color: #1c1917; }
                            p.sub { color: #d97706; text-transform: uppercase; font-weight: 800; font-size: 12px; letter-spacing: 1px; margin: 0; }
                            p.desc { color: #78716c; font-size: 13px; margin: 4px 0 16px; font-weight: 600; }
                            .qr-wrap { display: flex; justify-content: center; margin: 16px 0; }
                            img.qr-img { width: 220px; height: 220px; border-radius: 12px; border: 1px solid #e7e5e4; }
                            .footer { font-size: 10px; font-family: monospace; color: #a8a29e; margin-top: 16px; border-t: 1px solid #f5f5f4; pt: 8px; }
                        </style>
                    </head>
                    <body onload="window.print()">
                        <div class="sticker">
                            <p class="sub">${tenant.cafe?.name || 'Cafe'}</p>
                            <h1>${table.name}</h1>
                            <p class="desc">Scan with phone to view menu & order</p>
                            <div class="qr-wrap">
                                <img src="${qrDataUrl}" class="qr-img" alt="QR Code" />
                            </div>
                            <div class="footer">Token: ${table.qr_token} • BrewOS</div>
                        </div>
                    </body>
                </html>
            `);
            w.document.close();
        }
    };

    return (
        <AppLayout title="Restaurant Tables & QR Code Engine" cafeSlug={cafeSlug}>
            <Head title={`Tables & QR — ${tenant.cafe?.name || 'Workspace'}`} />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-stone-200 pb-4">
                    <div>
                        <h1 className="text-2xl font-bold text-stone-900 tracking-tight">Tables & QR Codes</h1>
                        <p className="text-xs text-stone-500 mt-0.5">Manage physical dining tables, generate customer QR ordering tokens, and preview customer menus.</p>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button variant="primary" onClick={() => setShowCreateModal(true)}>
                            + Add New Table
                        </Button>
                    </div>
                </div>

                {/* Branch Tabs */}
                {branches.length > 1 && (
                    <div className="flex items-center gap-2 overflow-x-auto py-1">
                        <button
                            onClick={() => setSelectedBranchId('all')}
                            className={`px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors ${
                                selectedBranchId === 'all'
                                    ? 'bg-stone-900 text-white'
                                    : 'bg-white text-stone-600 border border-stone-200 hover:bg-stone-100'
                            }`}
                        >
                            All Branches ({tables.length})
                        </button>
                        {branches.map((b) => (
                            <button
                                key={b.id}
                                onClick={() => setSelectedBranchId(b.id)}
                                className={`px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors ${
                                    selectedBranchId === b.id
                                        ? 'bg-stone-900 text-white'
                                        : 'bg-white text-stone-600 border border-stone-200 hover:bg-stone-100'
                                }`}
                            >
                                {b.name}
                            </button>
                        ))}
                    </div>
                )}

                {/* Tables Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    {filteredTables.length === 0 ? (
                        <div className="col-span-full text-center py-16 bg-white rounded-2xl border border-stone-200 p-6">
                            <p className="text-3xl mb-2">🪑</p>
                            <h3 className="font-bold text-stone-900 text-base">No tables created yet</h3>
                            <p className="text-xs text-stone-500 mt-1">Add a new table to generate QR tokens for customer dine-in ordering.</p>
                        </div>
                    ) : (
                        filteredTables.map((t) => {
                            const qrUrl = `${window.location.origin}/order/c/${cafeSlug}/t/${t.qr_token}`;

                            return (
                                <div key={t.id} className="bg-white border border-stone-200 rounded-3xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between space-y-4">
                                    <div className="space-y-3 text-center">
                                        <div className="flex items-center justify-between">
                                            <h3 className="font-extrabold text-base text-stone-900">{t.name}</h3>
                                            <span className="bg-amber-100 text-amber-900 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                                                {t.capacity} Seats
                                            </span>
                                        </div>

                                        {/* VISIBLE QR CODE DISPLAY */}
                                        <div className="bg-stone-50 border border-stone-200 rounded-2xl p-4 flex flex-col items-center space-y-2 group relative">
                                            <QRCodeSVG
                                                value={qrUrl}
                                                size={140}
                                                className="rounded-xl bg-white p-2 border border-stone-200 shadow-inner"
                                            />
                                            <p className="text-[11px] font-bold text-stone-600">Scan to Order Dine-In</p>
                                            <p className="text-[9px] text-stone-400 font-mono truncate max-w-[160px]">{t.qr_token}</p>
                                        </div>
                                    </div>

                                    {/* Action Tools */}
                                    <div className="space-y-2 pt-1 border-t border-stone-100">
                                        <div className="grid grid-cols-2 gap-1.5">
                                            <button
                                                onClick={() => setQrPreviewTable(t)}
                                                className="bg-amber-500 hover:bg-amber-600 text-stone-950 font-bold text-xs py-2 rounded-xl transition-colors shadow-sm"
                                            >
                                                🔍 View QR
                                            </button>
                                            <a
                                                href={`/order/c/${cafeSlug}/t/${t.qr_token}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="block text-center bg-stone-900 hover:bg-stone-800 text-amber-400 font-bold text-xs py-2 rounded-xl transition-colors"
                                            >
                                                📲 Preview Menu
                                            </a>
                                        </div>

                                        <div className="grid grid-cols-3 gap-1 text-[10px]">
                                            <button
                                                onClick={() => {
                                                    navigator.clipboard.writeText(qrUrl);
                                                    alert(`Customer link copied for ${t.name}!`);
                                                }}
                                                className="bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold py-1.5 rounded-lg transition-colors"
                                            >
                                                📋 Copy
                                            </button>
                                            <button
                                                onClick={() => handleRegenerateQr(t.id)}
                                                className="bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold py-1.5 rounded-lg transition-colors"
                                            >
                                                🔄 Reset
                                            </button>
                                            <button
                                                onClick={() => handleDelete(t.id)}
                                                className="bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold py-1.5 rounded-lg transition-colors"
                                            >
                                                🗑️ Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>
            </div>

            {/* Create Table Modal */}
            {showCreateModal && (
                <div className="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4 border border-stone-200">
                        <div className="flex justify-between items-center border-b border-stone-100 pb-3">
                            <h3 className="font-bold text-base text-stone-900">Add New Restaurant Table</h3>
                            <button onClick={() => setShowCreateModal(false)} className="text-stone-400 hover:text-stone-600">✕</button>
                        </div>

                        <form onSubmit={handleCreate} className="space-y-4">
                            <div>
                                <label className="block text-xs font-bold text-stone-700 mb-1">Table Name / Number</label>
                                <input
                                    type="text"
                                    placeholder="e.g. Table 01, Terrace 04, VIP Booth A"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    required
                                    className="w-full bg-stone-50 border border-stone-300 rounded-xl px-3 py-2 text-xs text-stone-900 focus:outline-none focus:border-amber-500"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-stone-700 mb-1">Seating Capacity</label>
                                <input
                                    type="number"
                                    min="1"
                                    max="50"
                                    value={capacity}
                                    onChange={(e) => setCapacity(e.target.value)}
                                    required
                                    className="w-full bg-stone-50 border border-stone-300 rounded-xl px-3 py-2 text-xs text-stone-900 focus:outline-none focus:border-amber-500"
                                />
                            </div>

                            {branches.length > 0 && (
                                <div>
                                    <label className="block text-xs font-bold text-stone-700 mb-1">Assigned Branch</label>
                                    <select
                                        value={branchId}
                                        onChange={(e) => setBranchId(parseInt(e.target.value, 10))}
                                        className="w-full bg-stone-50 border border-stone-300 rounded-xl px-3 py-2 text-xs text-stone-900 focus:outline-none focus:border-amber-500"
                                    >
                                        {branches.map((b) => (
                                            <option key={b.id} value={b.id}>{b.name}</option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            <div className="flex justify-end gap-2 pt-2 border-t border-stone-100">
                                <Button type="button" variant="secondary" onClick={() => setShowCreateModal(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" variant="primary" disabled={isSubmitting} isLoading={isSubmitting}>
                                    Create Table & QR &rarr;
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* View & Print QR Modal */}
            {qrPreviewTable && (
                <div className="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-white rounded-3xl p-6 max-w-sm w-full shadow-2xl space-y-5 border border-stone-200 text-center">
                        <div className="flex justify-between items-center border-b border-stone-100 pb-3">
                            <h3 className="font-extrabold text-sm text-stone-900">Table QR Sticker Preview</h3>
                            <button onClick={() => setQrPreviewTable(null)} className="text-stone-400 hover:text-stone-600">✕</button>
                        </div>

                        <div className="bg-stone-50 border border-stone-200 rounded-2xl p-6 space-y-4">
                            <div className="space-y-1">
                                <p className="text-xs uppercase tracking-wider font-extrabold text-amber-600">{tenant.cafe?.name || 'Cafe'}</p>
                                <h4 className="text-xl font-black text-stone-900">{qrPreviewTable.name}</h4>
                                <p className="text-xs text-stone-500">Scan to Order Dine-In</p>
                            </div>

                            <QRCodeSVG
                                value={`${window.location.origin}/order/c/${cafeSlug}/t/${qrPreviewTable.qr_token}`}
                                size={200}
                                className="mx-auto rounded-xl bg-white p-2 border border-stone-300 shadow-md"
                            />

                            <p className="text-[10px] text-stone-400 font-mono">Token: {qrPreviewTable.qr_token}</p>
                        </div>

                        <div className="grid grid-cols-2 gap-2">
                            <Button
                                variant="secondary"
                                onClick={() => {
                                    const w = window.open('', '_blank');
                                    if (w) {
                                        const qrUrl = `${window.location.origin}/order/c/${cafeSlug}/t/${qrPreviewTable.qr_token}`;
                                        const imgUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(qrUrl)}`;
                                        w.document.write(`
                                            <html>
                                                <head>
                                                    <title>Print QR — ${qrPreviewTable.name}</title>
                                                    <style>
                                                        body { font-family: sans-serif; text-align: center; padding: 40px; }
                                                        .card { border: 3px solid #1c1917; border-radius: 24px; padding: 32px; display: inline-block; max-width: 320px; }
                                                        h1 { font-size: 24px; margin: 8px 0; }
                                                        p { color: #78716c; font-size: 14px; margin: 4px 0; }
                                                        img { width: 220px; height: 220px; margin: 16px 0; }
                                                    </style>
                                                </head>
                                                <body onload="window.print()">
                                                    <div class="card">
                                                        <p style="text-transform: uppercase; font-weight: bold; color: #d97706;">${tenant.cafe?.name || 'Cafe'}</p>
                                                        <h1>${qrPreviewTable.name}</h1>
                                                        <p>Scan to View Menu & Order</p>
                                                        <img src="${imgUrl}" />
                                                        <p style="font-size: 10px; font-family: monospace;">Powered by BrewOS</p>
                                                    </div>
                                                </body>
                                            </html>
                                        `);
                                        w.document.close();
                                    }
                                }}
                            >
                                🖨️ Print Sticker
                            </Button>
                            <Button
                                variant="primary"
                                onClick={() => {
                                    const qrUrl = `${window.location.origin}/order/c/${cafeSlug}/t/${qrPreviewTable.qr_token}`;
                                    navigator.clipboard.writeText(qrUrl);
                                    alert('Customer ordering link copied!');
                                }}
                            >
                                📋 Copy Link
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
