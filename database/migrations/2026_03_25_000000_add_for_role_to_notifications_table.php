<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Kolom keamanan tambahan: target role penerima notifikasi
            $table->string('for_role')->nullable()->after('user_id')
                ->comment('Target role: admin, pasien, terapis');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('for_role');
        });
    }
};
