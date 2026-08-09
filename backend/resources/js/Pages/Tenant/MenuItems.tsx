import React, { useState } from 'react';
import { useForm, Head, usePage, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import Button from '../../Components/Button';
import Input from '../../Components/Input';
import Select from '../../Components/Select';
import Modal from '../../Components/Modal';
import DataTable, { Column } from '../../Components/DataTable';
import StatusBadge from '../../Components/StatusBadge';
import { PageProps } from '../../types';

export interface MenuItemData {
    id: number;
    category_id: number;
    category_name?: string;
    name: string;
    description: string | null;
    price: number;
    image: string | null;
    status: string;
    is_available: boolean;
    sort_order: number;
}

export interface CategoryOption {
    id: number;
    name: string;
}

export interface MenuItemsProps {
    menu_items: MenuItemData[];
    categories: CategoryOption[];
    selected_category_id: number | null;
}

export default function MenuItems({ menu_items, categories, selected_category_id }: MenuItemsProps) {
    const { tenant, auth } = usePage<PageProps>().props;
    const cafeSlug = tenant.cafe?.slug || '';

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<MenuItemData | null>(null);
    const [imagePreview, setImagePreview] = useState<string | null>(null);

    const canCreate = auth.permissions.includes('menu.create') || auth.roles.includes('cafe-owner');
    const canUpdate = auth.permissions.includes('menu.update') || auth.roles.includes('cafe-owner');
    const canDelete = auth.permissions.includes('menu.delete') || auth.roles.includes('cafe-owner');

    const { data, setData, post, processing, errors, reset } = useForm({
        category_id: categories[0]?.id || 1,
        name: '',
        description: '',
        price: '',
        image: null as File | null,
        status: 'active',
        sort_order: 0,
        _method: 'POST',
    });

    const handleCategoryFilter = (catId: string) => {
        router.get(`/cafes/${cafeSlug}/menu-items`, catId ? { category_id: catId } : {}, { preserveState: true });
    };

    const openCreateModal = () => {
        reset();
        setEditingItem(null);
        setImagePreview(null);
        setData((prev) => ({
            ...prev,
            category_id: selected_category_id || categories[0]?.id || 1,
            _method: 'POST',
        }));
        setIsModalOpen(true);
    };

    const openEditModal = (item: MenuItemData) => {
        setEditingItem(item);
        setImagePreview(item.image);
        setData({
            category_id: item.category_id,
            name: item.name,
            description: item.description || '',
            price: String(item.price),
            image: null,
            status: item.status,
            sort_order: item.sort_order,
            _method: 'PUT',
        });
        setIsModalOpen(true);
    };

    const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('image', file);
            setImagePreview(URL.createObjectURL(file));
        }
    };

    const handleToggleAvailability = (itemId: number) => {
        router.patch(`/cafes/${cafeSlug}/menu-items/${itemId}/toggle-availability`, {}, {
            preserveScroll: true,
        });
    };

    const handleDelete = (itemId: number) => {
        if (confirm('Are you sure you want to delete this menu item? Soft deletion will occur.')) {
            router.delete(`/cafes/${cafeSlug}/menu-items/${itemId}`, {
                preserveScroll: true,
            });
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingItem) {
            post(`/cafes/${cafeSlug}/menu-items/${editingItem.id}`, {
                onSuccess: () => {
                    setIsModalOpen(false);
                    reset();
                },
            });
        } else {
            post(`/cafes/${cafeSlug}/menu-items`, {
                onSuccess: () => {
                    setIsModalOpen(false);
                    reset();
                },
            });
        }
    };

    const columns: Column<MenuItemData>[] = [
        {
            header: 'Item',
            cell: (item) => (
                <div className="flex items-center gap-3">
                    <div className="w-12 h-12 rounded-lg bg-stone-100 border border-stone-200 overflow-hidden flex items-center justify-center shrink-0">
                        {item.image ? (
                            <img src={item.image} alt={item.name} className="w-full h-full object-cover" />
                        ) : (
                            <span className="text-xl">☕</span>
                        )}
                    </div>
                    <div>
                        <p className="font-semibold text-stone-900">{item.name}</p>
                        <p className="text-xs text-stone-400 max-w-xs truncate">{item.description || 'No description'}</p>
                    </div>
                </div>
            ),
        },
        {
            header: 'Category',
            cell: (item) => (
                <span className="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-amber-50 text-amber-900 border border-amber-200">
                    {item.category_name || 'Uncategorized'}
                </span>
            ),
        },
        {
            header: 'Price',
            cell: (item) => (
                <span className="font-bold text-stone-900">
                    ₹{item.price.toFixed(2)}
                </span>
            ),
        },
        {
            header: 'Availability',
            cell: (item) => (
                <div className="flex items-center gap-2">
                    <StatusBadge status={item.is_available ? 'active' : 'unavailable'} />
                    {canUpdate && (
                        <button
                            type="button"
                            onClick={() => handleToggleAvailability(item.id)}
                            className={`text-xs px-2 py-1 rounded border transition-colors ${
                                item.is_available
                                    ? 'border-red-200 text-red-700 bg-red-50 hover:bg-red-100'
                                    : 'border-emerald-200 text-emerald-700 bg-emerald-50 hover:bg-emerald-100'
                            }`}
                        >
                            {item.is_available ? 'Disable' : 'Enable'}
                        </button>
                    )}
                </div>
            ),
        },
        {
            header: 'Actions',
            cell: (item) => (
                <div className="flex items-center gap-2">
                    {canUpdate && (
                        <Button variant="ghost" size="sm" onClick={() => openEditModal(item)}>
                            Edit
                        </Button>
                    )}
                    {canDelete && (
                        <Button variant="danger" size="sm" onClick={() => handleDelete(item.id)}>
                            Delete
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Menu Item Management">
            <Head title="Menu Items" />

            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <div>
                    <h2 className="text-lg font-bold text-stone-900">Cafe Menu Items</h2>
                    <p className="text-xs text-stone-500 mt-1">Manage food items, prices, images, and live operational availability.</p>
                </div>

                <div className="flex items-center gap-3 w-full sm:w-auto">
                    <Select
                        value={selected_category_id || ''}
                        onChange={(e) => handleCategoryFilter(e.target.value)}
                        options={[
                            { value: '', label: 'All Categories' },
                            ...categories.map((c) => ({ value: c.id, label: c.name })),
                        ]}
                    />

                    {canCreate && (
                        <Button variant="primary" onClick={openCreateModal} className="whitespace-nowrap">
                            + Add Menu Item
                        </Button>
                    )}
                </div>
            </div>

            <div className="bg-white rounded-xl border border-stone-200 shadow-sm overflow-hidden">
                <DataTable
                    columns={columns}
                    data={menu_items}
                    keyExtractor={(item) => item.id}
                    emptyTitle="No Menu Items Found"
                    emptyDescription="Click 'Add Menu Item' to create items for your cafe menu."
                />
            </div>

            {/* Create/Edit Modal */}
            <Modal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                title={editingItem ? 'Edit Menu Item' : 'Add New Menu Item'}
            >
                <form onSubmit={handleSubmit} className="space-y-4">
                    <Select
                        label="Category"
                        required
                        value={data.category_id}
                        onChange={(e) => setData('category_id', parseInt(e.target.value))}
                        options={categories.map((c) => ({ value: c.id, label: c.name }))}
                        error={errors.category_id}
                    />

                    <Input
                        label="Item Name"
                        required
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        error={errors.name}
                        placeholder="Cappuccino"
                    />

                    <Input
                        label="Description"
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        error={errors.description}
                        placeholder="Rich espresso with steamed milk foam"
                    />

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Input
                            label="Price (₹)"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                            value={data.price}
                            onChange={(e) => setData('price', e.target.value)}
                            error={errors.price}
                            placeholder="180.00"
                        />

                        <Input
                            label="Sort Order"
                            type="number"
                            min="0"
                            value={data.sort_order}
                            onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)}
                            error={errors.sort_order}
                        />
                    </div>

                    <Select
                        label="Initial Status"
                        value={data.status}
                        onChange={(e) => setData('status', e.target.value)}
                        options={[
                            { value: 'active', label: 'Active (Available)' },
                            { value: 'unavailable', label: 'Unavailable' },
                            { value: 'inactive', label: 'Inactive' },
                        ]}
                    />

                    <div>
                        <label className="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">
                            Item Image (JPEG, PNG, WebP max 2MB)
                        </label>
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                            onChange={handleImageChange}
                            className="w-full text-xs text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100"
                        />
                        {errors.image && <p className="text-xs text-red-600 mt-1">{errors.image}</p>}

                        {imagePreview && (
                            <div className="mt-3 relative w-20 h-20 rounded-lg overflow-hidden border border-stone-200">
                                <img src={imagePreview} alt="Preview" className="w-full h-full object-cover" />
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end gap-3 pt-4 border-t border-stone-100">
                        <Button type="button" variant="secondary" onClick={() => setIsModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="primary" isLoading={processing}>
                            {editingItem ? 'Update Menu Item' : 'Create Menu Item'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
