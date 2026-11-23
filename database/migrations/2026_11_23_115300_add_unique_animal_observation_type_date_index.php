<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animal_observations', function (Blueprint $table) {
            $table->unique(
                ['animal_id', 'observation_type_id', 'observed_on'],
                'animal_observations_animal_type_date_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('animal_observations', function (Blueprint $table) {
            $table->dropUnique('animal_observations_animal_type_date_unique');
        });
    }
};
