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
        Schema::table('schedules', function (Blueprint $table) {
            // Change enum to string to allow 'holiday' and other flexible types
            $table->string('type')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            // Revert to original enum if needed (careful with data loss if values don't match)
            // For now, let's keep it as string in down or revert to enum
            // $table->enum('type', ['routine', 'unavailable'])->change();
        });
    }
};
