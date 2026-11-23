<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait RecordsAudit
{
    public static function bootRecordsAudit(): void
    {
        static::created(function (Model $model): void {
            AuditLog::record($model, 'created', null, self::auditAttributes($model));
        });

        static::updated(function (Model $model): void {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $old = [];
            $new = [];
            foreach (array_keys($changes) as $key) {
                $old[$key] = $model->getOriginal($key);
                $new[$key] = $changes[$key];
            }

            AuditLog::record($model, 'updated', self::stringifyAudit($old), self::stringifyAudit($new));
        });

        static::deleted(function (Model $model): void {
            AuditLog::record($model, 'deleted', self::auditAttributes($model), null);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function auditAttributes(Model $model): array
    {
        $attrs = $model->attributesToArray();
        unset($attrs['created_at'], $attrs['updated_at']);

        return self::stringifyAudit($attrs);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function stringifyAudit(array $values): array
    {
        foreach ($values as $key => $value) {
            if ($value instanceof \BackedEnum) {
                $values[$key] = $value->value;
            } elseif ($value instanceof \UnitEnum) {
                $values[$key] = $value->name;
            } elseif ($value instanceof \DateTimeInterface) {
                $values[$key] = $value->format('Y-m-d H:i:s');
            }
        }

        return $values;
    }
}
