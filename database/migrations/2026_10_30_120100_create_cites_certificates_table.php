<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cites_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('certificate_number');
            $table->date('issued_on')->nullable();
            $table->text('notes')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['animal_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cites_certificates');
    }
};
