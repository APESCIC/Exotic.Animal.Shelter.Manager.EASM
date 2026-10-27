<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 32);
            $table->decimal('change', 12, 2);
            $table->decimal('quantity_after', 12, 2);
            $table->foreignId('animal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('medical_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['stock_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
