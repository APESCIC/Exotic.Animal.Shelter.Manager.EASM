<?php

namespace Tests\Feature;

use App\Enums\AnimalTab;
use App\Enums\MovementType;
use App\Models\Animal;
use App\Models\AnimalMedia;
use App\Models\AnimalObservation;
use App\Models\DiaryTask;
use App\Models\Diet;
use App\Models\MedicalRecord;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AnimalTabsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: AnimalTab, 1: list<string>, 2: list<string>}>
     */
    public static function tabs(): array
    {
        return [
            'overview' => [AnimalTab::Overview, ['Date of birth', 'Tab Colour'], ['Tab Ivermectin', 'Tab Pellets', 'Tab Bright']],
            'health' => [AnimalTab::Health, ['Tab Ivermectin', 'Add medical record'], ['Tab Pellets', 'Tab Bright']],
            'husbandry' => [AnimalTab::Husbandry, ['Tab Pellets', 'ENC-77', 'Add diet'], ['Tab Ivermectin']],
            'movements' => [AnimalTab::Movements, ['Tab intake reason', 'Record movement'], ['Tab Ivermectin']],
            'legal' => [AnimalTab::Legal, ['Appendix II', 'DWA-TAB-1'], ['Tab Ivermectin']],
            'media' => [AnimalTab::Media, ['tab-photo.jpg', 'Upload media'], ['Tab Ivermectin']],
            'diary' => [AnimalTab::Diary, ['Tab weigh-in', 'Add diary task'], ['Tab Ivermectin']],
            'log' => [AnimalTab::Log, ['Tab Bright', 'Add observation'], ['Tab Ivermectin']],
        ];
    }

    /**
     * @param  list<string>  $sees
     * @param  list<string>  $doesNotSee
     */
    #[DataProvider('tabs')]
    public function test_each_tab_has_its_own_route_and_content(AnimalTab $tab, array $sees, array $doesNotSee): void
    {
        $animal = $this->animalWithRecords();

        $url = $tab->url($animal);
        $this->assertSame(
            $tab === AnimalTab::Overview ? url('/animals/'.$animal->id) : url('/animals/'.$animal->id.'/'.$tab->value),
            $url,
        );

        $response = $this->actingAs(User::factory()->staff()->create())
            ->get($url)
            ->assertOk()
            ->assertSee('data-active-tab="'.$tab->value.'"', false)
            ->assertSee('data-tab="'.$tab->value.'" aria-current="page"', false);

        foreach ($sees as $text) {
            $response->assertSee($text, false);
        }

        foreach ($doesNotSee as $text) {
            $response->assertDontSee($text, false);
        }
    }

    public function test_tab_bar_lists_all_eight_tabs_with_counts(): void
    {
        $animal = $this->animalWithRecords();

        $response = $this->actingAs(User::factory()->readonly()->create())
            ->get(route('animals.show', $animal))
            ->assertOk();

        $this->assertCount(8, AnimalTab::cases());
        foreach (AnimalTab::cases() as $tab) {
            $response->assertSee('href="'.$tab->url($animal).'" data-tab="'.$tab->value.'"', false);
        }

        $response->assertSee('aria-label="1 records"', false);
    }

    public function test_unknown_tab_is_not_found(): void
    {
        $animal = Animal::factory()->create();

        $this->actingAs(User::factory()->staff()->create())
            ->get('/animals/'.$animal->id.'/nonsense')
            ->assertNotFound();
    }

    public function test_actions_return_to_the_matching_tab(): void
    {
        $animal = Animal::factory()->create();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post(route('animals.medical.store', $animal), ['type' => 'test', 'name' => 'Swab'])
            ->assertRedirect(AnimalTab::Health->url($animal));

        $this->actingAs($staff)
            ->post(route('animals.diets.store', $animal), ['name' => 'Crickets'])
            ->assertRedirect(AnimalTab::Husbandry->url($animal));

        $this->actingAs($staff)
            ->post(route('animals.observations.store', $animal), ['observed_on' => '2026-10-01', 'body' => 'Shed well'])
            ->assertRedirect(AnimalTab::Log->url($animal));

        $this->actingAs($staff)
            ->post(route('animals.movements.store', $animal), ['type' => MovementType::Hold->value, 'moved_at' => '2026-10-01'])
            ->assertRedirect(AnimalTab::Movements->url($animal));

        $this->actingAs($staff)
            ->post(route('animals.diary.store', $animal), ['title' => 'Mist enclosure', 'assigned_to' => $staff->id, 'due_on' => '2026-10-02'])
            ->assertRedirect(AnimalTab::Diary->url($animal));
    }

    public function test_animal_forms_have_sticky_save_bar_and_unsaved_changes_guard(): void
    {
        $animal = Animal::factory()->create();
        $staff = User::factory()->staff()->create();

        foreach ([route('animals.create'), route('animals.edit', $animal)] as $url) {
            $this->actingAs($staff)
                ->get($url)
                ->assertOk()
                ->assertSee('data-dirty-guard', false)
                ->assertSee('class="save-bar"', false)
                ->assertSee('data-dirty-status', false);
        }

        $this->actingAs($staff)
            ->get(route('animals.medical.create', $animal))
            ->assertOk()
            ->assertSee('data-dirty-guard', false);
    }

    private function animalWithRecords(): Animal
    {
        $animal = Animal::factory()->create([
            'colour' => 'Tab Colour',
            'enclosure' => 'ENC-77',
            'cites' => 'Appendix II',
            'dwa' => 'DWA-TAB-1',
        ]);

        MedicalRecord::factory()->for($animal)->create(['name' => 'Tab Ivermectin', 'notes' => null]);
        Diet::factory()->for($animal)->create(['name' => 'Tab Pellets', 'details' => null]);
        Movement::factory()->for($animal)->create(['type' => MovementType::Intake, 'reason' => 'Tab intake reason', 'notes' => null]);
        AnimalMedia::factory()->for($animal)->create(['original_name' => 'tab-photo.jpg']);
        DiaryTask::factory()->for($animal)->create(['title' => 'Tab weigh-in', 'body' => null]);
        AnimalObservation::factory()->for($animal)->create(['body' => 'Tab Bright and alert']);

        return $animal;
    }
}
