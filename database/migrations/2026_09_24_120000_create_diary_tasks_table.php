<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diary_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->date('due_on')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['animal_id', 'completed_at']);
            $table->index(['assigned_to', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diary_tasks');
    }
};
