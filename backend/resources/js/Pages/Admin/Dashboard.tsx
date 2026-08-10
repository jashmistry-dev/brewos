import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import StatusBadge from '../../Components/StatusBadge';

export interface CafeItem {
    id: number;
    name: string;
    slug: string;
    status: string;
    created_at: string;
}

export interface AdminDashboardProps {
    metrics: {
        total_cafes: number;
        active_cafes: number;
        total_plans: number;
        total_subscriptions: number;
        active_subscriptions: number;
    };
    recent_cafes: CafeItem[];
    message: string;
}

export default function AdminDashboard({ metrics, recent_cafes }: AdminDashboardProps) {
    return (
        <AdminLayout title="Platform Dashboard">
            <Head title="Super Admin Dashboard" />

            <div className="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 className="text-2xl font-bold text-white tracking-tight">Platform Health Overview</h2>
                    <p className="text-sm text-stone-400 mt-1">Real-time SaaS operational metrics and registered cafes.</p>
                </div>
                <Link
                    href="/admin/cafes"
                    className="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-stone-950 font-bold text-xs rounded-lg transition-colors shadow-sm self-start sm:self-auto"
                >
                    Manage Customer Cafes →
                </Link>
            </div>

            {/* Metrics Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5 mb-10">
                <div className="bg-stone-800/80 p-5 rounded-xl border border-stone-700/60 shadow-sm flex items-center justify-between">
                    <div>
                        <p className="text-xs font-semibold text-stone-400 uppercase tracking-wider">Total Cafes</p>
                        <h3 className="text-3xl font-extrabold text-white mt-1">{metrics.total_cafes}</h3>
                    </div>
                    <span className="text-3xl">☕</span>
                </div>

                <div className="bg-stone-800/80 p-5 rounded-xl border border-stone-700/60 shadow-sm flex items-center justify-between">
                    <div>
                        <p className="text-xs font-semibold text-stone-400 uppercase tracking-wider">Active Cafes</p>
                        <h3 className="text-3xl font-extrabold text-emerald-400 mt-1">{metrics.active_cafes}</h3>
                    </div>
                    <span className="text-3xl">✅</span>
                </div>

                <div className="bg-stone-800/80 p-5 rounded-xl border border-stone-700/60 shadow-sm flex items-center justify-between">
                    <div>
                        <p className="text-xs font-semibold text-stone-400 uppercase tracking-wider">Total Plans</p>
                        <h3 className="text-3xl font-extrabold text-amber-400 mt-1">{metrics.total_plans}</h3>
                    </div>
                    <span className="text-3xl">📋</span>
                </div>

                <div className="bg-stone-800/80 p-5 rounded-xl border border-stone-700/60 shadow-sm flex items-center justify-between">
                    <div>
                        <p className="text-xs font-semibold text-stone-400 uppercase tracking-wider">Subscriptions</p>
                        <h3 className="text-3xl font-extrabold text-blue-400 mt-1">{metrics.total_subscriptions}</h3>
                    </div>
                    <span className="text-3xl">💳</span>
                </div>

                <div className="bg-stone-800/80 p-5 rounded-xl border border-stone-700/60 shadow-sm flex items-center justify-between">
                    <div>
                        <p className="text-xs font-semibold text-stone-400 uppercase tracking-wider">Active Subs</p>
                        <h3 className="text-3xl font-extrabold text-indigo-400 mt-1">{metrics.active_subscriptions}</h3>
                    </div>
                    <span className="text-3xl">⚡</span>
                </div>
            </div>

            {/* Recent Cafes Table */}
            <div className="bg-stone-800/80 rounded-xl border border-stone-700/60 shadow-sm overflow-hidden">
                <div className="p-5 border-b border-stone-700/60 flex items-center justify-between">
                    <h2 className="text-base font-bold text-white">Recently Registered Cafes</h2>
                    <Link href="/admin/cafes" className="text-xs font-semibold text-amber-400 hover:underline">
                        View All Cafes →
                    </Link>
                </div>

                {recent_cafes.length === 0 ? (
                    <div className="p-8 text-center text-stone-400 text-sm">
                        No cafes registered on the platform yet.
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-stone-300">
                            <thead className="bg-stone-900/50 text-xs font-semibold uppercase tracking-wider text-stone-400 border-b border-stone-700/60">
                                <tr>
                                    <th className="px-6 py-3.5">Cafe Name</th>
                                    <th className="px-6 py-3.5">Slug</th>
                                    <th className="px-6 py-3.5">Status</th>
                                    <th className="px-6 py-3.5">Registered</th>
                                    <th className="px-6 py-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-stone-700/40">
                                {recent_cafes.map((cafe) => (
                                    <tr key={cafe.id} className="hover:bg-stone-700/30 transition-colors">
                                        <td className="px-6 py-4 font-semibold text-white">{cafe.name}</td>
                                        <td className="px-6 py-4 font-mono text-xs text-amber-400/90">{cafe.slug}</td>
                                        <td className="px-6 py-4">
                                            <StatusBadge status={cafe.status} />
                                        </td>
                                        <td className="px-6 py-4 text-xs text-stone-400">
                                            {new Date(cafe.created_at).toLocaleDateString(undefined, {
                                                year: 'numeric',
                                                month: 'short',
                                                day: 'numeric',
                                            })}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <Link
                                                href={`/admin/cafes/${cafe.id}`}
                                                className="text-xs text-stone-300 hover:text-white font-medium bg-stone-700/60 hover:bg-stone-700 px-2.5 py-1 rounded-md transition-colors"
                                            >
                                                Details
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
