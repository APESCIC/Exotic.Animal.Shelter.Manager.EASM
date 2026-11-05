<?php

namespace App\Models;

use App\Enums\CitesCertificateType;
use Carbon\CarbonInterface;
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
     * GOV.UK guidance: an export or re-export permit covers commercial use for 6 months
     * from issue in place of an Article 10 certificate (to be confirmed with APHA).
     */
    public const EXPORT_PERMIT_COVER_MONTHS = 6;

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

    /**
     * Last day a recent export or re-export permit stands in for an Article 10 certificate.
     */
    public function articleTenCoverEndsOn(): ?CarbonInterface
    {
        if ($this->type !== CitesCertificateType::ExportPermit || $this->issued_on === null) {
            return null;
        }

        return $this->issued_on->copy()->addMonthsNoOverflow(self::EXPORT_PERMIT_COVER_MONTHS);
    }

    public function coversArticleTenOn(CarbonInterface $day): bool
    {
        $end = $this->articleTenCoverEndsOn();

        return $end !== null && $this->issued_on->lte($day) && $end->gte($day->copy()->startOfDay());
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
