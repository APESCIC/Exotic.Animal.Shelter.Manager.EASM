<?php

namespace App\Models;

use App\Enums\CitesCertificateType;
use Database\Factories\CitesCertificateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CitesCertificate extends Model
{
    /** @use HasFactory<CitesCertificateFactory> */
    use HasFactory;

    /**
     * Certificates are legal documents, so files live on the private disk and are only served to signed-in users.
     */
    public const DISK = 'local';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'animal_id',
        'type',
        'certificate_number',
        'issued_on',
        'notes',
        'file_path',
        'original_name',
        'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CitesCertificateType::class,
            'issued_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Animal, $this>
     */
    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function hasFile(): bool
    {
        return $this->file_path !== null && $this->file_path !== '';
    }

    public function summary(): string
    {
        return $this->type->label().' '.$this->certificate_number;
    }
}
