<?php

namespace App\Models;

use App\Enums\DocumentTemplateType;
use Database\Factories\DocumentTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    /** @use HasFactory<DocumentTemplateFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'body',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentTemplateType::class,
            'active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @param  Builder<DocumentTemplate>  $query
     * @return Builder<DocumentTemplate>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
