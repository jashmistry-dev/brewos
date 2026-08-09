import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import StatusBadge from '../../Components/StatusBadge';
import { PageProps } from '../../types';

export interface DashboardProps {
    cafe: {
        id: number;
        name: string;
        slug: string;
        email: string;
        phone: string | null;
        status: string;
    };
    branchCount: number;
    staffCount: number;
}

export default function Dashboard({ cafe, branchCount, staffCount }: DashboardProps) {
    const { auth } = usePage<PageProps>().props;

    const hasPermission = (permission: string) => {
        return auth.permissions.includes(permission) || auth.roles.includes('cafe-owner');
    };

    const shortcuts = [
        { name: 'Manage Menu', href: `/cafes/${cafe.slug}/menu-items`, show: hasPermission('menu.manage'), icon: '📜' },
        { name: 'Tables & QR', href: `/cafes/${cafe.slug}/tables`, show: hasPermission('table.manage'), icon: '📱' },
        { name: 'Orders Display', href: `/cafes/${cafe.slug}/orders`, show: hasPermission('order.view'), icon: '🛒' },
        { name: 'Kitchen Display', href: `/cafes/${cafe.slug}/kitchen-display`, show: hasPermission('kds.view'), icon: '🍳' },
        { name: 'Sales Reports', href: `/cafes/${cafe.slug}/reports/sales`, show: hasPermission('report.view'), icon: '📈' },
        { name: 'Advanced Analytics', href: `/cafes/${cafe.slug}/analytics/customers`, show: hasPermission('report.view'), icon: '📊' },
    ];

    return (
        <AppLayout title="Cafe Operational Dashboard">
            <Head title="Dashboard" />

            {/* Operational Summary Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                <div className="bg-white p-6 rounded-xl border border-stone-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p className="text-xs font-semibold text-stone-500 uppercase tracking-wider">Cafe Business</p>
                        <h3 className="text-lg font-bold text-stone-900 mt-1">{cafe.name}</h3>
                        <div className="mt-2">
                            <StatusBadge status={cafe.status} />
                        </div>
                    </div>
                    <span className="text-3xl">☕</span>
                </div>

                <div className="bg-white p-6 rounded-xl border border-stone-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p className="text-xs font-semibold text-stone-500 uppercase tracking-wider">Active Branches</p>
                        <h3 className="text-3xl font-extrabold text-amber-600 mt-1">{branchCount}</h3>
                        <Link href={`/cafes/${cafe.slug}/branches`} className="text-xs text-amber-700 font-medium hover:underline mt-2 inline-block">
                            View Branches →
                        </Link>
                    </div>
                    <span className="text-3xl">🏢</span>
                </div>

                <div className="bg-white p-6 rounded-xl border border-stone-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p className="text-xs font-semibold text-stone-500 uppercase tracking-wider">Staff Members</p>
                        <h3 className="text-3xl font-extrabold text-amber-600 mt-1">{staffCount}</h3>
                        <Link href={`/cafes/${cafe.slug}/staff`} className="text-xs text-amber-700 font-medium hover:underline mt-2 inline-block">
                            View Staff →
                        </Link>
                    </div>
                    <span className="text-3xl">👥</span>
                </div>

                <div className="bg-white p-6 rounded-xl border border-stone-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p className="text-xs font-semibold text-stone-500 uppercase tracking-wider">Current Role</p>
                        <h3 className="text-lg font-bold text-stone-900 mt-1 capitalize">{auth.roles[0] || 'Member'}</h3>
                        <p className="text-xs text-stone-500 mt-1">{auth.user?.email}</p>
                    </div>
                    <span className="text-3xl">🛡️</span>
                </div>
            </div>

            {/* Quick Operational Shortcuts */}
            <div className="bg-white rounded-xl border border-stone-200 p-6 shadow-sm mb-8">
                <h2 className="text-base font-bold text-stone-900 mb-4">Quick Operational Shortcuts</h2>
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                    {shortcuts.filter(s => s.show).map((item) => (
                        <Link
                            key={item.name}
                            href={item.href}
                            className="flex flex-col items-center justify-center p-4 rounded-xl border border-stone-200 bg-stone-50/50 hover:bg-amber-50 hover:border-amber-200 transition-colors text-center group"
                        >
                            <span className="text-2xl mb-2 group-hover:scale-110 transition-transform">{item.icon}</span>
                            <span className="text-xs font-semibold text-stone-800 group-hover:text-amber-900">{item.name}</span>
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
