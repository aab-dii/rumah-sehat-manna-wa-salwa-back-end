<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 2.1: Ubah kolom role di tabel users dari ENUM ke STRING
 * agar mendukung role baru 'super_admin'.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Untuk MySQL: ubah ENUM ke VARCHAR
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'pasien'");
        }
        // Untuk SQLite (testing): tidak bisa ALTER, jadi kita skip
        // SQLite tidak enforce ENUM secara native, tapi CHECK constraint bisa muncul
        // dari schema. Kita buat ulang tabel jika diperlukan.

        // Fallback menggunakan Schema Builder (works on SQLite)
        if (DB::getDriverName() === 'sqlite') {
            // SQLite: Buat tabel baru tanpa constraint, copy data, rename
            Schema::table('users', function (Blueprint $table) {
                $table->string('role_new', 50)->default('pasien')->after('role');
            });

            DB::table('users')->update(['role_new' => DB::raw('role')]);

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('role_new', 'role');
            });
        }
    }

    public function down(): void
    {
        // Revert ke enum jika diperlukan (MySQL only)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'terapis', 'pasien', 'super_admin') NOT NULL DEFAULT 'pasien'");
        }
    }
};
