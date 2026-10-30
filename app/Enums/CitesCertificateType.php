<?php

namespace App\Enums;

enum CitesCertificateType: string
{
    case Article10 = 'article_10';
    case ImportPermit = 'import_permit';
    case ExportPermit = 'export_permit';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Article10 => 'Article 10 certificate',
            self::ImportPermit => 'Import permit',
            self::ExportPermit => 'Export or re-export permit',
            self::Other => 'Other',
        };
    }
}
