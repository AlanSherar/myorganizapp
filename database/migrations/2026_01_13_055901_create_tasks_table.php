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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'completed'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            
            // Routine fields
            $table->boolean('is_routine')->default(false);
            $table->enum('routine_type', ['daily', 'monthly'])->nullable();
            $table->integer('routine_day')->nullable(); // 1-31 for monthly
            $table->time('routine_time')->nullable(); // Time for daily/monthly
            
            $table->dateTime('due_date')->nullable(); // Specific date/time for non-routine
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
