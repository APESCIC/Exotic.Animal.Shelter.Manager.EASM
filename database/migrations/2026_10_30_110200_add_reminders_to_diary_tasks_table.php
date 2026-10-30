<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diary_tasks', function (Blueprint $table) {
            $table->foreignId('enclosure_id')->nullable()->after('animal_id')->constrained()->nullOnDelete();
            $table->string('reminder_key', 100)->nullable()->unique();
            $table->unsignedBigInteger('assigned_to')->nullable()->change();
            $table->unsignedBigInteger('created_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('diary_tasks')->whereNull('assigned_to')->orWhereNull('created_by')->delete();

        Schema::table('diary_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_to')->nullable(false)->change();
            $table->unsignedBigInteger('created_by')->nullable(false)->change();
            $table->dropUnique(['reminder_key']);
            $table->dropColumn('reminder_key');
            $table->dropConstrainedForeignId('enclosure_id');
        });
    }
};
