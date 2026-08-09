import React, { ReactNode, useState } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { PageProps } from '../types';
import Toast from '../Components/Toast';

export interface AppLayoutProps {
    children: ReactNode;
    title?: string;
}

export const AppLayout: React.FC<AppLayoutProps> = ({ children, title }) => {
    const { auth, tenant } = usePage<PageProps>().props;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    const cafeSlug = tenant.cafe?.slug || '';

    const handleLogout = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/logout');
    };

    const hasPermission = (permission: string) => {
        return auth.permissions.includes(permission) || auth.roles.includes('cafe-owner');
    };

    const navigation = [
        { name: 'Dashboard', href: `/cafes/${cafeSlug}/dashboard`, show: true },
        { name: 'Branches', href: `/cafes/${cafeSlug}/branches`, show: hasPermission('branch.manage') },
        { name: 'Staff', href: `/cafes/${cafeSlug}/staff`, show: hasPermission('staff.manage') },
        { name: 'Categories', href: `/cafes/${cafeSlug}/categories`, show: hasPermission('menu.manage') },
        { name: 'Menu Items', href: `/cafes/${cafeSlug}/menu-items`, show: hasPermission('menu.manage') },
        { name: 'Tables & QR', href: `/cafes/${cafeSlug}/tables`, show: hasPermission('table.manage') },
        { name: 'Orders', href: `/cafes/${cafeSlug}/orders`, show: hasPermission('order.view') },
        { name: 'Kitchen Display', href: `/cafes/${cafeSlug}/kitchen-display`, show: hasPermission('kds.view') },
        { name: 'Payments', href: `/cafes/${cafeSlug}/payments`, show: hasPermission('payment.view') },
        { name: 'Invoices', href: `/cafes/${cafeSlug}/invoices`, show: hasPermission('invoice.view') },
        { name: 'Subscription', href: `/cafes/${cafeSlug}/subscription`, show: hasPermission('subscription.view') },
        { name: 'Reports', href: `/cafes/${cafeSlug}/reports/sales`, show: hasPermission('report.view') },
        { name: 'Analytics', href: `/cafes/${cafeSlug}/analytics/customers`, show: hasPermission('report.view') },
        { name: 'Settings', href: `/cafes/${cafeSlug}/settings`, show: hasPermission('settings.manage') },
    ];

    return (
        <div className="min-h-screen bg-stone-50 flex flex-col">
            <Toast />

            {/* Header / Navbar */}
            <header className="bg-stone-900 text-white shadow-md border-b border-stone-800 sticky top-0 z-40">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between h-16">
                        {/* Logo & Active Cafe */}
                        <div className="flex items-center gap-3">
                            <Link href="/" className="flex items-center gap-2 text-xl font-bold text-amber-500 tracking-tight">
                                ☕ BrewOS
                            </Link>
                            {tenant.cafe && (
                                <span className="bg-stone-800 text-stone-300 text-xs px-2.5 py-1 rounded-md font-medium border border-stone-700">
                                    {tenant.cafe.name}
                                </span>
                            )}
                        </div>

                        {/* Desktop Navigation */}
                        <nav className="hidden md:flex items-center gap-1 overflow-x-auto py-2">
                            {navigation.filter(item => item.show).map((item) => (
                                <Link
                                    key={item.name}
                                    href={item.href}
                                    className="px-3 py-1.5 rounded-lg text-xs font-medium text-stone-300 hover:text-white hover:bg-stone-800 transition-colors whitespace-nowrap"
                                >
                                    {item.name}
                                </Link>
                            ))}
                        </nav>

                        {/* User Area & Logout */}
                        <div className="hidden md:flex items-center gap-4">
                            {auth.user && (
                                <div className="flex items-center gap-3">
                                    <div className="text-right">
                                        <p className="text-xs font-semibold text-white">{auth.user.name}</p>
                                        <p className="text-[10px] text-stone-400">{auth.roles[0] || 'User'}</p>
                                    </div>
                                    <button
                                        onClick={handleLogout}
                                        className="text-xs text-stone-400 hover:text-rose-400 font-medium px-2.5 py-1 rounded-md border border-stone-700 hover:border-rose-900 transition-colors"
                                    >
                                        Logout
                                    </button>
                                </div>
                            )}
                        </div>

                        {/* Mobile Menu Button */}
                        <button
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            className="md:hidden p-2 text-stone-400 hover:text-white"
                        >
                            {mobileMenuOpen ? '✕' : '☰'}
                        </button>
                    </div>
                </div>

                {/* Mobile Drawer */}
                {mobileMenuOpen && (
                    <div className="md:hidden bg-stone-900 border-b border-stone-800 px-4 pt-2 pb-4 space-y-1">
                        {navigation.filter(item => item.show).map((item) => (
                            <Link
                                key={item.name}
                                href={item.href}
                                onClick={() => setMobileMenuOpen(false)}
                                className="block px-3 py-2 rounded-md text-sm font-medium text-stone-300 hover:text-white hover:bg-stone-800"
                            >
                                {item.name}
                            </Link>
                        ))}
                        {auth.user && (
                            <div className="pt-4 border-t border-stone-800 flex justify-between items-center">
                                <span className="text-xs text-stone-300">{auth.user.name}</span>
                                <button
                                    onClick={handleLogout}
                                    className="text-xs text-rose-400 font-medium px-3 py-1 border border-rose-900 rounded-md"
                                >
                                    Logout
                                </button>
                            </div>
                        )}
                    </div>
                )}
            </header>

            {/* Page Header Title */}
            {title && (
                <div className="bg-white border-b border-stone-200 py-4">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <h1 className="text-xl font-bold text-stone-900 tracking-tight">{title}</h1>
                    </div>
                </div>
            )}

            {/* Main Content */}
            <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
                {children}
            </main>
        </div>
    );
};

export default AppLayout;
