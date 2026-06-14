<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use EvolutionCMS\Models\Permissions;
use EvolutionCMS\Models\PermissionsGroups;
use EvolutionCMS\Models\RolePermissions;

/**
 * Migration: sApi permissions.
 */
return new class extends Migration {
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Create sApi permission
        |--------------------------------------------------------------------------
        */
        $sGroup = PermissionsGroups::firstOrCreate(
            ['name' => 'sPackages'],
            [
                'name' => 'sPackages',
                'lang_key' => 'sPackages',
                'createdon' => time(),
                'editedon' => time(),
            ]
        );

        Permissions::updateOrCreate(
            ['key' => 'sapi_manager'],
            [
                'name' => 'Access sApi Interface',
                'key' => 'sapi_manager',
                'lang_key' => 'sApi::global.permission_access',
                'group_id' => $sGroup->id,
                'createdon' => time(),
                'editedon' => time(),
            ]
        );

        Permissions::updateOrCreate(
            ['key' => 'sapi_access'],
            [
                'name' => 'Access sApi API',
                'key' => 'sapi_access',
                'lang_key' => 'sApi::global.permission_api_access',
                'group_id' => $sGroup->id,
                'createdon' => time(),
                'editedon' => time(),
            ]
        );

        foreach (RolePermissions::where('permission', 'sapi')->pluck('role_id') as $roleId) {
            RolePermissions::firstOrCreate([
                'role_id' => (int) $roleId,
                'permission' => 'sapi_manager',
            ]);
        }

        RolePermissions::where('permission', 'sapi')->delete();
        Permissions::where('key', 'sapi')->delete();

        // Assign permission to administrator role (role_id = 1)
        RolePermissions::firstOrCreate([
            'role_id' => 1,
            'permission' => 'sapi_manager',
        ]);

        $apiRoleId = DB::table('user_roles')->where('name', 'ApiUser')->value('id');

        if ($apiRoleId === null) {
            if (DB::getDriverName() === 'pgsql') {
                $table = DB::getTablePrefix() . 'user_roles';
                $quotedTable = DB::getPdo()->quote($table);
                $wrappedTable = DB::getQueryGrammar()->wrapTable('user_roles');

                DB::statement(
                    "SELECT setval(pg_get_serial_sequence({$quotedTable}, 'id'), COALESCE((SELECT MAX(id) FROM {$wrappedTable}), 1))"
                );
            }

            $apiRoleId = DB::table('user_roles')->insertGetId([
                'name' => 'ApiUser',
                'description' => 'sApi API user role',
            ]);
        }

        RolePermissions::where('role_id', (int) $apiRoleId)
            ->whereIn('permission', ['sapi', 'sapi_manager'])
            ->delete();

        RolePermissions::firstOrCreate([
            'role_id' => (int) $apiRoleId,
            'permission' => 'sapi_access',
        ]);
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Remove sApi permission
        |--------------------------------------------------------------------------
        */
        RolePermissions::whereIn('permission', ['sapi', 'sapi_manager', 'sapi_access'])->delete();
        Permissions::whereIn('key', ['sapi', 'sapi_manager', 'sapi_access'])->delete();
    }
};
