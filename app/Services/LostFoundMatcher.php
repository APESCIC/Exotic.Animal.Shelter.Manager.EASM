<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\Animal;
use App\Models\LostFoundReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LostFoundMatcher
{
    public const RECENT_ADOPTION_DAYS = 90;

    /**
     * Rank animals in care or recently adopted that likely match a lost/found report.
     *
     * @return Collection<int, array{animal: Animal, score: int, reasons: list<string>}>
     */
    public function matchesFor(LostFoundReport $report, int $limit = 10): Collection
    {
        $candidates = $this->candidateAnimals();

        return $candidates
            ->map(function (Animal $animal) use ($report): array {
                [$score, $reasons] = $this->score($report, $animal);

                return [
                    'animal' => $animal,
                    'score' => $score,
                    'reasons' => $reasons,
                ];
            })
            ->filter(fn (array $row): bool => $row['score'] > 0)
            ->sortByDesc('score')
            ->values()
            ->take($limit);
    }

    /**
     * @return Collection<int, Animal>
     */
    private function candidateAnimals(): Collection
    {
        $recentAdoptionCutoff = Carbon::now()->subDays(self::RECENT_ADOPTION_DAYS)->toDateString();

        return Animal::query()
            ->where(function ($query) use ($recentAdoptionCutoff): void {
                $query->whereNull('deceased_at')
                    ->orWhereHas('movements', function ($movements) use ($recentAdoptionCutoff): void {
                        $movements->where('type', MovementType::Adoption->value)
                            ->whereDate('moved_at', '>=', $recentAdoptionCutoff);
                    });
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{0: int, 1: list<string>}
     */
    private function score(LostFoundReport $report, Animal $animal): array
    {
        $score = 0;
        $reasons = [];

        if ($this->tokensOverlap($report->species, $animal->species)) {
            $score += 50;
            $reasons[] = 'Species';
        }

        $reportCode = trim((string) $report->identifying_code);
        $animalCode = trim((string) $animal->identifying_code);

        if ($reportCode !== '' && $animalCode !== '' && strcasecmp($reportCode, $animalCode) === 0) {
            $score += 100;
            $reasons[] = 'Identifying code';
        } elseif ($reportCode !== '' && $this->tokensOverlap($reportCode, $animalCode)) {
            $score += 40;
            $reasons[] = 'Identifying code (partial)';
        }

        if ($this->tokensOverlap($report->colour, $animal->colour)) {
            $score += 20;
            $reasons[] = 'Colour';
        }

        if ($this->tokensOverlap($report->location_area, $animal->location)) {
            $score += 15;
            $reasons[] = 'Location';
        }

        if ($this->tokensOverlap($report->markings, $animal->flagsLabel())
            || $this->tokensOverlap($report->markings, $animal->breed_type)) {
            $score += 10;
            $reasons[] = 'Markings / description';
        }

        return [$score, $reasons];
    }

    private function tokensOverlap(?string $left, ?string $right): bool
    {
        $left = strtolower(trim((string) $left));
        $right = strtolower(trim((string) $right));

        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right || str_contains($right, $left) || str_contains($left, $right)) {
            return true;
        }

        $leftTokens = preg_split('/[\s,\/\-]+/', $left, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $rightTokens = preg_split('/[\s,\/\-]+/', $right, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($leftTokens as $token) {
            if (strlen($token) < 3) {
                continue;
            }

            foreach ($rightTokens as $other) {
                if (strlen($other) < 3) {
                    continue;
                }

                if ($token === $other || str_contains($other, $token) || str_contains($token, $other)) {
                    return true;
                }
            }
        }

        return false;
    }
}
