import React, { ReactNode, useState, useEffect } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { PageProps } from '../types';
import Toast from '../Components/Toast';

export interface AppLayoutProps {
    children: ReactNode;
    title?: string;
    cafeSlug?: string;
}

export const AppLayout: React.FC<AppLayoutProps> = ({ children, title }) => {
    const { auth, tenant } = usePage<PageProps>().props;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [activeDropdown, setActiveDropdown] = useState<'menu' | 'team' | 'finance' | 'admin' | null>(null);

    const toggleDropdown = (name: 'menu' | 'team' | 'finance' | 'admin', e: React.MouseEvent) => {
        e.stopPropagation();
        setActiveDropdown((prev) => (prev === name ? null : name));
    };

    useEffect(() => {
        const handleOutsideClick = (e: MouseEvent) => {
            if (!(e.target as HTMLElement).closest('.nav-dropdown-container')) {
                setActiveDropdown(null);
            }
        };
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                setActiveDropdown(null);
            }
        };
        document.addEventListener('click', handleOutsideClick);
        document.addEventListener('keydown', handleKeyDown);
        return () => {
            document.removeEventListener('click', handleOutsideClick);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, []);

    const cafeSlug = tenant.cafe?.slug || '';

    const handleLogout = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/logout');
    };

    const hasPermission = (permission: string) => {
        if (auth.roles.includes('cafe-owner') || auth.roles.includes('super-admin')) return true;
        return auth.permissions.includes(permission);
    };

    const navSections = [
        {
            title: 'OPERATIONS',
            items: [
                { name: 'Dashboard', href: `/cafes/${cafeSlug}/dashboard`, icon: '📊', show: true },
                { name: 'Orders', href: `/cafes/${cafeSlug}/orders`, icon: '🧾', show: hasPermission('order.view') },
                { name: 'Kitchen Display', href: `/cafes/${cafeSlug}/kitchen-display`, icon: '🍳', show: hasPermission('order.kitchen.view') },
                { name: 'Tables & QR', href: `/cafes/${cafeSlug}/tables`, icon: '🪑', show: hasPermission('table.view') },
                { name: 'Categories', href: `/cafes/${cafeSlug}/categories`, icon: '📁', show: hasPermission('category.view') || hasPermission('menu.view') },
                { name: 'Menu Items', href: `/cafes/${cafeSlug}/menu-items`, icon: '☕', show: hasPermission('menu.view') },
            ],
        },
        {
            title: 'PEOPLE & OUTLETS',
            items: [
                { name: 'Staff', href: `/cafes/${cafeSlug}/staff`, icon: '👥', show: hasPermission('staff.view') },
                { name: 'Branches', href: `/cafes/${cafeSlug}/branches`, icon: '🏬', show: hasPermission('branch.view') },
            ],
        },
        {
            title: 'FINANCE',
            items: [
                { name: 'Payments', href: `/cafes/${cafeSlug}/payments`, icon: '💳', show: hasPermission('payment.view') },
                { name: 'Invoices', href: `/cafes/${cafeSlug}/invoices`, icon: '📄', show: hasPermission('invoice.view') },
            ],
        },
        {
            title: 'BUSINESS & SETTINGS',
            items: [
                { name: 'Reports', href: `/cafes/${cafeSlug}/reports/sales`, icon: '📈', show: hasPermission('report.view') },
                { name: 'Analytics', href: `/cafes/${cafeSlug}/analytics/customers`, icon: '🎯', show: hasPermission('report.view') },
                { name: 'Cafe Settings', href: `/cafes/${cafeSlug}/settings`, icon: '⚙️', show: hasPermission('cafe.view') || hasPermission('cafe.settings.update') },
                { name: 'Subscription', href: cafeSlug ? `/cafes/${cafeSlug}/subscription` : '/subscription', icon: '👑', show: hasPermission('subscription.view') },
            ],
        },
    ];

    const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';

    const isCurrent = (href: string) => {
        return currentPath === href || currentPath.startsWith(href + '/');
    };

    return (
        <div className="min-h-screen bg-stone-50 flex flex-col font-sans">
            <Toast />

            {/* Header / Navbar */}
            <header className="bg-stone-900 text-white shadow-md border-b border-stone-800 sticky top-0 z-40">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between h-16 gap-4">
                        {/* Logo & Active Cafe Badge */}
                        <div className="flex items-center gap-3 shrink-0">
                            <Link href={`/cafes/${cafeSlug}/dashboard`} className="flex items-center gap-2 text-xl font-black text-amber-500 tracking-tight">
                                ☕ BrewOS
                            </Link>
                            {tenant.cafe && (
                                <span className="bg-stone-800 text-amber-400 text-xs px-2.5 py-1 rounded-lg font-bold border border-stone-700 hidden sm:inline-block">
                                    {tenant.cafe.name}
                                </span>
                            )}
                        </div>

                        {/* Desktop Navigation Row */}
                        <nav className="hidden lg:flex items-center gap-1.5">
                            {/* Primary Operations Links */}
                            {navSections[0].items.filter(i => i.show).slice(0, 4).map((item) => {
                                const active = isCurrent(item.href);
                                return (
                                    <Link
                                        key={item.name}
                                        href={item.href}
                                        className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all whitespace-nowrap flex items-center gap-1.5 ${
                                            active
                                                ? 'bg-amber-500 text-stone-950 shadow-sm'
                                                : 'text-stone-300 hover:text-white hover:bg-stone-800'
                                        }`}
                                    >
                                        <span>{item.icon}</span>
                                        <span>{item.name}</span>
                                    </Link>
                                );
                            })}

                            {/* Section Dropdown 1: Menu & Catalog */}
                            {navSections[0].items.filter(i => i.show).slice(4).length > 0 && (
                                <div className="relative nav-dropdown-container">
                                    <button
                                        onClick={(e) => toggleDropdown('menu', e)}
                                        className={`px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1 transition-colors ${
                                            activeDropdown === 'menu' ? 'bg-stone-800 text-amber-400' : 'text-stone-300 hover:text-white hover:bg-stone-800'
                                        }`}
                                    >
                                        <span>🍽️ Menu & Catalog ▾</span>
                                    </button>
                                    {activeDropdown === 'menu' && (
                                        <div className="absolute left-0 top-full pt-1 w-48 bg-stone-900 border border-stone-800 rounded-xl shadow-xl p-1 z-50">
                                            {navSections[0].items.filter(i => i.show).slice(4).map((item) => (
                                                <Link
                                                    key={item.name}
                                                    href={item.href}
                                                    onClick={() => setActiveDropdown(null)}
                                                    className={`px-3 py-2 rounded-lg text-xs font-semibold block transition-colors flex items-center gap-2 ${
                                                        isCurrent(item.href) ? 'bg-amber-500/10 text-amber-400 font-bold' : 'text-stone-300 hover:bg-stone-800 hover:text-white'
                                                    }`}
                                                >
                                                    <span>{item.icon}</span>
                                                    <span>{item.name}</span>
                                                </Link>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* Section Dropdown 2: People & Outlets */}
                            {navSections[1].items.filter(i => i.show).length > 0 && (
                                <div className="relative nav-dropdown-container">
                                    <button
                                        onClick={(e) => toggleDropdown('team', e)}
                                        className={`px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1 transition-colors ${
                                            activeDropdown === 'team' ? 'bg-stone-800 text-amber-400' : 'text-stone-300 hover:text-white hover:bg-stone-800'
                                        }`}
                                    >
                                        <span>👥 Team & Outlets ▾</span>
                                    </button>
                                    {activeDropdown === 'team' && (
                                        <div className="absolute left-0 top-full pt-1 w-48 bg-stone-900 border border-stone-800 rounded-xl shadow-xl p-1 z-50">
                                            {navSections[1].items.filter(i => i.show).map((item) => (
                                                <Link
                                                    key={item.name}
                                                    href={item.href}
                                                    onClick={() => setActiveDropdown(null)}
                                                    className={`px-3 py-2 rounded-lg text-xs font-semibold block transition-colors flex items-center gap-2 ${
                                                        isCurrent(item.href) ? 'bg-amber-500/10 text-amber-400 font-bold' : 'text-stone-300 hover:bg-stone-800 hover:text-white'
                                                    }`}
                                                >
                                                    <span>{item.icon}</span>
                                                    <span>{item.name}</span>
                                                </Link>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* Section Dropdown 3: Finance */}
                            {navSections[2].items.filter(i => i.show).length > 0 && (
                                <div className="relative nav-dropdown-container">
                                    <button
                                        onClick={(e) => toggleDropdown('finance', e)}
                                        className={`px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1 transition-colors ${
                                            activeDropdown === 'finance' ? 'bg-stone-800 text-amber-400' : 'text-stone-300 hover:text-white hover:bg-stone-800'
                                        }`}
                                    >
                                        <span>💳 Finance ▾</span>
                                    </button>
                                    {activeDropdown === 'finance' && (
                                        <div className="absolute left-0 top-full pt-1 w-48 bg-stone-900 border border-stone-800 rounded-xl shadow-xl p-1 z-50">
                                            {navSections[2].items.filter(i => i.show).map((item) => (
                                                <Link
                                                    key={item.name}
                                                    href={item.href}
                                                    onClick={() => setActiveDropdown(null)}
                                                    className={`px-3 py-2 rounded-lg text-xs font-semibold block transition-colors flex items-center gap-2 ${
                                                        isCurrent(item.href) ? 'bg-amber-500/10 text-amber-400 font-bold' : 'text-stone-300 hover:bg-stone-800 hover:text-white'
                                                    }`}
                                                >
                                                    <span>{item.icon}</span>
                                                    <span>{item.name}</span>
                                                </Link>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* Section Dropdown 4: Reports & Settings */}
                            {navSections[3].items.filter(i => i.show).length > 0 && (
                                <div className="relative nav-dropdown-container">
                                    <button
                                        onClick={(e) => toggleDropdown('admin', e)}
                                        className={`px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1 transition-colors ${
                                            activeDropdown === 'admin' ? 'bg-stone-800 text-amber-400' : 'text-stone-300 hover:text-white hover:bg-stone-800'
                                        }`}
                                    >
                                        <span>⚙️ Admin & Reports ▾</span>
                                    </button>
                                    {activeDropdown === 'admin' && (
                                        <div className="absolute right-0 top-full pt-1 w-52 bg-stone-900 border border-stone-800 rounded-xl shadow-xl p-1 z-50">
                                            {navSections[3].items.filter(i => i.show).map((item) => (
                                                <Link
                                                    key={item.name}
                                                    href={item.href}
                                                    onClick={() => setActiveDropdown(null)}
                                                    className={`px-3 py-2 rounded-lg text-xs font-semibold block transition-colors flex items-center gap-2 ${
                                                        isCurrent(item.href) ? 'bg-amber-500/10 text-amber-400 font-bold' : 'text-stone-300 hover:bg-stone-800 hover:text-white'
                                                    }`}
                                                >
                                                    <span>{item.icon}</span>
                                                    <span>{item.name}</span>
                                                </Link>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}
                        </nav>

                        {/* User Area & Logout */}
                        <div className="hidden lg:flex items-center gap-3 shrink-0">
                            {auth.user && (
                                <div className="flex items-center gap-3 pl-3 border-l border-stone-800">
                                    <div className="text-right">
                                        <p className="text-xs font-bold text-stone-200">{auth.user.name}</p>
                                        <p className="text-[10px] text-amber-400 font-mono capitalize">{auth.roles[0] ? auth.roles[0].replace('-', ' ') : 'User'}</p>
                                    </div>
                                    <button
                                        onClick={handleLogout}
                                        className="text-xs text-stone-400 hover:text-rose-400 font-bold px-2.5 py-1.5 rounded-lg border border-stone-800 hover:border-rose-900 transition-colors"
                                    >
                                        Logout
                                    </button>
                                </div>
                            )}
                        </div>

                        {/* Mobile Menu Toggle Button */}
                        <button
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            className="lg:hidden p-2 text-stone-300 hover:text-white font-bold text-lg"
                        >
                            {mobileMenuOpen ? '✕' : '☰ Menu'}
                        </button>
                    </div>
                </div>

                {/* Mobile Drawer */}
                {mobileMenuOpen && (
                    <div className="lg:hidden bg-stone-900 border-b border-stone-800 px-4 pt-2 pb-4 space-y-4 shadow-2xl">
                        {navSections.map((sec) => {
                            const visibleItems = sec.items.filter((i) => i.show);
                            if (visibleItems.length === 0) return null;

                            return (
                                <div key={sec.title} className="space-y-1">
                                    <p className="text-[10px] uppercase tracking-wider font-black text-amber-500 px-2 pt-1">{sec.title}</p>
                                    <div className="grid grid-cols-2 gap-1">
                                        {visibleItems.map((item) => (
                                            <Link
                                                key={item.name}
                                                href={item.href}
                                                onClick={() => setMobileMenuOpen(false)}
                                                className={`px-3 py-2 rounded-lg text-xs font-bold block transition-colors flex items-center gap-2 ${
                                                    isCurrent(item.href) ? 'bg-amber-500 text-stone-950' : 'text-stone-300 bg-stone-950/60 hover:bg-stone-800'
                                                }`}
                                            >
                                                <span>{item.icon}</span>
                                                <span className="truncate">{item.name}</span>
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                        {auth.user && (
                            <div className="pt-3 border-t border-stone-800 flex justify-between items-center">
                                <div>
                                    <p className="text-xs font-bold text-stone-200">{auth.user.name}</p>
                                    <p className="text-[10px] text-amber-400 capitalize">{auth.roles[0] ? auth.roles[0].replace('-', ' ') : 'User'}</p>
                                </div>
                                <button
                                    onClick={handleLogout}
                                    className="text-xs text-rose-400 font-bold px-3 py-1.5 border border-rose-900 rounded-lg bg-rose-950/40"
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
