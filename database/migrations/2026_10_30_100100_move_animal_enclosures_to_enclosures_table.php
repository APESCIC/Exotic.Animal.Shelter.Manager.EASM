<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->foreignId('enclosure_id')->nullable()->after('death_reason')->constrained()->nullOnDelete();
        });

        $now = now();
        $names = DB::table('animals')
            ->whereNotNull('enclosure')
            ->pluck('enclosure')
            ->map(fn (string $name): string => trim($name))
            ->filter(fn (string $name): bool => $name !== '')
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->values();

        foreach ($names as $name) {
            $enclosureId = DB::table('enclosures')
                ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
                ->value('id')
                ?? DB::table('enclosures')->insertGetId([
                    'name' => $name,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            DB::table('animals')
                ->whereRaw('lower(trim(enclosure)) = ?', [mb_strtolower($name)])
                ->update(['enclosure_id' => $enclosureId]);
        }

        Schema::table('animals', function (Blueprint $table) {
            $table->dropColumn('enclosure');
        });
    }

    public function down(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->string('enclosure')->nullable()->after('death_reason');
        });

        foreach (DB::table('enclosures')->get(['id', 'name']) as $enclosure) {
            DB::table('animals')
                ->where('enclosure_id', $enclosure->id)
                ->update(['enclosure' => $enclosure->name]);
        }

        Schema::table('animals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('enclosure_id');
        });
    }
};
