import React from 'react';
import { useForm, Head, Link } from '@inertiajs/react';
import Button from '../../Components/Button';
import Input from '../../Components/Input';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <div className="min-h-screen bg-stone-900 flex items-center justify-center p-4">
            <Head title="Log In" />

            <div className="w-full max-w-md bg-white rounded-2xl shadow-2xl p-8 border border-stone-100">
                <div className="text-center mb-8">
                    <h1 className="text-3xl font-extrabold text-amber-600 tracking-tight">☕ BrewOS</h1>
                    <p className="mt-2 text-sm text-stone-600 font-medium">
                        Log in to manage your cafe operations
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-5">
                    <Input
                        label="Email Address"
                        type="email"
                        required
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        error={errors.email}
                        placeholder="owner@mycafe.com"
                        autoComplete="email"
                    />

                    <Input
                        label="Password"
                        type="password"
                        required
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        error={errors.password}
                        placeholder="••••••••"
                        autoComplete="current-password"
                    />

                    <Button
                        type="submit"
                        variant="primary"
                        size="lg"
                        className="w-full mt-2"
                        isLoading={processing}
                    >
                        Log In
                    </Button>
                </form>

                <div className="mt-6 pt-6 border-t border-stone-100 text-center">
                    <p className="text-sm text-stone-600">
                        Want to start a new cafe?{' '}
                        <Link
                            href="/register-cafe"
                            className="font-semibold text-amber-600 hover:text-amber-700 underline underline-offset-2"
                        >
                            Register your Cafe
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    );
}
