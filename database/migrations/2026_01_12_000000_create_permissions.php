<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Schema;
use EvolutionCMS\Models\Permissions;
use EvolutionCMS\Models\PermissionsGroups;
use EvolutionCMS\Models\RolePermissions;

/**
 * Migration: sApi tables creation.
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

        Permissions::firstOrCreate(
            ['key' => 'sapi'],
            [
                'name' => 'Access sApi Interface',
                'key' => 'sapi',
                'lang_key' => 'sApi::global.permission_access',
                'group_id' => $sGroup->id,
                'createdon' => time(),
                'editedon' => time(),
            ]
        );

        // Assign permission to administrator role (role_id = 1)
        RolePermissions::firstOrCreate([
            'role_id' => 1,
            'permission' => 'sapi',
        ]);
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Remove sApi permission
        |--------------------------------------------------------------------------
        */
        RolePermissions::where('permission', 'sapi')->delete();
        Permissions::where('key', 'sapi')->delete();
    }
};