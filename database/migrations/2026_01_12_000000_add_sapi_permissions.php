<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use EvolutionCMS\Models\Permissions;
use EvolutionCMS\Models\RolePermissions;

/**
 * Migration: sApi permissions.
 */
return new class extends Migration {
    /**
     * PostgreSQL aborts the whole transaction after the first failed statement.
     * This migration intentionally retries inserts after sequence repair and after
     * duplicate-key races, so it must run without Laravel's transaction wrapper.
     *
     * @var bool
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasTable('permissions_groups') || !Schema::hasTable('permissions')) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Create sApi permission
        |--------------------------------------------------------------------------
        */
        $groupId = $this->getOrCreateGroup();

        Permissions::updateOrCreate(
            ['key' => 'sapi_manager'],
            [
                'name' => 'Access sApi Interface',
                'key' => 'sapi_manager',
                'lang_key' => 'sApi::global.permission_access',
                'group_id' => $groupId,
            ]
        );

        Permissions::updateOrCreate(
            ['key' => 'sapi_access'],
            [
                'name' => 'Access sApi API',
                'key' => 'sapi_access',
                'lang_key' => 'sApi::global.permission_api_access',
                'group_id' => $groupId,
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
        if (Schema::hasTable('role_permissions')) {
            RolePermissions::whereIn('permission', ['sapi', 'sapi_manager', 'sapi_access'])->delete();
        }

        if (Schema::hasTable('permissions')) {
            Permissions::whereIn('key', ['sapi', 'sapi_manager', 'sapi_access'])->delete();
        }

        if (Schema::hasTable('permissions_groups')) {
            $group = DB::table('permissions_groups')->where('name', 'Seiger packages')->first();

            if ($group) {
                $hasPermissions = Schema::hasTable('permissions')
                    && DB::table('permissions')->where('group_id', $group->id)->exists();

                if (!$hasPermissions) {
                    DB::table('permissions_groups')->where('id', $group->id)->delete();
                }
            }
        }
    }

    /**
     * Resolve the shared Seiger permission group or create it safely.
     *
     * @since 1.1.1
     */
    protected function getOrCreateGroup(): int
    {
        $group = DB::table('permissions_groups')
            ->where('name', 'Seiger packages')
            ->first();

        if ($group) {
            return (int) $group->id;
        }

        try {
            return (int) DB::table('permissions_groups')->insertGetId([
                'name' => 'Seiger packages',
                'lang_key' => 'seiger_packages',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            $this->fixPostgresSequence('permissions_groups');

            try {
                return (int) DB::table('permissions_groups')->insertGetId([
                    'name' => 'Seiger packages',
                    'lang_key' => 'seiger_packages',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $e2) {
                $group = DB::table('permissions_groups')
                    ->where('name', 'Seiger packages')
                    ->first();

                if ($group) {
                    return (int) $group->id;
                }

                throw $e2;
            }
        }
    }

    /**
     * Repair a PostgreSQL sequence after imported or manually assigned IDs.
     *
     * @since 1.1.1
     */
    protected function fixPostgresSequence(string $table): void
    {
        try {
            $fullTable = DB::getTablePrefix() . $table;
            $maxId = DB::table($table)->max('id') ?? 0;
            DB::statement("SELECT setval(pg_get_serial_sequence('{$fullTable}', 'id'), " . ($maxId + 1) . ", false)");
        } catch (\Exception $e) {
            // Ignore if the database is not PostgreSQL or sequence access is unavailable.
        }
    }
};
