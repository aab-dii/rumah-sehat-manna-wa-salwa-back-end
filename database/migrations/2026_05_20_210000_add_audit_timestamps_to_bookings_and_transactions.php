<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable()->after('rejection_note');
            $table->timestamp('refunded_at')->nullable()->after('verified_at');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('canceled_at')->nullable()->after('handled_by');
            $table->timestamp('completed_at')->nullable()->after('canceled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['verified_at', 'refunded_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['canceled_at', 'completed_at']);
        });
    }
};
