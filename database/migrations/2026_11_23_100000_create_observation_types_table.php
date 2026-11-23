<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observation_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind');
            $table->foreignId('lookup_list_id')->nullable()->constrained('lookup_lists')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->json('species_filter')->nullable();
            $table->timestamps();

            $table->index(['active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observation_types');
    }
};
