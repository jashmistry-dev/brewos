import React from 'react';
import { useForm, Head, Link } from '@inertiajs/react';
import Button from '../../Components/Button';
import Input from '../../Components/Input';

export default function RegisterCafe() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        slug: '',
        email: '',
        phone: '',
        owner_name: '',
        owner_email: '',
        password: '',
        password_confirmation: '',
    });

    const handleNameChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const nameVal = e.target.value;
        const slugVal = nameVal
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');

        setData((prev) => ({
            ...prev,
            name: nameVal,
            slug: slugVal,
        }));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/register-cafe');
    };

    return (
        <div className="min-h-screen bg-stone-900 flex items-center justify-center p-4 py-12">
            <Head title="Register Your Cafe" />

            <div className="w-full max-w-lg bg-white rounded-2xl shadow-2xl p-8 border border-stone-100">
                <div className="text-center mb-8">
                    <h1 className="text-3xl font-extrabold text-amber-600 tracking-tight">☕ BrewOS</h1>
                    <p className="mt-2 text-sm text-stone-600 font-medium">
                        Create your multi-tenant cafe workspace in seconds
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input
                            label="Cafe Name"
                            required
                            value={data.name}
                            onChange={handleNameChange}
                            error={errors.name}
                            placeholder="Artisan Roast"
                        />

                        <Input
                            label="URL Slug"
                            required
                            value={data.slug}
                            onChange={(e) => setData('slug', e.target.value)}
                            error={errors.slug}
                            placeholder="artisan-roast"
                            helperText="Used in your custom cafe URL"
                        />
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input
                            label="Cafe Business Email"
                            type="email"
                            required
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            error={errors.email}
                            placeholder="contact@artisanroast.com"
                        />

                        <Input
                            label="Cafe Phone (Optional)"
                            type="tel"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            error={errors.phone}
                            placeholder="+1 555-0192"
                        />
                    </div>

                    <hr className="my-4 border-stone-100" />

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input
                            label="Owner Full Name"
                            required
                            value={data.owner_name}
                            onChange={(e) => setData('owner_name', e.target.value)}
                            error={errors.owner_name}
                            placeholder="Jane Doe"
                        />

                        <Input
                            label="Owner Login Email"
                            type="email"
                            required
                            value={data.owner_email}
                            onChange={(e) => setData('owner_email', e.target.value)}
                            error={errors.owner_email}
                            placeholder="jane@artisanroast.com"
                        />
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input
                            label="Password"
                            type="password"
                            required
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            error={errors.password}
                            placeholder="••••••••"
                        />

                        <Input
                            label="Confirm Password"
                            type="password"
                            required
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            error={errors.password_confirmation}
                            placeholder="••••••••"
                        />
                    </div>

                    <Button
                        type="submit"
                        variant="primary"
                        size="lg"
                        className="w-full mt-4"
                        isLoading={processing}
                    >
                        Register Cafe & Launch Workspace
                    </Button>
                </form>

                <div className="mt-6 pt-6 border-t border-stone-100 text-center">
                    <p className="text-sm text-stone-600">
                        Already have a cafe?{' '}
                        <Link
                            href="/login"
                            className="font-semibold text-amber-600 hover:text-amber-700 underline underline-offset-2"
                        >
                            Log In
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    );
}
