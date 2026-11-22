<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animal_control_cases', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->timestamp('reported_at');
            $table->string('status', 32);
            $table->string('location_area');
            $table->string('species')->nullable();
            $table->string('summary');
            $table->text('notes')->nullable();
            $table->foreignId('reporter_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('linked_animal_id')->nullable()->constrained('animals')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('close_notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('reported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_control_cases');
    }
};
