<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAdoptableTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_browse_adoptable_list_and_detail(): void
    {
        $listed = Animal::factory()->create([
            'name' => 'Ziggy',
            'species' => 'Corn snake',
            'is_adoptable' => true,
            'non_shelter' => false,
            'deceased_at' => null,
            'colour' => 'normal',
        ]);
        Animal::factory()->create([
            'name' => 'Hidden',
            'species' => 'Bearded dragon',
            'is_adoptable' => false,
        ]);
        Animal::factory()->create([
            'name' => 'Late',
            'species' => 'Tortoise',
            'is_adoptable' => true,
            'deceased_at' => '2026-01-01',
        ]);
        Animal::factory()->create([
            'name' => 'Private',
            'species' => 'Parrot',
            'is_adoptable' => true,
            'non_shelter' => true,
        ]);

        $this->get(route('public.adopt.index'))
            ->assertOk()
            ->assertSee('Ziggy', false)
            ->assertSee('Corn snake', false)
            ->assertDontSee('Hidden', false)
            ->assertDontSee('Late', false)
            ->assertDontSee('Private', false);

        $this->get(route('public.adopt.show', $listed))
            ->assertOk()
            ->assertSee('Ziggy', false)
            ->assertSee('Corn snake', false)
            ->assertSee('normal', false)
            ->assertDontSee('CITES', false)
            ->assertDontSee('DWA', false);

        $this->get(route('public.adopt.embed'))
            ->assertOk()
            ->assertSee('Ziggy', false);
    }

    public function test_guest_gets_404_for_non_adoptable_animal(): void
    {
        $animal = Animal::factory()->create([
            'name' => 'NotListed',
            'is_adoptable' => false,
        ]);

        $this->get(route('public.adopt.show', $animal))->assertNotFound();
    }

    public function test_guest_can_read_adoptable_api(): void
    {
        $animal = Animal::factory()->create([
            'name' => 'Ruby',
            'species' => 'Ball python',
            'sex' => 'female',
            'colour' => 'pastel',
            'age_years' => 3,
            'is_adoptable' => true,
        ]);
        Animal::factory()->create([
            'name' => 'Skip',
            'is_adoptable' => false,
        ]);

        $this->getJson('/api/v1/adoptable')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Ruby')
            ->assertJsonPath('data.0.species', 'Ball python')
            ->assertJsonPath('data.0.sex', 'female')
            ->assertJsonPath('data.0.colour', 'pastel')
            ->assertJsonPath('data.0.age_years', 3)
            ->assertJsonPath('data.0.url', route('public.adopt.show', $animal))
            ->assertJsonMissing(['name' => 'Skip'])
            ->assertJsonMissingPath('data.0.location')
            ->assertJsonMissingPath('data.0.cites')
            ->assertJsonMissingPath('data.0.dwa');

        $this->getJson('/api/v1/adoptable/'.$animal->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'Ruby')
            ->assertJsonPath('data.species', 'Ball python');

        $hidden = Animal::factory()->create(['is_adoptable' => false]);
        $this->getJson('/api/v1/adoptable/'.$hidden->id)->assertNotFound();
    }

    public function test_staff_can_toggle_is_adoptable(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create([
            'name' => 'Toggle',
            'species' => 'Iguana',
            'sex' => 'unknown',
            'is_adoptable' => false,
        ]);

        $this->actingAs($staff)
            ->put(route('animals.update', $animal), [
                'name' => 'Toggle',
                'species' => 'Iguana',
                'sex' => 'unknown',
                'is_adoptable' => '1',
            ])
            ->assertRedirect(route('animals.show', $animal));

        $animal->refresh();
        $this->assertTrue($animal->is_adoptable);

        $this->actingAs($staff)
            ->get(route('animals.show', $animal))
            ->assertOk()
            ->assertSee('Available for adoption', false)
            ->assertSee('Listed for public adoption', false);
    }

    public function test_guest_still_redirected_from_staff_animals(): void
    {
        $this->get(route('animals.index'))
            ->assertRedirect(route('login'));
    }
}
