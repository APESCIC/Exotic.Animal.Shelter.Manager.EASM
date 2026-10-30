<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Animal;
use App\Models\Enclosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnimalTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_animal_with_photo_and_exotic_fields(): void
    {
        Storage::fake('public');

        $staff = User::factory()->staff()->create();
        $enclosure = Enclosure::factory()->create(['name' => 'ENC-12']);

        $response = $this->actingAs($staff)->post(route('animals.store'), [
            'name' => 'Spike',
            'species' => 'Bearded dragon',
            'breed_type' => 'Pogona vitticeps',
            'sex' => 'male',
            'date_of_birth' => '2020-05-01',
            'age_years' => 6,
            'colour' => 'Sand',
            'identifying_code' => 'EX-1001',
            'flags' => 'special-diet',
            'location' => 'Reptile House',
            'bonded_animals' => 'Dot',
            'entry_reason' => 'Surrender',
            'non_shelter' => '0',
            'enclosure_id' => $enclosure->id,
            'cites' => 'Appendix II',
            'dwa' => 'No',
            'primary_photo' => UploadedFile::fake()->createWithContent(
                'spike.jpg',
                (string) base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAGfAP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAQUCf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQMBAT8Bf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQIBAT8Bf//EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEABj8Cf//EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAT8hf//Z', true)
            ),
        ]);

        $animal = Animal::query()->where('identifying_code', 'EX-1001')->first();
        $this->assertNotNull($animal);
        $response->assertRedirect(route('animals.show', $animal));

        $this->assertSame('Spike', $animal->name);
        $this->assertSame('Bearded dragon', $animal->species);
        $this->assertSame('ENC-12', $animal->enclosure?->name);
        $this->assertSame('Appendix II', $animal->cites);
        $this->assertSame('No', $animal->dwa);
        $this->assertNotNull($animal->primary_photo_path);
        Storage::disk('public')->assertExists($animal->primary_photo_path);
    }

    public function test_staff_can_search_and_filter_by_location(): void
    {
        $staff = User::factory()->staff()->create();
        Animal::factory()->create(['name' => 'Kaa', 'species' => 'Corn snake', 'location' => 'Quarantine A']);
        Animal::factory()->create(['name' => 'Polly', 'species' => 'African grey parrot', 'location' => 'Aviary 1']);

        $this->actingAs($staff)
            ->get(route('animals.index', ['q' => 'Corn']))
            ->assertOk()
            ->assertSee('Kaa', false)
            ->assertDontSee('Polly', false);

        $this->actingAs($staff)
            ->get(route('animals.index', ['location' => 'Aviary 1']))
            ->assertOk()
            ->assertSee('Polly', false)
            ->assertDontSee('Kaa', false);
    }

    public function test_shelter_view_groups_animals_by_location(): void
    {
        $staff = User::factory()->staff()->create();
        Animal::factory()->create(['name' => 'Tess', 'location' => 'Outdoor Pad']);
        Animal::factory()->create(['name' => 'Uno', 'location' => null]);

        $this->actingAs($staff)
            ->get(route('animals.shelter'))
            ->assertOk()
            ->assertSee('Outdoor Pad', false)
            ->assertSee('Tess', false)
            ->assertSee('Unassigned', false)
            ->assertSee('Uno', false);
    }

    public function test_readonly_cannot_create_animals_but_can_view(): void
    {
        $readonly = User::factory()->readonly()->create();
        $animal = Animal::factory()->create(['name' => 'Visible']);

        $this->actingAs($readonly)
            ->get(route('animals.create'))
            ->assertForbidden();

        $this->actingAs($readonly)
            ->post(route('animals.store'), [
                'name' => 'Blocked',
                'species' => 'Iguana',
                'sex' => 'unknown',
            ])
            ->assertForbidden();

        $this->actingAs($readonly)
            ->get(route('animals.show', $animal))
            ->assertOk()
            ->assertSee('Visible', false);
    }

    public function test_volunteer_cannot_edit_animals(): void
    {
        $volunteer = User::factory()->state(['role' => UserRole::Volunteer])->create();
        $animal = Animal::factory()->create(['name' => 'Locked']);

        $this->actingAs($volunteer)
            ->get(route('animals.edit', $animal))
            ->assertForbidden();
    }
}
