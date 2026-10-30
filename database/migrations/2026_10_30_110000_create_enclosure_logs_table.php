<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enclosure_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enclosure_id')->constrained()->cascadeOnDelete();
            $table->dateTime('recorded_at');
            $table->decimal('temperature_hot_c', 4, 1)->nullable();
            $table->decimal('temperature_cool_c', 4, 1)->nullable();
            $table->unsignedTinyInteger('humidity_percent')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['enclosure_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enclosure_logs');
    }
};
