<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_values_tmp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('definition_id')->constrained('custom_field_definitions')->cascadeOnDelete();
            $table->foreignId('animal_id')->nullable()->constrained('animals')->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('people')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['definition_id', 'animal_id']);
            $table->unique(['definition_id', 'person_id']);
            $table->index('animal_id');
            $table->index('person_id');
        });

        $rows = DB::table('custom_field_values')->get();
        foreach ($rows as $row) {
            DB::table('custom_field_values_tmp')->insert([
                'id' => $row->id,
                'definition_id' => $row->definition_id,
                'animal_id' => $row->animal_id,
                'person_id' => null,
                'value' => $row->value,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('custom_field_values');
        Schema::rename('custom_field_values_tmp', 'custom_field_values');
    }

    public function down(): void
    {
        Schema::create('custom_field_values_tmp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('definition_id')->constrained('custom_field_definitions')->cascadeOnDelete();
            $table->foreignId('animal_id')->constrained('animals')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['definition_id', 'animal_id']);
            $table->index('animal_id');
        });

        $rows = DB::table('custom_field_values')->whereNotNull('animal_id')->get();
        foreach ($rows as $row) {
            DB::table('custom_field_values_tmp')->insert([
                'id' => $row->id,
                'definition_id' => $row->definition_id,
                'animal_id' => $row->animal_id,
                'value' => $row->value,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('custom_field_values');
        Schema::rename('custom_field_values_tmp', 'custom_field_values');
    }
};
