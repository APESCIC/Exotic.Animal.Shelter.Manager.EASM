<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enclosure_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enclosure_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('name');
            $table->date('installed_on')->nullable();
            $table->unsignedSmallInteger('replace_every_days')->nullable();
            $table->date('replace_due_on')->nullable()->index();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enclosure_equipment');
    }
};
