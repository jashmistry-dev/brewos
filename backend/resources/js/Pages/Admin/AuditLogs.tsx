import React from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head } from '@inertiajs/react';

interface AuditLogItem {
    id: number;
    user_id: number | null;
    cafe_id: number | null;
    action: string;
    entity_type: string;
    entity_id: number | null;
    old_values: any;
    new_values: any;
    ip_address: string | null;
    created_at: string;
    user?: {
        id: number;
        name: string;
        email: string;
    } | null;
    cafe?: {
        id: number;
        name: string;
        slug: string;
    } | null;
}

interface Pagination {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

interface Props {
    audit_logs: AuditLogItem[];
    pagination: Pagination;
    filters?: {
        cafe_id?: string;
        user_id?: string;
        action?: string;
        entity_type?: string;
    };
}

export default function AuditLogs({ audit_logs, pagination }: Props) {
    return (
        <AdminLayout title="System Audit Logs">
            <Head title="System Audit Logs — Super Admin" />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header */}
                <div className="border-b border-stone-800 pb-5">
                    <h1 className="text-2xl font-bold text-stone-100 tracking-tight">System Audit Logs</h1>
                    <p className="text-sm text-stone-400 mt-1">
                        Track platform-wide administrative actions, subscription adjustments, and security events.
                    </p>
                </div>

                {/* Audit Logs Table */}
                <div className="bg-stone-900 border border-stone-800 rounded-xl overflow-hidden shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-stone-300">
                            <thead className="bg-stone-950 border-b border-stone-800 text-xs uppercase font-semibold text-stone-400 tracking-wider">
                                <tr>
                                    <th className="px-6 py-4">Timestamp</th>
                                    <th className="px-6 py-4">Action</th>
                                    <th className="px-6 py-4">Actor</th>
                                    <th className="px-6 py-4">Target Cafe</th>
                                    <th className="px-6 py-4">Entity</th>
                                    <th className="px-6 py-4">Details</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-stone-800">
                                {audit_logs.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-8 text-center text-stone-500">
                                            No audit logs found.
                                        </td>
                                    </tr>
                                ) : (
                                    audit_logs.map((log) => (
                                        <tr key={log.id} className="hover:bg-stone-800/50 transition text-xs">
                                            <td className="px-6 py-4 text-stone-400 font-mono whitespace-nowrap">
                                                {new Date(log.created_at).toLocaleString()}
                                            </td>
                                            <td className="px-6 py-4 font-mono font-semibold text-amber-400">
                                                {log.action}
                                            </td>
                                            <td className="px-6 py-4 text-stone-200">
                                                {log.user ? (
                                                    <div>
                                                        <div className="font-medium">{log.user.name}</div>
                                                        <div className="text-stone-500 text-[11px]">{log.user.email}</div>
                                                    </div>
                                                ) : (
                                                    <span className="text-stone-500">System</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-stone-200">
                                                {log.cafe ? (
                                                    <a href={`/admin/cafes/${log.cafe.id}`} className="text-amber-400 hover:text-amber-300">
                                                        {log.cafe.name}
                                                    </a>
                                                ) : (
                                                    <span className="text-stone-500">Platform Global</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-stone-400 font-mono">
                                                {log.entity_type} #{log.entity_id ?? 'N/A'}
                                            </td>
                                            <td className="px-6 py-4">
                                                <details className="cursor-pointer group">
                                                    <summary className="text-stone-400 hover:text-stone-200 font-medium">
                                                        View Changes
                                                    </summary>
                                                    <div className="mt-2 p-3 bg-stone-950 rounded-lg border border-stone-800 space-y-2 font-mono text-[11px] text-stone-300 max-w-md overflow-x-auto">
                                                        {log.old_values && (
                                                            <div>
                                                                <span className="text-rose-400 font-semibold">Previous: </span>
                                                                <pre className="inline whitespace-pre-wrap">{JSON.stringify(log.old_values, null, 2)}</pre>
                                                            </div>
                                                        )}
                                                        {log.new_values && (
                                                            <div>
                                                                <span className="text-emerald-400 font-semibold">New: </span>
                                                                <pre className="inline whitespace-pre-wrap">{JSON.stringify(log.new_values, null, 2)}</pre>
                                                            </div>
                                                        )}
                                                    </div>
                                                </details>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination Footer */}
                    {pagination && pagination.last_page > 1 && (
                        <div className="bg-stone-950 px-6 py-4 border-t border-stone-800 flex items-center justify-between text-xs text-stone-400">
                            <div>
                                Showing Page {pagination.current_page} of {pagination.last_page} ({pagination.total} total logs)
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
