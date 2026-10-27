<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Models\Animal;
use App\Models\Application;
use App\Models\CustomFieldDefinition;
use App\Models\Diet;
use App\Models\LostFoundReport;
use App\Models\MedicalRecord;
use App\Models\Movement;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_signed_in_page_uses_the_app_layout(): void
    {
        $admin = User::factory()->admin()->create();
        $animal = Animal::factory()->create();

        $parameters = [
            'animal' => $animal,
            'medicalRecord' => MedicalRecord::factory()->for($animal)->create(),
            'diet' => Diet::factory()->for($animal)->create(),
            'person' => Person::factory()->create(),
            'application' => Application::factory()->create(),
            'lostFoundReport' => LostFoundReport::factory()->create(),
            'customField' => CustomFieldDefinition::factory()->create(),
        ];

        $routes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => in_array('GET', $route->methods(), true)
                && in_array('auth', $route->gatherMiddleware(), true)
                && ! str_ends_with((string) $route->getName(), '.json'));

        $this->assertGreaterThan(20, $routes->count());

        foreach ($routes as $route) {
            $bindings = [];
            foreach ($route->parameterNames() as $name) {
                if (str_contains($route->uri(), '{'.$name.'?}')) {
                    continue;
                }
                $this->assertArrayHasKey($name, $parameters, "No test model for route parameter {$name} on {$route->uri()}");
                $bindings[$name] = $parameters[$name];
            }

            $this->actingAs($admin)
                ->get(route($route->getName(), $bindings))
                ->assertOk()
                ->assertSee('data-layout="app"', false)
                ->assertSee('href="#main"', false);
        }
    }

    public function test_sidebar_marks_the_current_section(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('people.index'))
            ->assertOk()
            ->assertSee('href="'.route('people.index').'" aria-current="page"', false);
    }

    public function test_admin_links_are_hidden_from_non_admins(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('admin.settings.edit'), false);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee(route('admin.settings.edit'), false);
    }

    public function test_animal_badges_show_quarantine_cites_and_dwa(): void
    {
        $animal = Animal::factory()->create([
            'name' => 'Badge Gecko',
            'cites' => 'Appendix II',
            'dwa' => 'Licence 42',
        ]);
        Movement::factory()->for($animal)->create([
            'type' => MovementType::Quarantine,
            'moved_at' => now()->subDay(),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('animals.index'))
            ->assertOk()
            ->assertSee('badge-quarantine', false)
            ->assertSee('badge-cites', false)
            ->assertSee('badge-dwa', false);
    }

    public function test_quarantine_status_follows_the_latest_movement(): void
    {
        $quarantined = Animal::factory()->create();
        Movement::factory()->for($quarantined)->create(['type' => MovementType::Intake, 'moved_at' => now()->subDays(3)]);
        Movement::factory()->for($quarantined)->create(['type' => MovementType::Quarantine, 'moved_at' => now()->subDays(2)]);

        $released = Animal::factory()->create();
        Movement::factory()->for($released)->create(['type' => MovementType::Quarantine, 'moved_at' => now()->subDays(3)]);
        Movement::factory()->for($released)->create(['type' => MovementType::Hold, 'moved_at' => now()->subDay()]);

        $deceased = Animal::factory()->create(['deceased_at' => now()]);
        Movement::factory()->for($deceased)->create(['type' => MovementType::Quarantine, 'moved_at' => now()->subDay()]);

        $this->assertTrue($quarantined->isInQuarantine());
        $this->assertFalse($released->isInQuarantine());
        $this->assertFalse($deceased->isInQuarantine());
        $this->assertSame([$quarantined->id], Animal::query()->inQuarantine()->pluck('id')->all());
    }
}
