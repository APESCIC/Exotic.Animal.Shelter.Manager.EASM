<?php

namespace App\Models;

use App\Enums\DocumentTemplateType;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'document_template_id',
        'animal_id',
        'person_id',
        'type',
        'title',
        'body_html',
        'signature_path',
        'signed_by_name',
        'signed_at',
        'pdf_path',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentTemplateType::class,
            'signed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DocumentTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    /**
     * @return BelongsTo<Animal, $this>
     */
    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }

    public function awaitingSignature(): bool
    {
        return ! $this->isSigned() && $this->type->needsSignature();
    }
}
