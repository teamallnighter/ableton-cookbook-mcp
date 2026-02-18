<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Rack permissions
            'create racks',
            'edit racks',
            'delete racks',
            'moderate racks',
            'feature racks',
            
            // User permissions
            'view users',
            'edit users',
            'ban users',
            'moderate users',
            
            // Comment permissions
            'create comments',
            'edit comments',
            'delete comments',
            'moderate comments',
            
            // General admin permissions
            'access admin panel',
            'view analytics',
            'manage site settings',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        
        // Regular user role
        $userRole = Role::create(['name' => 'user']);
        $userRole->givePermissionTo([
            'create racks',
            'edit racks',
            'delete racks',
            'create comments',
            'edit comments',
            'delete comments',
        ]);

        // Pro user role (verified creators)
        $proRole = Role::create(['name' => 'pro']);
        $proRole->givePermissionTo($userRole->permissions->pluck('name')->toArray());
        $proRole->givePermissionTo([
            'view analytics',
        ]);

        // Moderator role
        $moderatorRole = Role::create(['name' => 'moderator']);
        $moderatorRole->givePermissionTo($proRole->permissions->pluck('name')->toArray());
        $moderatorRole->givePermissionTo([
            'moderate racks',
            'moderate comments',
            'moderate users',
        ]);

        // Admin role
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());

        // Banned user role (restricted)
        Role::create(['name' => 'banned']);
    }
}