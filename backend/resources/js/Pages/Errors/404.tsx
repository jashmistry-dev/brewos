import React from 'react';
import { Head, Link } from '@inertiajs/react';
import Button from '../../Components/Button';

export default function Error404() {
    return (
        <div className="min-h-screen bg-stone-900 flex items-center justify-center p-4 text-center">
            <Head title="404 — Page Not Found" />
            <div className="max-w-md bg-white rounded-2xl p-8 shadow-2xl border border-stone-100">
                <div className="text-5xl mb-4">🔍</div>
                <h1 className="text-3xl font-extrabold text-stone-900">404 — Not Found</h1>
                <p className="mt-2 text-sm text-stone-600">
                    The requested page or resource could not be found.
                </p>
                <div className="mt-6 flex justify-center gap-3">
                    <Link href="/">
                        <Button variant="primary">Return Home</Button>
                    </Link>
                </div>
            </div>
        </div>
    );
}
