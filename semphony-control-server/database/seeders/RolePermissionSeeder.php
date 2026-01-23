<?php

namespace Database\Seeders;

use App\Models\Command;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $userRole = Role::query()->firstOrCreate([
            'name' => 'User',
            'guard_name' => 'web',
        ]);

        $adminRole = Role::query()->firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        $adminOnlyCommandNames = [
            'vacuum_vent',
            'vacuum_pump',
        ];

        $allPermissions = [];
        $userPermissions = [];

        foreach (Command::query()->get() as $command) {
            $permission = Permission::query()->firstOrCreate([
                'name' => $command->permissionName(),
                'guard_name' => 'web',
            ]);

            $allPermissions[] = $permission;

            if (! in_array($command->name, $adminOnlyCommandNames, true)) {
                $userPermissions[] = $permission;
            }
        }

        $adminRole->syncPermissions($allPermissions);
        $userRole->syncPermissions($userPermissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
