<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The last applied migration name from the migrations table (schema fingerprint).
 */
class SchemaVersion
{
    public function current(): ?string
    {
        try {
            if (! Schema::hasTable('migrations')) {
                return null;
            }

            $migration = DB::table('migrations')
                ->orderByDesc('batch')
                ->orderByDesc('migration')
                ->value('migration');

            return is_string($migration) && $migration !== '' ? $migration : null;
        } catch (Throwable) {
            return null;
        }
    }
}
