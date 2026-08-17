<?php

namespace App\Services;

use App\Models\Cafe;
use App\Models\Permission;
use App\Models\Role;

class DefaultTenantRolesService
{
    public static array $defaultRolesWithPermissions = [
        'cafe-owner' => [
            'name' => 'Cafe Owner',
            'permissions' => [
                'cafe.view', 'cafe.update', 'cafe.settings.update',
                'branch.view', 'branch.create', 'branch.update',
                'staff.view', 'branch.view', 'staff.create', 'staff.update', 'staff.delete',
                'role.view', 'role.create', 'role.update', 'role.delete',
                'menu.view', 'menu.create', 'menu.update', 'menu.delete',
                'category.view', 'category.create', 'category.update', 'category.delete',
                'table.view', 'table.create', 'table.update', 'table.delete',
                'order.view', 'order.create', 'order.update', 'order.cancel',
                'order.kitchen.view', 'order.kitchen.update',
                'customer.view', 'customer.create', 'customer.update',
                'payment.view', 'payment.create',
                'invoice.view', 'invoice.create', 'invoice.download', 'invoice.settings.update',
                'report.view',
                'subscription.view', 'subscription.update',
            ],
        ],
        'manager' => [
            'name' => 'Manager',
            'permissions' => [
                'order.view', 'order.create', 'order.update', 'order.cancel',
                'order.kitchen.view', 'order.kitchen.update',
                'menu.view', 'menu.create', 'menu.update',
                'category.view',
                'table.view', 'table.update',
                'customer.view',
                'staff.view', 'branch.view',
                'payment.view', 'payment.create',
                'invoice.view', 'invoice.create', 'invoice.download',
                'report.view',
            ],
        ],
        'cashier' => [
            'name' => 'Cashier',
            'permissions' => [
                'order.view', 'order.create', 'order.update', 'order.cancel',
                'table.view',
                'customer.view', 'customer.create',
                'payment.view', 'payment.create',
                'invoice.view', 'invoice.create', 'invoice.download',
            ],
        ],
        'waiter' => [
            'name' => 'Waiter',
            'permissions' => [
                'table.view', 'order.view', 'order.create', 'order.update',
                'customer.view', 'customer.create', 'menu.view',
            ],
        ],
        'kitchen-staff' => [
            'name' => 'Kitchen Staff',
            'permissions' => [
                'order.kitchen.view', 'order.kitchen.update',
            ],
        ],
    ];

    public function createDefaultRolesForCafe(Cafe $cafe): array
    {
        $createdRoles = [];

        foreach (self::$defaultRolesWithPermissions as $roleSlug => $roleData) {
            $role = Role::create([
                'cafe_id' => $cafe->id,
                'name' => $roleData['name'],
                'slug' => $roleSlug,
                'scope' => 'tenant',
            ]);

            $permissionIds = [];
            foreach ($roleData['permissions'] as $permSlug) {
                $permission = Permission::firstOrCreate([
                    'slug' => $permSlug,
                ], [
                    'name' => ucwords(str_replace('.', ' ', $permSlug)),
                ]);
                $permissionIds[] = $permission->id;
            }

            $role->permissions()->sync($permissionIds);
            $createdRoles[$roleSlug] = $role;
        }

        return $createdRoles;
    }
}
