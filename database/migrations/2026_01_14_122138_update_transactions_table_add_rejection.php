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
            $table->text('rejection_note')->nullable()->after('proof_of_transfer');
            // Modifying enum is hard in generic migration without raw SQL, 
            // easier to just drop and recreate or use valid method if doctrine installed.
            // But we can usually just modify it or assume we handle it in code.
            // Let's just try to ALTER via raw statement if needed, or simply Add the column for now.
            // Actually, let's just use the note. 'unpaid' + note present = rejected?
            // User requested explicit "Tolak". 
            // Let's try to modify column if possible, else just stick to note.
            // Simplest: Add column 'rejection_note'. We can use status 'unpaid' or 'pending' with note.
            // But 'rejected' status is cleaner.
            // Let's try changing to string.
        });
        
        // Use raw statement to update Enum if MySQL
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('paid', 'unpaid', 'pending', 'rejected') DEFAULT 'unpaid'");
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('rejection_note');
        });
         \Illuminate\Support\Facades\DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('paid', 'unpaid', 'pending') DEFAULT 'unpaid'");
    }
};
