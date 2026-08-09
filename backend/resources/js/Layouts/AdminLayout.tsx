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

    const adminNav = [
        { name: 'Dashboard', href: '/admin/dashboard', icon: '📊' },
        { name: 'Cafes', href: '/admin/cafes', icon: '☕' },
        { name: 'SaaS Plans', href: '/admin/plans', icon: '💎' },
        { name: 'Subscriptions', href: '/admin/subscriptions', icon: '💳' },
        { name: 'Audit Logs', href: '/admin/audit-logs', icon: '📋' },
    ];

    return (
        <div className="min-h-screen bg-stone-100 flex flex-col md:flex-row">
            <Toast />

            {/* Sidebar */}
            <aside className="w-full md:w-64 bg-stone-900 text-stone-300 flex-shrink-0 flex flex-col border-r border-stone-800">
                <div className="p-5 border-b border-stone-800 flex items-center justify-between">
                    <Link href="/admin/dashboard" className="text-xl font-bold text-amber-500 tracking-tight flex items-center gap-2">
                        🛡️ BrewOS Admin
                    </Link>
                </div>

                <nav className="flex-1 p-4 space-y-1">
                    {adminNav.map((item) => (
                        <Link
                            key={item.name}
                            href={item.href}
                            className="flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-semibold text-stone-300 hover:text-white hover:bg-stone-800 transition-colors"
                        >
                            <span>{item.icon}</span>
                            <span>{item.name}</span>
                        </Link>
                    ))}
                </nav>

                {auth.user && (
                    <div className="p-4 border-t border-stone-800 flex items-center justify-between">
                        <div>
                            <p className="text-xs font-semibold text-white">{auth.user.name}</p>
                            <p className="text-[10px] text-amber-400 font-bold">Super Admin</p>
                        </div>
                        <button
                            onClick={handleLogout}
                            className="text-xs text-stone-400 hover:text-rose-400 font-medium px-2.5 py-1 rounded-md border border-stone-700 hover:border-rose-900 transition-colors"
                        >
                            Logout
                        </button>
                    </div>
                )}
            </aside>

            {/* Main Content Area */}
            <div className="flex-1 flex flex-col min-w-0">
                {title && (
                    <header className="bg-white border-b border-stone-200 px-6 py-4">
                        <h1 className="text-xl font-bold text-stone-900">{title}</h1>
                    </header>
                )}
                <main className="flex-1 p-6 max-w-7xl w-full mx-auto">{children}</main>
            </div>
        </div>
    );
};

export default AdminLayout;
