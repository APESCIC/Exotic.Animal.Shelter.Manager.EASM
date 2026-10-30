<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dwa_licences', function (Blueprint $table) {
            $table->id();
            $table->string('licence_number');
            $table->string('issuing_authority');
            $table->text('species_covered')->nullable();
            $table->text('conditions')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('animal_dwa_licence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dwa_licence_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['animal_id', 'dwa_licence_id']);
        });

        Schema::create('dwa_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dwa_licence_id')->constrained()->cascadeOnDelete();
            $table->date('inspected_on');
            $table->string('inspector')->nullable();
            $table->string('outcome', 32);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['dwa_licence_id', 'inspected_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dwa_inspections');
        Schema::dropIfExists('animal_dwa_licence');
        Schema::dropIfExists('dwa_licences');
    }
};
