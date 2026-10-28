<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Struktur standar spatie/laravel-permission. Jika kamu menjalankan
    // `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`
    // paket akan membuat migration serupa - jalankan salah satu saja untuk menghindari duplikasi.
    public function up(): void
    {
        $teams = config('permission.teams', false);
        $tableNames = config('permission.table_names', [
            'roles' => 'roles', 'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles', 'role_has_permissions' => 'role_has_permissions',
        ]);
        $columnNames = config('permission.column_names', ['model_morph_key' => 'model_id']);

        Schema::create($tableNames['permissions'], function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'], function (Blueprint $table) use ($teams) {
            $table->bigIncrements('id');
            if ($teams) {
                $table->unsignedBigInteger('team_foreign_key')->nullable();
                $table->index('team_foreign_key');
            }
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $teams ? $table->unique(['team_foreign_key', 'name', 'guard_name']) : $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign('permission_id')->references('id')->on($tableNames['permissions'])->onDelete('cascade');
            $table->primary(['permission_id', $columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign('role_id')->references('id')->on($tableNames['roles'])->onDelete('cascade');
            $table->primary(['role_id', $columnNames['model_morph_key'], 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->foreign('permission_id')->references('id')->on($tableNames['permissions'])->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on($tableNames['roles'])->onDelete('cascade');
            $table->primary(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names', [
            'roles' => 'roles', 'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles', 'role_has_permissions' => 'role_has_permissions',
        ]);
        Schema::drop($tableNames['role_has_permissions']);
        Schema::drop($tableNames['model_has_roles']);
        Schema::drop($tableNames['model_has_permissions']);
        Schema::drop($tableNames['roles']);
        Schema::drop($tableNames['permissions']);
    }
};
