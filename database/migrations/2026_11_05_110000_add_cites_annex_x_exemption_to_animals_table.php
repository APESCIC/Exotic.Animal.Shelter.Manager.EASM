<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->boolean('cites_exempt_annex_x')->default(false)->after('cites_annex');
            $table->text('cites_exemption_note')->nullable()->after('cites_exempt_annex_x');
        });
    }

    public function down(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->dropColumn(['cites_exempt_annex_x', 'cites_exemption_note']);
        });
    }
};
