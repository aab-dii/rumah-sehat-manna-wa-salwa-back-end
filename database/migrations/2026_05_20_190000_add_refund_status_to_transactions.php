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
            if (Schema::hasColumn('transactions', 'status')) {
                // Ensure the status column can accommodate 'refund' (up to 50 characters)
                if (config('database.default') !== 'sqlite') {
                    \Illuminate\Support\Facades\DB::statement("ALTER TABLE transactions MODIFY COLUMN status VARCHAR(50) DEFAULT 'unpaid'");
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op since it is a VARCHAR column
    }
};
