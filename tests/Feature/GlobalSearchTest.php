<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Person;
use App\Models\User;
use App\Services\GlobalSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_search(): void
    {
        $this->get(route('search', ['q' => 'gecko']))->assertRedirect(route('login'));
        $this->getJson(route('search.json', ['q' => 'gecko']))->assertUnauthorized();
    }

    public function test_finds_an_animal_by_microchip(): void
    {
        $chipped = Animal::factory()->create(['name' => 'Chipped Iguana', 'identifying_code' => '900118000654321']);
        Animal::factory()->create(['name' => 'Other Iguana', 'identifying_code' => 'EX-0001']);

        $this->actingAs(User::factory()->readonly()->create())
            ->getJson(route('search.json', ['q' => '900118000654321']))
            ->assertOk()
            ->assertJsonPath('groups.0.key', 'animals')
            ->assertJsonCount(1, 'groups.0.items')
            ->assertJsonPath('groups.0.items.0.title', 'Chipped Iguana')
            ->assertJsonPath('groups.0.items.0.url', route('animals.show', $chipped));
    }

    public function test_finds_an_animal_by_cites_reference(): void
    {
        Animal::factory()->create(['name' => 'Permit Parrot', 'cites' => 'Article 10 GB-2026-0042']);
        Animal::factory()->create(['name' => 'Plain Parrot', 'cites' => null]);

        $this->actingAs(User::factory()->volunteer()->create())
            ->getJson(route('search.json', ['q' => 'GB-2026-0042']))
            ->assertOk()
            ->assertJsonCount(1, 'groups.0.items')
            ->assertJsonPath('groups.0.items.0.title', 'Permit Parrot')
            ->assertJsonPath('groups.0.items.0.subtitle', fn (string $subtitle): bool => str_contains($subtitle, 'CITES Article 10 GB-2026-0042'));
    }

    public function test_finds_people_by_email_and_postcode(): void
    {
        $person = Person::factory()->create(['name' => 'Ada Keeper', 'email' => 'ada@example.org', 'postcode' => 'SW1A 1AA']);
        Person::factory()->create(['name' => 'Bob Other', 'email' => 'bob@example.org', 'postcode' => 'M1 1AE']);

        $user = User::factory()->staff()->create();

        foreach (['ada@example', 'SW1A'] as $term) {
            $this->actingAs($user)
                ->getJson(route('search.json', ['q' => $term]))
                ->assertOk()
                ->assertJsonPath('groups.0.key', 'people')
                ->assertJsonCount(1, 'groups.0.items')
                ->assertJsonPath('groups.0.items.0.url', route('people.show', $person));
        }
    }

    public function test_json_shape_groups_animals_and_people(): void
    {
        Animal::factory()->create(['name' => 'Kestrel Kaa', 'species' => 'Royal python']);
        Person::factory()->create(['name' => 'Kestrel Smith']);

        $this->actingAs(User::factory()->staff()->create())
            ->getJson(route('search.json', ['q' => 'Kestrel']))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('query', 'Kestrel')
                ->has('groups', 2)
                ->has('groups.0', fn (AssertableJson $group) => $group
                    ->where('key', 'animals')
                    ->where('label', 'Animals')
                    ->where('more_url', route('animals.index', ['q' => 'Kestrel']))
                    ->has('items', 1, fn (AssertableJson $item) => $item
                        ->where('type', 'animal')
                        ->where('title', 'Kestrel Kaa')
                        ->has('subtitle')
                        ->has('url')))
                ->where('groups.1.key', 'people'));
    }

    public function test_short_queries_return_no_groups_and_results_are_capped(): void
    {
        Animal::factory()->count(GlobalSearch::LIMIT_PER_GROUP + 4)->create(['species' => 'Leopard gecko']);

        $user = User::factory()->staff()->create();

        $this->actingAs($user)
            ->getJson(route('search.json', ['q' => 'g']))
            ->assertOk()
            ->assertJsonPath('groups', []);

        $this->actingAs($user)
            ->getJson(route('search.json', ['q' => 'Leopard gecko']))
            ->assertOk()
            ->assertJsonCount(GlobalSearch::LIMIT_PER_GROUP, 'groups.0.items');
    }

    public function test_results_page_is_the_no_javascript_fallback(): void
    {
        Animal::factory()->create(['name' => 'Fallback Frog']);

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('search', ['q' => 'Fallback']))
            ->assertOk()
            ->assertSee('data-search-group="animals"', false)
            ->assertSee('Fallback Frog')
            ->assertSee(route('animals.index', ['q' => 'Fallback']), false);
    }

    public function test_layout_has_top_bar_search_and_keyboard_palette(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('action="'.route('search').'"', false)
            ->assertSee('data-palette', false)
            ->assertSee('role="combobox"', false)
            ->assertSee('role="listbox"', false)
            ->assertSee('data-search-url="'.route('search.json').'"', false);
    }

    public function test_search_responds_within_300ms_on_seeded_data(): void
    {
        Animal::factory()->count(500)->create();
        Person::factory()->count(500)->create();
        Animal::factory()->create(['name' => 'Needle Monitor', 'identifying_code' => '900118000999999']);

        $user = User::factory()->staff()->create();
        $this->actingAs($user)->getJson(route('search.json', ['q' => 'warm']))->assertOk();

        foreach (['Needle', '900118000999999', 'example'] as $term) {
            $started = hrtime(true);
            $this->actingAs($user)->getJson(route('search.json', ['q' => $term]))->assertOk();
            $elapsedMs = (hrtime(true) - $started) / 1_000_000;

            $this->assertLessThan(300, $elapsedMs, "Search for {$term} took {$elapsedMs} ms");
        }
    }
}
