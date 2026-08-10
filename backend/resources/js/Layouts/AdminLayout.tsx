import React, { ReactNode } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { PageProps } from '../types';
import Toast from '../Components/Toast';

export interface AdminLayoutProps {
    children: ReactNode;
    title?: string;
}

export const AdminLayout: React.FC<AdminLayoutProps> = ({ children, title }) => {
    const { auth } = usePage<PageProps>().props;

    const handleLogout = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/logout');
    };

    const navigation = [
        { name: 'Dashboard', href: '/admin/dashboard', pattern: '/admin/dashboard' },
        { name: 'Cafes', href: '/admin/cafes', pattern: '/admin/cafes*' },
        { name: 'Subscriptions', href: '/admin/subscriptions', pattern: '/admin/subscriptions*' },
        { name: 'Plans', href: '/admin/plans', pattern: '/admin/plans*' },
        { name: 'Audit Logs', href: '/admin/audit-logs', pattern: '/admin/audit-logs*' },
    ];

    const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';

    const isCurrent = (pattern: string) => {
        if (pattern.endsWith('*')) {
            const prefix = pattern.slice(0, -1);
            return currentPath.startsWith(prefix);
        }
        return currentPath === pattern;
    };

    return (
        <div className="min-h-screen bg-stone-900 text-stone-100 flex flex-col font-sans">
            <Toast />

            {/* Platform Admin Navbar */}
            <header className="bg-stone-950 border-b border-stone-800 sticky top-0 z-40 shadow-sm">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between h-16">
                        {/* Brand & Badge */}
                        <div className="flex items-center gap-6">
                            <Link href="/admin/dashboard" className="flex items-center gap-2 text-lg font-extrabold text-amber-500 tracking-tight">
                                ☕ BrewOS Admin
                            </Link>

                            {/* Navigation Links */}
                            <nav className="hidden md:flex items-center gap-1">
                                {navigation.map((item) => {
                                    const active = isCurrent(item.pattern);
                                    return (
                                        <Link
                                            key={item.name}
                                            href={item.href}
                                            className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors ${
                                                active
                                                    ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30'
                                                    : 'text-stone-300 hover:text-white hover:bg-stone-800/80'
                                            }`}
                                        >
                                            {item.name}
                                        </Link>
                                    );
                                })}
                            </nav>
                        </div>

                        {/* Super Admin User Menu */}
                        <div className="flex items-center gap-4">
                            <span className="hidden sm:inline-block bg-amber-500/10 text-amber-400 text-[11px] px-2.5 py-0.5 rounded-md font-semibold border border-amber-500/20 uppercase tracking-wider">
                                Platform Super Admin
                            </span>

                            {auth.user && (
                                <div className="flex items-center gap-3">
                                    <div className="text-right hidden sm:block">
                                        <p className="text-xs font-semibold text-stone-200">{auth.user.name}</p>
                                        <p className="text-[10px] text-stone-400">{auth.user.email}</p>
                                    </div>
                                    <button
                                        onClick={handleLogout}
                                        className="text-xs text-stone-400 hover:text-rose-400 font-medium px-2.5 py-1 rounded-md border border-stone-800 hover:border-rose-900 transition-colors"
                                    >
                                        Logout
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </header>

            {/* Sub-header / Title */}
            {title && (
                <div className="bg-stone-900/60 border-b border-stone-800/80 py-4">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">
                        <h1 className="text-xl font-bold text-white tracking-tight">{title}</h1>
                    </div>
                </div>
            )}

            {/* Main Content Area */}
            <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {children}
            </main>
        </div>
    );
};

export default AdminLayout;
