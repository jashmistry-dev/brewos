import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import Button from '../../Components/Button';
import Input from '../../Components/Input';

export interface ProfileProps {
    user: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        status: string;
        is_super_admin: boolean;
        created_at: string | null;
    };
}

export default function Profile({ user }: ProfileProps) {
    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: '',
        new_password: '',
        new_password_confirmation: '',
    });

    const handlePasswordSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put('/admin/profile/password', {
            onSuccess: () => reset(),
        });
    };

    return (
        <AdminLayout title="Super Admin Security & Profile">
            <Head title="Super Admin Profile — BrewOS" />

            <div className="max-w-4xl mx-auto space-y-8">
                {/* Account Header Banner */}
                <div className="bg-stone-800/80 p-6 rounded-xl border border-stone-700/60 shadow-sm flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="w-14 h-14 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-full flex items-center justify-center font-bold text-xl">
                            🛡️
                        </div>
                        <div>
                            <h2 className="text-xl font-bold text-white">{user.name}</h2>
                            <p className="text-xs text-stone-400 mt-0.5">{user.email}</p>
                        </div>
                    </div>
                    <span className="bg-amber-500/10 text-amber-400 border border-amber-500/30 text-xs px-3 py-1 rounded-full font-bold uppercase tracking-wider">
                        Platform Super Admin
                    </span>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                    {/* Account Details Card */}
                    <div className="bg-stone-800/80 p-6 rounded-xl border border-stone-700/60 shadow-sm space-y-4">
                        <h3 className="text-base font-bold text-white border-b border-stone-700/60 pb-3">
                            Account Information
                        </h3>
                        <div className="space-y-3 text-xs text-stone-300">
                            <div className="flex justify-between py-1 border-b border-stone-700/40">
                                <span className="text-stone-400">Full Name</span>
                                <span className="font-semibold text-white">{user.name}</span>
                            </div>
                            <div className="flex justify-between py-1 border-b border-stone-700/40">
                                <span className="text-stone-400">Email Address</span>
                                <span className="font-mono text-amber-400">{user.email}</span>
                            </div>
                            <div className="flex justify-between py-1 border-b border-stone-700/40">
                                <span className="text-stone-400">Account Status</span>
                                <span className="font-bold text-emerald-400 uppercase">{user.status}</span>
                            </div>
                            <div className="flex justify-between py-1">
                                <span className="text-stone-400">Member Since</span>
                                <span>{user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A'}</span>
                            </div>
                        </div>
                    </div>

                    {/* Change Password Form */}
                    <div className="bg-stone-800/80 p-6 rounded-xl border border-stone-700/60 shadow-sm">
                        <h3 className="text-base font-bold text-white border-b border-stone-700/60 pb-3 mb-4">
                            Update Account Password
                        </h3>

                        <form onSubmit={handlePasswordSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-stone-300 mb-1">
                                    Current Password
                                </label>
                                <input
                                    type="password"
                                    required
                                    value={data.current_password}
                                    onChange={(e) => setData('current_password', e.target.value)}
                                    className="w-full bg-stone-900 border border-stone-700 rounded-lg px-3 py-2 text-xs text-white focus:border-amber-500 focus:outline-none"
                                    placeholder="••••••••"
                                />
                                {errors.current_password && (
                                    <p className="text-xs text-rose-400 mt-1">{errors.current_password}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-stone-300 mb-1">
                                    New Password
                                </label>
                                <input
                                    type="password"
                                    required
                                    value={data.new_password}
                                    onChange={(e) => setData('new_password', e.target.value)}
                                    className="w-full bg-stone-900 border border-stone-700 rounded-lg px-3 py-2 text-xs text-white focus:border-amber-500 focus:outline-none"
                                    placeholder="Minimum 8 characters"
                                />
                                {errors.new_password && (
                                    <p className="text-xs text-rose-400 mt-1">{errors.new_password}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-stone-300 mb-1">
                                    Confirm New Password
                                </label>
                                <input
                                    type="password"
                                    required
                                    value={data.new_password_confirmation}
                                    onChange={(e) => setData('new_password_confirmation', e.target.value)}
                                    className="w-full bg-stone-900 border border-stone-700 rounded-lg px-3 py-2 text-xs text-white focus:border-amber-500 focus:outline-none"
                                    placeholder="••••••••"
                                />
                            </div>

                            <div className="pt-2">
                                <Button
                                    type="submit"
                                    variant="primary"
                                    size="md"
                                    className="w-full"
                                    isLoading={processing}
                                >
                                    Update Password
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
