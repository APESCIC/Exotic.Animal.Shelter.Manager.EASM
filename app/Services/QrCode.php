<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCode
{
    /**
     * Inline SVG (no XML declaration) so it can be printed straight from the page.
     */
    public function svg(string $text, int $size = 240): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));

        return (string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $writer->writeString($text));
    }
}
