<?php

namespace App\Http\Controllers;

use App\Enums\AnimalTab;
use App\Enums\MedicalRecordType;
use App\Enums\StockCategory;
use App\Enums\StockMovementReason;
use App\Http\Requests\StoreMedicalRecordRequest;
use App\Http\Requests\UpdateMedicalRecordRequest;
use App\Models\Animal;
use App\Models\MedicalRecord;
use App\Models\StockItem;
use App\Services\StockLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MedicalRecordController extends Controller
{
    public function create(Animal $animal): View
    {
        $this->authorizeManage();

        return view('medical.create', [
            'animal' => $animal,
            'record' => new MedicalRecord([
                'type' => MedicalRecordType::Treatment,
                'due_on' => now()->toDateString(),
            ]),
            'types' => MedicalRecordType::cases(),
            'stockItems' => StockItem::query()
                ->active()
                ->orderByRaw('category = ? desc', [StockCategory::Medical->value])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreMedicalRecordRequest $request, Animal $animal, StockLedger $ledger): RedirectResponse
    {
        $status = DB::transaction(function () use ($request, $animal, $ledger): string {
            $record = $animal->medicalRecords()->create($this->validatedData($request));

            if (! $request->filled('stock_item_id')) {
                return 'Medical record added.';
            }

            $item = StockItem::query()->findOrFail($request->integer('stock_item_id'));
            $ledger->record(
                $item,
                StockMovementReason::Used,
                $request->string('stock_quantity')->toString(),
                $request->user(),
                $animal,
                $record,
                'Medical: '.$record->name,
            );

            return 'Medical record added. '.$item->name.' stock is now '.$item->formattedQuantity().'.';
        });

        return redirect()
            ->to(AnimalTab::Health->url($animal))
            ->with('status', $status);
    }

    public function edit(MedicalRecord $medicalRecord): View
    {
        $this->authorizeManage();
        $medicalRecord->load('animal');

        return view('medical.edit', [
            'animal' => $medicalRecord->animal,
            'record' => $medicalRecord,
            'types' => MedicalRecordType::cases(),
        ]);
    }

    public function update(UpdateMedicalRecordRequest $request, MedicalRecord $medicalRecord): RedirectResponse
    {
        $medicalRecord->update($this->validatedData($request));

        return redirect()
            ->to(AnimalTab::Health->url($medicalRecord->animal))
            ->with('status', 'Medical record updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(StoreMedicalRecordRequest $request): array
    {
        $data = $request->safe()->except(['stock_item_id', 'stock_quantity']);

        foreach (['due_on', 'given_on', 'expires_on', 'notes'] as $nullable) {
            if (($data[$nullable] ?? null) === '') {
                $data[$nullable] = null;
            }
        }

        return $data;
    }

    private function authorizeManage(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canManageMedical(), 403);
    }
}
