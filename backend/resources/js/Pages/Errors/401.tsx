import React from 'react';
import { Head, Link } from '@inertiajs/react';
import Button from '../../Components/Button';

export default function Error401() {
    return (
        <div className="min-h-screen bg-stone-900 flex items-center justify-center p-4 text-center">
            <Head title="401 — Unauthenticated" />
            <div className="max-w-md bg-white rounded-2xl p-8 shadow-2xl border border-stone-100">
                <div className="text-5xl mb-4">🔐</div>
                <h1 className="text-3xl font-extrabold text-stone-900">401 — Unauthenticated</h1>
                <p className="mt-2 text-sm text-stone-600">
                    You must be logged in to access this cafe workspace or resource.
                </p>
                <div className="mt-6 flex justify-center gap-3">
                    <Link href="/login">
                        <Button variant="primary">Log In</Button>
                    </Link>
                </div>
            </div>
        </div>
    );
}
