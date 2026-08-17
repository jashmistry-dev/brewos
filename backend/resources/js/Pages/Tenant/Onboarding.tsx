import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router, useForm } from '@inertiajs/react';
import Button from '@/Components/Button';
import Input from '@/Components/Input';

interface CafeInfo {
    id: number;
    name: string;
    slug: string;
    email: string;
    phone: string | null;
    logo_url: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    postal_code: string | null;
    country: string;
    tax_number: string | null;
    tax_rate: number;
    timezone: string;
    currency: string;
    onboarded_at: string | null;
}

interface Props {
    cafe: CafeInfo;
}

export default function Onboarding({ cafe }: Props) {
    const [step, setStep] = useState(1);
    const [logoPreview, setLogoPreview] = useState<string | null>(cafe.logo_url);

    const { data, setData, post, processing, errors } = useForm({
        address: cafe.address || '',
        city: cafe.city || '',
        state: cafe.state || '',
        postal_code: cafe.postal_code || '',
        country: cafe.country || 'US',
        tax_number: cafe.tax_number || '',
        tax_rate: cafe.tax_rate !== undefined ? String(cafe.tax_rate) : '0',
        timezone: cafe.timezone || 'UTC',
        currency: cafe.currency || 'USD',
        logo: null as File | null,
    });

    const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            const file = e.target.files[0];
            setData('logo', file);
            setLogoPreview(URL.createObjectURL(file));
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/cafes/${cafe.slug}/onboarding`);
    };

    return (
        <AppLayout title="Cafe Setup & Onboarding" cafeSlug={cafe.slug}>
            <Head title={`Setup Workspace — ${cafe.name}`} />

            <div className="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
                {/* Onboarding Header Banner */}
                <div className="bg-stone-900 border border-stone-800 rounded-2xl p-8 shadow-xl text-center mb-8">
                    <span className="inline-block p-3 bg-amber-500/10 text-amber-500 rounded-full mb-3 text-2xl">
                        ☕
                    </span>
                    <h1 className="text-3xl font-extrabold text-stone-100 tracking-tight">
                        Welcome to {cafe.name}!
                    </h1>
                    <p className="text-sm text-stone-400 mt-2 max-w-lg mx-auto">
                        Complete your quick cafe setup to unlock full POS, QR ordering, tax invoicing, and menu features.
                    </p>

                    {/* Progress Steps Indicator */}
                    <div className="flex items-center justify-center gap-4 mt-8">
                        <div
                            onClick={() => setStep(1)}
                            className={`cursor-pointer flex items-center gap-2 text-xs font-semibold px-4 py-2 rounded-full transition ${
                                step === 1
                                    ? 'bg-amber-500 text-stone-950 font-bold'
                                    : 'bg-stone-800 text-stone-400 hover:text-stone-200'
                            }`}
                        >
                            <span>1</span> Branding & Logo
                        </div>
                        <div className="w-8 h-0.5 bg-stone-800"></div>
                        <div
                            onClick={() => setStep(2)}
                            className={`cursor-pointer flex items-center gap-2 text-xs font-semibold px-4 py-2 rounded-full transition ${
                                step === 2
                                    ? 'bg-amber-500 text-stone-950 font-bold'
                                    : 'bg-stone-800 text-stone-400 hover:text-stone-200'
                            }`}
                        >
                            <span>2</span> Location & Tax
                        </div>
                        <div className="w-8 h-0.5 bg-stone-800"></div>
                        <div
                            onClick={() => setStep(3)}
                            className={`cursor-pointer flex items-center gap-2 text-xs font-semibold px-4 py-2 rounded-full transition ${
                                step === 3
                                    ? 'bg-amber-500 text-stone-950 font-bold'
                                    : 'bg-stone-800 text-stone-400 hover:text-stone-200'
                            }`}
                        >
                            <span>3</span> Review & Complete
                        </div>
                    </div>
                </div>

                {/* Form Card */}
                <form onSubmit={handleSubmit} className="bg-stone-900 border border-stone-800 rounded-2xl p-8 shadow-sm space-y-6">
                    {step === 1 && (
                        <div className="space-y-6">
                            <h2 className="text-xl font-bold text-stone-100 border-b border-stone-800 pb-3">
                                Step 1: Branding & Logo
                            </h2>

                            <div className="flex flex-col items-center justify-center p-6 border-2 border-dashed border-stone-700 rounded-xl bg-stone-950/50">
                                {logoPreview ? (
                                    <img
                                        src={logoPreview}
                                        alt="Logo Preview"
                                        className="w-24 h-24 object-contain rounded-lg border border-stone-700 mb-4 bg-stone-900 p-2"
                                    />
                                ) : (
                                    <div className="w-20 h-20 rounded-full bg-stone-800 flex items-center justify-center text-stone-500 mb-4 text-xl">
                                        📷
                                    </div>
                                )}

                                <label className="cursor-pointer bg-stone-800 hover:bg-stone-700 text-stone-200 px-4 py-2 rounded-lg text-sm font-semibold transition border border-stone-700">
                                    <span>{logoPreview ? 'Change Logo Image' : 'Upload Cafe Logo'}</span>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        onChange={handleLogoChange}
                                        className="hidden"
                                    />
                                </label>
                                <p className="text-xs text-stone-500 mt-2">Recommended: PNG, WEBP or JPG up to 2MB</p>
                                {errors.logo && <p className="text-xs text-rose-500 mt-1">{errors.logo}</p>}
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-2">
                                        Timezone
                                    </label>
                                    <select
                                        value={data.timezone}
                                        onChange={(e) => setData('timezone', e.target.value)}
                                        className="w-full bg-stone-950 text-stone-200 border border-stone-800 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                    >
                                        <option value="UTC">UTC (Coordinated Universal Time)</option>
                                        <option value="America/New_York">Eastern Time (US & Canada)</option>
                                        <option value="America/Chicago">Central Time (US & Canada)</option>
                                        <option value="America/Los_Angeles">Pacific Time (US & Canada)</option>
                                        <option value="Europe/London">London (GMT/BST)</option>
                                        <option value="Asia/Kolkata">India (IST - UTC+5:30)</option>
                                        <option value="Asia/Tokyo">Tokyo (JST - UTC+9:00)</option>
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-stone-300 uppercase tracking-wider mb-2">
                                        Currency Symbol
                                    </label>
                                    <select
                                        value={data.currency}
                                        onChange={(e) => setData('currency', e.target.value)}
                                        className="w-full bg-stone-950 text-stone-200 border border-stone-800 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                    >
                                        <option value="USD">USD ($)</option>
                                        <option value="EUR">EUR (€)</option>
                                        <option value="GBP">GBP (£)</option>
                                        <option value="INR">INR (₹)</option>
                                        <option value="CAD">CAD ($)</option>
                                        <option value="AUD">AUD ($)</option>
                                    </select>
                                </div>
                            </div>

                            <div className="flex justify-end pt-4">
                                <Button type="button" variant="primary" onClick={() => setStep(2)}>
                                    Next: Location & Tax &rarr;
                                </Button>
                            </div>
                        </div>
                    )}

                    {step === 2 && (
                        <div className="space-y-6">
                            <h2 className="text-xl font-bold text-stone-100 border-b border-stone-800 pb-3">
                                Step 2: Location & Tax Setup
                            </h2>

                            <div>
                                <Input
                                    label="Street Address"
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    error={errors.address}
                                    placeholder="123 Coffee Bean St"
                                />
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <Input
                                    label="City"
                                    value={data.city}
                                    onChange={(e) => setData('city', e.target.value)}
                                    error={errors.city}
                                    placeholder="San Francisco"
                                />
                                <Input
                                    label="State / Province"
                                    value={data.state}
                                    onChange={(e) => setData('state', e.target.value)}
                                    error={errors.state}
                                    placeholder="CA"
                                />
                                <Input
                                    label="Postal Code"
                                    value={data.postal_code}
                                    onChange={(e) => setData('postal_code', e.target.value)}
                                    error={errors.postal_code}
                                    placeholder="94103"
                                />
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <Input
                                    label="Tax Registration Number (GST / VAT / EIN)"
                                    value={data.tax_number}
                                    onChange={(e) => setData('tax_number', e.target.value)}
                                    error={errors.tax_number}
                                    placeholder="GSTIN12345678"
                                />

                                <Input
                                    label="Default Tax Rate (%)"
                                    type="number"
                                    step="0.01"
                                    value={data.tax_rate}
                                    onChange={(e) => setData('tax_rate', e.target.value)}
                                    error={errors.tax_rate}
                                    placeholder="5.00"
                                />
                            </div>

                            <div className="flex justify-between pt-4">
                                <Button type="button" variant="secondary" onClick={() => setStep(1)}>
                                    &larr; Back
                                </Button>
                                <Button type="button" variant="primary" onClick={() => setStep(3)}>
                                    Next: Review & Complete &rarr;
                                </Button>
                            </div>
                        </div>
                    )}

                    {step === 3 && (
                        <div className="space-y-6">
                            <h2 className="text-xl font-bold text-stone-100 border-b border-stone-800 pb-3">
                                Step 3: Review & Activate Workspace
                            </h2>

                            <div className="bg-stone-950 p-6 rounded-xl border border-stone-800 space-y-3 text-sm text-stone-300">
                                <div className="flex justify-between">
                                    <span className="text-stone-400">Cafe Name:</span>
                                    <span className="font-semibold text-stone-100">{cafe.name}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-stone-400">Address:</span>
                                    <span>{data.address ? `${data.address}, ${data.city || ''} ${data.state || ''}` : 'Not provided'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-stone-400">Timezone / Currency:</span>
                                    <span>{data.timezone} ({data.currency})</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-stone-400">Tax Number / Rate:</span>
                                    <span>{data.tax_number || 'N/A'} ({data.tax_rate}%)</span>
                                </div>
                                <div className="flex justify-between border-t border-stone-800 pt-3">
                                    <span className="text-stone-400">Subscription Status:</span>
                                    <span className="text-emerald-400 font-bold uppercase">14-Day Free Trial Active</span>
                                </div>
                            </div>

                            <div className="flex justify-between pt-4">
                                <Button type="button" variant="secondary" onClick={() => setStep(2)}>
                                    &larr; Back
                                </Button>
                                <Button type="submit" variant="primary" isLoading={processing}>
                                    Complete Setup & Go to Dashboard &rarr;
                                </Button>
                            </div>
                        </div>
                    )}
                </form>
            </div>
        </AppLayout>
    );
}
