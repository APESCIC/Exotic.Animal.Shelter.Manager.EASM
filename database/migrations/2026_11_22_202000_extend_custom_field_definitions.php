<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_field_definitions', function (Blueprint $table) {
            $table->boolean('is_mandatory')->default(false)->after('active');
            $table->string('tooltip')->nullable()->after('is_mandatory');
            $table->boolean('show_on_new_record')->default(true)->after('tooltip');
            $table->string('species_filter')->nullable()->after('show_on_new_record');
            $table->foreignId('lookup_list_id')->nullable()->after('species_filter')
                ->constrained('lookup_lists')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('custom_field_definitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lookup_list_id');
            $table->dropColumn([
                'is_mandatory',
                'tooltip',
                'show_on_new_record',
                'species_filter',
            ]);
        });
    }
};
