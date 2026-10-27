<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Setting;
use App\Support\UkDate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentPdf
{
    public function output(Document $document): string
    {
        $signature = $document->signature_path !== null && Storage::disk('local')->exists($document->signature_path)
            ? base64_encode((string) Storage::disk('local')->get($document->signature_path))
            : null;

        return Pdf::loadView('documents.pdf', [
            'document' => $document,
            'organisationName' => Setting::current()?->organisation_name ?? config('app.name'),
            'signature' => $signature,
            'signedOn' => $document->signed_at ? UkDate::format($document->signed_at) : null,
            'generatedOn' => UkDate::format($document->created_at ?? now()),
        ])
            ->setOption('isFontSubsettingEnabled', true)
            ->setPaper('a4')
            ->output();
    }

    public function filename(Document $document): string
    {
        return Str::slug($document->title).'-'.$document->id.'.pdf';
    }
}
