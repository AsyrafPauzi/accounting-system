<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        /** @var \Spatie\Permission\Models\Permission $permission */
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'audit.view']);
        
        $adminRoles = \Spatie\Permission\Models\Role::whereIn('name', ['admin', 'super-admin'])->get();
        
        foreach ($adminRoles as $role) {
            /** @var \Spatie\Permission\Models\Role $role */
            $role->givePermissionTo($permission);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /** @var \Spatie\Permission\Models\Permission|null $permission */
        $permission = \Spatie\Permission\Models\Permission::where('name', 'audit.view')->first();
        if ($permission) {
            $permission->delete();
        }
        
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
