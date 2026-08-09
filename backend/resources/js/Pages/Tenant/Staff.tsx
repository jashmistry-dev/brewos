import React, { useState } from 'react';
import { useForm, Head, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Button from '../../Components/Button';
import Input from '../../Components/Input';
import Select from '../../Components/Select';
import Modal from '../../Components/Modal';
import DataTable, { Column } from '../../Components/DataTable';
import StatusBadge from '../../Components/StatusBadge';
import { PageProps } from '../../types';

export interface StaffItem {
    id: number;
    user_id: number;
    name: string;
    email: string;
    role_id: number;
    role: { id: number; name: string; slug: string } | null;
    branch_id: number | null;
    branch: { id: number; name: string; slug: string } | null;
    status: string;
}

export interface RoleOption {
    id: number;
    name: string;
    slug: string;
}

export interface BranchOption {
    id: number;
    name: string;
    slug: string;
}

export interface StaffProps {
    staff: StaffItem[];
    roles: RoleOption[];
    branches: BranchOption[];
}

export default function Staff({ staff, roles, branches }: StaffProps) {
    const { tenant, auth } = usePage<PageProps>().props;
    const cafeSlug = tenant.cafe?.slug || '';

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingStaff, setEditingStaff] = useState<StaffItem | null>(null);

    const canCreate = auth.permissions.includes('staff.create') || auth.roles.includes('cafe-owner');
    const canUpdate = auth.permissions.includes('staff.update') || auth.roles.includes('cafe-owner');
    const canDelete = auth.permissions.includes('staff.delete') || auth.roles.includes('cafe-owner');

    const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        role_id: roles[0]?.id || 1,
        branch_id: branches[0]?.id || ('' as any),
        status: 'active',
    });

    const openCreateModal = () => {
        reset();
        setEditingStaff(null);
        setIsModalOpen(true);
    };

    const openEditModal = (member: StaffItem) => {
        setEditingStaff(member);
        setData({
            name: member.name,
            email: member.email,
            password: '',
            role_id: member.role_id,
            branch_id: member.branch_id || ('' as any),
            status: member.status,
        });
        setIsModalOpen(true);
    };

    const handleRevoke = (staffId: number) => {
        if (confirm('Are you sure you want to revoke access for this staff member?')) {
            destroy(`/cafes/${cafeSlug}/staff/${staffId}`);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingStaff) {
            put(`/cafes/${cafeSlug}/staff/${editingStaff.id}`, {
                onSuccess: () => {
                    setIsModalOpen(false);
                    reset();
                },
            });
        } else {
            post(`/cafes/${cafeSlug}/staff`, {
                onSuccess: () => {
                    setIsModalOpen(false);
                    reset();
                },
            });
        }
    };

    const columns: Column<StaffItem>[] = [
        {
            header: 'Staff Member',
            cell: (s) => (
                <div>
                    <p className="font-semibold text-stone-900">{s.name}</p>
                    <p className="text-xs text-stone-400">{s.email}</p>
                </div>
            ),
        },
        {
            header: 'Role',
            cell: (s) => (
                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-stone-100 text-stone-800 capitalize">
                    {s.role?.name || s.role?.slug || 'No Role'}
                </span>
            ),
        },
        {
            header: 'Branch Assignment',
            cell: (s) => (
                <span className="text-xs text-stone-600 font-medium">
                    {s.branch?.name || 'All Branches'}
                </span>
            ),
        },
        {
            header: 'Status',
            cell: (s) => <StatusBadge status={s.status} />,
        },
        {
            header: 'Actions',
            cell: (s) => (
                <div className="flex items-center gap-2">
                    {canUpdate && (
                        <Button variant="ghost" size="sm" onClick={() => openEditModal(s)}>
                            Edit
                        </Button>
                    )}
                    {canDelete && s.status === 'active' && (
                        <Button variant="danger" size="sm" onClick={() => handleRevoke(s.id)}>
                            Revoke
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Staff & Role Management">
            <Head title="Staff Management" />

            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <div>
                    <h2 className="text-lg font-bold text-stone-900">Cafe Staff Members</h2>
                    <p className="text-xs text-stone-500 mt-1">
                        Invite staff, assign operational roles, and manage branch assignments.
                    </p>
                </div>

                {canCreate && (
                    <Button variant="primary" onClick={openCreateModal}>
                        + Invite Staff Member
                    </Button>
                )}
            </div>

            <div className="bg-white rounded-xl border border-stone-200 shadow-sm overflow-hidden">
                <DataTable
                    columns={columns}
                    data={staff}
                    keyExtractor={(s) => s.id}
                    emptyTitle="No Staff Members Added"
                    emptyDescription="Click 'Invite Staff Member' to assign roles to cafe employees."
                />
            </div>

            {/* Invite/Edit Modal */}
            <Modal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                title={editingStaff ? 'Edit Staff Details' : 'Invite New Staff Member'}
            >
                <form onSubmit={handleSubmit} className="space-y-4">
                    <Input
                        label="Full Name"
                        required
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        error={errors.name}
                    />

                    <Input
                        label="Email Address"
                        type="email"
                        required
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        error={errors.email}
                    />

                    {!editingStaff && (
                        <Input
                            label="Initial Password"
                            type="password"
                            required
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            error={errors.password}
                            helperText="Minimum 8 characters"
                        />
                    )}

                    <Select
                        label="Assigned Role"
                        required
                        value={data.role_id}
                        onChange={(e) => setData('role_id', parseInt(e.target.value))}
                        options={roles.map((r) => ({ value: r.id, label: r.name }))}
                        error={errors.role_id}
                    />

                    <Select
                        label="Branch Assignment"
                        value={data.branch_id || ''}
                        onChange={(e) => setData('branch_id', e.target.value ? parseInt(e.target.value) : null as any)}
                        options={[
                            { value: '', label: 'All Branches / Unassigned' },
                            ...branches.map((b) => ({ value: b.id, label: b.name })),
                        ]}
                    />

                    <Select
                        label="Membership Status"
                        value={data.status}
                        onChange={(e) => setData('status', e.target.value)}
                        options={[
                            { value: 'active', label: 'Active' },
                            { value: 'inactive', label: 'Inactive' },
                        ]}
                    />

                    <div className="flex justify-end gap-3 pt-4 border-t border-stone-100">
                        <Button type="button" variant="secondary" onClick={() => setIsModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="primary" isLoading={processing}>
                            {editingStaff ? 'Update Staff Member' : 'Invite Staff'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
