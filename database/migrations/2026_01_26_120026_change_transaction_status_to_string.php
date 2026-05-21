<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                DB::statement("ALTER TABLE transactions MODIFY COLUMN status VARCHAR(50) DEFAULT 'unpaid'");
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Revert back to ENUM if needed, but risky if data has 'failed'
            if (DB::getDriverName() !== 'sqlite') {
                DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('paid', 'unpaid', 'pending', 'failed', 'rejected', 'refund') DEFAULT 'unpaid'");
            }
        });
    }
};
