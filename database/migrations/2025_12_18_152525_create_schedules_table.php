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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapist_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['routine', 'unavailable']);
            $table->string('day')->nullable(); // For routine (e.g., "Monday")
            $table->date('specific_date')->nullable(); // For unavailable or specific dates
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('location_type', ['clinic', 'home']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
