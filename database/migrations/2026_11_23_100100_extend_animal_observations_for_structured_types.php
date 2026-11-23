<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animal_observations', function (Blueprint $table) {
            $table->foreignId('observation_type_id')->nullable()->after('animal_id')
                ->constrained('observation_types')->nullOnDelete();
            $table->foreignId('lookup_value_id')->nullable()->after('observation_type_id')
                ->constrained('lookup_values')->nullOnDelete();
            $table->string('value_text', 500)->nullable()->after('lookup_value_id');
        });

        Schema::table('animal_observations', function (Blueprint $table) {
            $table->text('body')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('animal_observations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('observation_type_id');
            $table->dropConstrainedForeignId('lookup_value_id');
            $table->dropColumn('value_text');
            $table->text('body')->nullable(false)->change();
        });
    }
};
