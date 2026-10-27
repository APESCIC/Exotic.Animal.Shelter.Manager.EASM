<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waiting_list_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32)->index();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 64)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('species');
            $table->text('details')->nullable();
            $table->string('urgency', 16)->index();
            $table->date('added_on');
            $table->string('status', 16)->default('open')->index();
            $table->date('closed_on')->nullable();
            $table->string('closed_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiting_list_entries');
    }
};
