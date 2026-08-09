import React from 'react';
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
    const { data, setData, put, processing, errors } = useForm({
        name: cafe.name || '',
        email: cafe.email || '',
        phone: cafe.phone || '',
        timezone: cafe.timezone || 'Asia/Kolkata',
        currency: cafe.currency || 'INR',
        tax_rate: cafe.tax_rate ?? 5.0,
        logo_url: cafe.logo_url || '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/cafes/${cafe.slug}/settings`);
    };

    return (
        <AppLayout title="Cafe Settings & Configuration">
            <Head title="Cafe Settings" />

            <div className="max-w-3xl bg-white rounded-xl border border-stone-200 p-8 shadow-sm">
                <div className="mb-6 pb-4 border-b border-stone-100">
                    <h2 className="text-lg font-bold text-stone-900">General Business Settings</h2>
                    <p className="text-xs text-stone-500 mt-1">
                        Configure business profile details, timezone, currency, and default invoice tax rates.
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-5">
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

                    <Input
                        label="Logo Image URL"
                        type="url"
                        value={data.logo_url}
                        onChange={(e) => setData('logo_url', e.target.value)}
                        error={errors.logo_url}
                        placeholder="https://example.com/logo.png"
                    />

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
