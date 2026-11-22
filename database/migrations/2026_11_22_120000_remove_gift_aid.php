<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gift_aid_declarations');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('gift_aid_eligible');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('gift_aid_eligible')->default(false)->after('reference');
        });

        Schema::create('gift_aid_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->date('declared_on');
            $table->date('effective_from');
            $table->date('cancelled_on')->nullable();
            $table->string('method', 32);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
};