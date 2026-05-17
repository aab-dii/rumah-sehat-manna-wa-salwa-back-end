<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sprint 2.1 — Super Admin: Tambah audit trail ke tabel bookings.
     * - handled_by: FK ke users.id, mencatat admin mana yang menangani booking
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'handled_by')) {
                $table->unsignedBigInteger('handled_by')->nullable()->after('created_by');
                $table->foreign('handled_by')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['handled_by']);
            $table->dropColumn('handled_by');
        });
    }
};
