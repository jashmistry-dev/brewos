import React, { useState, useRef } from 'react';
import { useForm, Head } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Button from '../../Components/Button';
import Input from '../../Components/Input';

export interface SettingsProps {
    cafe: {
        id: number;
        name: string;
        slug: string;
        email: string;
        phone: string | null;
        timezone: string | null;
        currency: string | null;
        tax_rate: number | null;
        logo_url: string | null;
        status: string;
    };
}

export default function Settings({ cafe }: SettingsProps) {
    const { data, setData, post, processing, errors } = useForm({
        _method: 'PUT',
        name: cafe.name || '',
        email: cafe.email || '',
        phone: cafe.phone || '',
        timezone: cafe.timezone || 'Asia/Kolkata',
        currency: cafe.currency || 'INR',
        tax_rate: cafe.tax_rate ?? 5.0,
        logo_url: cafe.logo_url || '',
        logo_base64: '',
        remove_logo: false,
    });

    const [previewUrl, setPreviewUrl] = useState<string | null>(cafe.logo_url || null);
    const [zoom, setZoom] = useState(1);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        if (file.size > 5 * 1024 * 1024) {
            alert('File size exceeds 5MB limit. Please choose a smaller image.');
            return;
        }

        const reader = new FileReader();
        reader.onload = () => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                const size = Math.min(img.width, img.height);
                canvas.width = 300;
                canvas.height = 300;

                if (ctx) {
                    const sx = (img.width - size) / 2;
                    const sy = (img.height - size) / 2;
                    ctx.drawImage(img, sx, sy, size, size, 0, 0, 300, 300);
                    const croppedBase64 = canvas.toDataURL('image/png');
                    setPreviewUrl(croppedBase64);
                    setData((prev) => ({
                        ...prev,
                        logo_base64: croppedBase64,
                        remove_logo: false,
                    }));
                }
            };
            img.src = reader.result as string;
        };
        reader.readAsDataURL(file);
    };

    const handleRemoveLogo = () => {
        setPreviewUrl(null);
        setData((prev) => ({
            ...prev,
            logo_url: '',
            logo_base64: '',
            remove_logo: true,
        }));
        if (fileInputRef.current) fileInputRef.current.value = '';
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/cafes/${cafe.slug}/settings`);
    };

    return (
        <AppLayout title="Cafe Settings & Configuration">
            <Head title="Cafe Settings" />

            <div className="max-w-3xl bg-white rounded-xl border border-stone-200 p-8 shadow-sm">
                <div className="mb-6 pb-4 border-b border-stone-100">
                    <h2 className="text-lg font-bold text-stone-900">General Business Settings</h2>
                    <p className="text-xs text-stone-500 mt-1">
                        Configure business profile details, brand logo, timezone, currency, and default invoice tax rates.
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Cafe Logo Branding Card */}
                    <div className="bg-stone-50 border border-stone-200 rounded-xl p-5">
                        <label className="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-3">
                            Cafe Logo & Brand Mark
                        </label>
                        <div className="flex flex-col sm:flex-row items-center gap-6">
                            <div className="relative w-24 h-24 bg-white border border-stone-300 rounded-2xl flex items-center justify-center overflow-hidden shadow-sm shrink-0">
                                {previewUrl ? (
                                    <img
                                        src={previewUrl}
                                        alt="Cafe Logo Preview"
                                        className="w-full h-full object-contain p-2"
                                        style={{ transform: `scale(${zoom})` }}
                                    />
                                ) : (
                                    <span className="text-3xl text-stone-400">☕</span>
                                )}
                            </div>

                            <div className="flex-1 space-y-3 w-full">
                                <div className="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        onClick={() => fileInputRef.current?.click()}
                                        className="px-3.5 py-1.5 bg-stone-900 text-white text-xs font-bold rounded-lg hover:bg-stone-800 transition"
                                    >
                                        📷 Upload & Crop Image
                                    </button>

                                    {previewUrl && (
                                        <button
                                            type="button"
                                            onClick={handleRemoveLogo}
                                            className="px-3.5 py-1.5 bg-rose-50 text-rose-600 border border-rose-200 text-xs font-bold rounded-lg hover:bg-rose-100 transition"
                                        >
                                            🗑️ Remove Logo
                                        </button>
                                    )}
                                </div>
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                    onChange={handleFileChange}
                                    className="hidden"
                                />

                                {previewUrl && (
                                    <div className="flex items-center gap-3 pt-1">
                                        <span className="text-[11px] font-semibold text-stone-500">Preview Zoom:</span>
                                        <input
                                            type="range"
                                            min="0.8"
                                            max="1.5"
                                            step="0.05"
                                            value={zoom}
                                            onChange={(e) => setZoom(parseFloat(e.target.value))}
                                            className="w-32 accent-amber-500"
                                        />
                                    </div>
                                )}

                                <p className="text-[11px] text-stone-500">
                                    Supports PNG, JPG, WEBP, or SVG up to 5MB. Automatic 1:1 brand crop preview included.
                                </p>
                            </div>
                        </div>

                        <div className="mt-4 pt-3 border-t border-stone-200">
                            <Input
                                label="Or Enter External Logo Image URL"
                                type="url"
                                value={data.logo_url}
                                onChange={(e) => {
                                    const val = e.target.value;
                                    setData('logo_url', val);
                                    if (val && !data.logo_base64) setPreviewUrl(val);
                                }}
                                error={errors.logo_url}
                                placeholder="https://example.com/logo.png"
                                helperText="Optional direct HTTPS image URL"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input
                            label="Cafe Business Name"
                            required
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            error={errors.name}
                        />

                        <Input
                            label="Business Email"
                            type="email"
                            required
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            error={errors.email}
                        />
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input
                            label="Contact Phone Number"
                            type="tel"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            error={errors.phone}
                        />

                        <Input
                            label="Timezone"
                            value={data.timezone}
                            onChange={(e) => setData('timezone', e.target.value)}
                            error={errors.timezone}
                            helperText="e.g. Asia/Kolkata or UTC"
                        />
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input
                            label="Currency Code"
                            value={data.currency}
                            onChange={(e) => setData('currency', e.target.value)}
                            error={errors.currency}
                            placeholder="INR"
                        />

                        <Input
                            label="Default Tax Rate (%)"
                            type="number"
                            step="0.01"
                            value={data.tax_rate}
                            onChange={(e) => setData('tax_rate', parseFloat(e.target.value) || 0)}
                            error={errors.tax_rate}
                            placeholder="5.00"
                        />
                    </div>

                    <div className="pt-4 flex justify-end">
                        <Button type="submit" variant="primary" isLoading={processing}>
                            Save Settings Changes
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}