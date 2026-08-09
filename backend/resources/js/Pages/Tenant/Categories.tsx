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

export interface CategoryItem {
    id: number;
    name: string;
    description: string | null;
    sort_order: number;
    status: string;
}

export interface CategoriesProps {
    categories: CategoryItem[];
}

export default function Categories({ categories }: CategoriesProps) {
    const { tenant, auth } = usePage<PageProps>().props;
    const cafeSlug = tenant.cafe?.slug || '';

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingCategory, setEditingCategory] = useState<CategoryItem | null>(null);

    const canCreate = auth.permissions.includes('category.create') || auth.roles.includes('cafe-owner');
    const canUpdate = auth.permissions.includes('category.update') || auth.roles.includes('cafe-owner');
    const canDelete = auth.permissions.includes('category.delete') || auth.roles.includes('cafe-owner');

    const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
        name: '',
        description: '',
        sort_order: 0,
        status: 'active',
    });

    const openCreateModal = () => {
        reset();
        setEditingCategory(null);
        setIsModalOpen(true);
    };

    const openEditModal = (cat: CategoryItem) => {
        setEditingCategory(cat);
        setData({
            name: cat.name,
            description: cat.description || '',
            sort_order: cat.sort_order,
            status: cat.status,
        });
        setIsModalOpen(true);
    };

    const handleDelete = (categoryId: number) => {
        if (confirm('Are you sure you want to delete this category? Soft deletion will occur.')) {
            destroy(`/cafes/${cafeSlug}/categories/${categoryId}`);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingCategory) {
            put(`/cafes/${cafeSlug}/categories/${editingCategory.id}`, {
                onSuccess: () => {
                    setIsModalOpen(false);
                    reset();
                },
            });
        } else {
            post(`/cafes/${cafeSlug}/categories`, {
                onSuccess: () => {
                    setIsModalOpen(false);
                    reset();
                },
            });
        }
    };

    const columns: Column<CategoryItem>[] = [
        {
            header: 'Sort',
            cell: (c) => <span className="font-mono text-xs text-stone-500 font-bold">#{c.sort_order}</span>,
        },
        {
            header: 'Category Name',
            cell: (c) => (
                <div>
                    <p className="font-semibold text-stone-900">{c.name}</p>
                    <p className="text-xs text-stone-400 max-w-sm truncate">{c.description || 'No description'}</p>
                </div>
            ),
        },
        {
            header: 'Status',
            cell: (c) => <StatusBadge status={c.status} />,
        },
        {
            header: 'Actions',
            cell: (c) => (
                <div className="flex items-center gap-2">
                    {canUpdate && (
                        <Button variant="ghost" size="sm" onClick={() => openEditModal(c)}>
                            Edit
                        </Button>
                    )}
                    {canDelete && (
                        <Button variant="danger" size="sm" onClick={() => handleDelete(c.id)}>
                            Delete
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Menu Categories">
            <Head title="Menu Categories" />

            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <div>
                    <h2 className="text-lg font-bold text-stone-900">Menu Item Categories</h2>
                    <p className="text-xs text-stone-500 mt-1">Organize your menu into structured food and beverage categories.</p>
                </div>

                {canCreate && (
                    <Button variant="primary" onClick={openCreateModal}>
                        + Add New Category
                    </Button>
                )}
            </div>

            <div className="bg-white rounded-xl border border-stone-200 shadow-sm overflow-hidden">
                <DataTable
                    columns={columns}
                    data={categories}
                    keyExtractor={(c) => c.id}
                    emptyTitle="No Categories Created"
                    emptyDescription="Click 'Add New Category' to structure your cafe menu items."
                />
            </div>

            {/* Create/Edit Modal */}
            <Modal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                title={editingCategory ? 'Edit Category' : 'Create New Category'}
            >
                <form onSubmit={handleSubmit} className="space-y-4">
                    <Input
                        label="Category Name"
                        required
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        error={errors.name}
                        placeholder="Hot Beverages"
                    />

                    <Input
                        label="Description"
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        error={errors.description}
                        placeholder="Espresso drinks, lattes, and teas"
                    />

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input
                            label="Sort Order"
                            type="number"
                            min="0"
                            value={data.sort_order}
                            onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)}
                            error={errors.sort_order}
                        />

                        <Select
                            label="Category Status"
                            value={data.status}
                            onChange={(e) => setData('status', e.target.value)}
                            options={[
                                { value: 'active', label: 'Active' },
                                { value: 'inactive', label: 'Inactive' },
                            ]}
                        />
                    </div>

                    <div className="flex justify-end gap-3 pt-4 border-t border-stone-100">
                        <Button type="button" variant="secondary" onClick={() => setIsModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="primary" isLoading={processing}>
                            {editingCategory ? 'Update Category' : 'Create Category'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
