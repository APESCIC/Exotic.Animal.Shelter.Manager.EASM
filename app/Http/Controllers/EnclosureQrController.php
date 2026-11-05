<?php

namespace App\Http\Controllers;

use App\Models\Enclosure;
use App\Services\QrCode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

class EnclosureQrController extends Controller
{
    public function index(QrCode $qr): View
    {
        return $this->labels(Enclosure::query()->active()->ordered()->get(), $qr);
    }

    public function show(Enclosure $enclosure, QrCode $qr): View
    {
        return $this->labels(new Collection([$enclosure]), $qr);
    }

    /**
     * @param  Collection<int, Enclosure>  $enclosures
     */
    private function labels(Collection $enclosures, QrCode $qr): View
    {
        return view('enclosures.qr-labels', [
            'labels' => $enclosures->map(fn (Enclosure $enclosure): array => [
                'enclosure' => $enclosure,
                'url' => route('mobile.enclosures.show', $enclosure),
                'svg' => $qr->svg(route('mobile.enclosures.show', $enclosure)),
            ]),
        ]);
    }
}
