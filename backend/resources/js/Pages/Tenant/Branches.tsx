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

export interface BranchItem {
    id: number;
    name: string;
    slug: string;
    address: string | null;
    phone: string | null;
    status: string;
}

export interface BranchesProps {
    branches: BranchItem[];
}

export default function Branches({ branches }: BranchesProps) {
    const { tenant, auth } = usePage<PageProps>().props;
    const cafeSlug = tenant.cafe?.slug || '';

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingBranch, setEditingBranch] = useState<BranchItem | null>(null);

    const canCreate = auth.permissions.includes('branch.create') || auth.roles.includes('cafe-owner');
    const canUpdate = auth.permissions.includes('branch.update') || auth.roles.includes('cafe-owner');

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        slug: '',
        address: '',
        phone: '',
        status: 'active',
    });

    const openCreateModal = () => {
        reset();
        setEditingBranch(null);
        setIsModalOpen(true);
    };

    const openEditModal = (branch: BranchItem) => {
        setEditingBranch(branch);
        setData({
            name: branch.name,
            slug: branch.slug,
            address: branch.address || '',
            phone: branch.phone || '',
            status: branch.status,
        });
        setIsModalOpen(true);
    };

    const handleNameChange = (nameVal: string) => {
        setData((prev) => ({
            ...prev,
            name: nameVal,
            slug: editingBranch ? prev.slug : nameVal.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)+/g, ''),
        }));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingBranch) {
            put(`/cafes/${cafeSlug}/branches/${editingBranch.id}`, {
                onSuccess: () => {
                    setIsModalOpen(false);
                    reset();
                },
            });
        } else {
            post(`/cafes/${cafeSlug}/branches`, {
                onSuccess: () => {
                    setIsModalOpen(false);
                    reset();
                },
            });
        }
    };

    const columns: Column<BranchItem>[] = [
        {
            header: 'Branch Name',
            cell: (b) => (
                <div>
                    <p className="font-semibold text-stone-900">{b.name}</p>
                    <p className="text-xs text-stone-400 font-mono">/{b.slug}</p>
                </div>
            ),
        },
        {
            header: 'Contact & Location',
            cell: (b) => (
                <div className="text-xs text-stone-600">
                    <p>{b.phone || 'No phone set'}</p>
                    <p className="text-stone-400 truncate max-w-xs">{b.address || 'No address set'}</p>
                </div>
            ),
        },
        {
            header: 'Status',
            cell: (b) => <StatusBadge status={b.status} />,
        },
        {
            header: 'Actions',
            cell: (b) =>
                canUpdate ? (
                    <Button variant="ghost" size="sm" onClick={() => openEditModal(b)}>
                        Edit
                    </Button>
                ) : null,
        },
    ];

    return (
        <AppLayout title="Branch Management">
            <Head title="Branch Management" />

            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <div>
                    <h2 className="text-lg font-bold text-stone-900">Cafe Operational Branches</h2>
                    <p className="text-xs text-stone-500 mt-1">Manage physical cafe outlets, contact info, and status.</p>
                </div>

                {canCreate && (
                    <Button variant="primary" onClick={openCreateModal}>
                        + Add New Branch
                    </Button>
                )}
            </div>

            <div className="bg-white rounded-xl border border-stone-200 shadow-sm overflow-hidden">
                <DataTable
                    columns={columns}
                    data={branches}
                    keyExtractor={(b) => b.id}
                    emptyTitle="No Branches Registered"
                    emptyDescription="Click 'Add New Branch' to configure your first cafe branch location."
                />
            </div>

            {/* Create/Edit Modal */}
            <Modal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                title={editingBranch ? 'Edit Branch Location' : 'Add New Branch Location'}
            >
                <form onSubmit={handleSubmit} className="space-y-4">
                    <Input
                        label="Branch Name"
                        required
                        value={data.name}
                        onChange={(e) => handleNameChange(e.target.value)}
                        error={errors.name}
                        placeholder="Main Street Branch"
                    />

                    <Input
                        label="Branch Slug"
                        required
                        value={data.slug}
                        onChange={(e) => setData('slug', e.target.value)}
                        error={errors.slug}
                        placeholder="main-street-branch"
                        helperText="Used in URL structures and location identifier"
                    />

                    <Input
                        label="Phone Number"
                        type="tel"
                        value={data.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                        error={errors.phone}
                    />

                    <Input
                        label="Address"
                        value={data.address}
                        onChange={(e) => setData('address', e.target.value)}
                        error={errors.address}
                    />

                    <Select
                        label="Branch Status"
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
                            {editingBranch ? 'Update Branch' : 'Create Branch'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
