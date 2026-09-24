<?php

namespace Tests\Feature;

use App\Enums\CustomFieldType;
use App\Enums\UserRole;
use App\Models\Animal;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_definition_and_staff_can_save_value(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();

        $this->actingAs($admin)
            ->post(route('admin.custom-fields.store'), [
                'label' => 'UVB hours',
                'type' => CustomFieldType::Text->value,
                'sort_order' => 1,
                'active' => '1',
            ])
            ->assertRedirect(route('admin.custom-fields.index'));

        $definition = CustomFieldDefinition::query()->first();
        $this->assertNotNull($definition);
        $this->assertSame('UVB hours', $definition->label);
        $this->assertSame('uvb-hours', $definition->slug);
        $this->assertSame(CustomFieldType::Text, $definition->type);

        $animal = Animal::factory()->create(['name' => 'Spike', 'species' => 'Bearded dragon']);

        $this->actingAs($staff)
            ->put(route('animals.update', $animal), [
                'name' => 'Spike',
                'species' => 'Bearded dragon',
                'sex' => 'unknown',
                'custom_fields' => [
                    $definition->id => '12 hours',
                ],
            ])
            ->assertRedirect(route('animals.show', $animal));

        $this->assertDatabaseHas('custom_field_values', [
            'definition_id' => $definition->id,
            'animal_id' => $animal->id,
            'value' => '12 hours',
        ]);

        $this->actingAs($staff)
            ->get(route('animals.show', $animal))
            ->assertOk()
            ->assertSee('Custom fields', false)
            ->assertSee('UVB hours', false)
            ->assertSee('12 hours', false);
    }

    public function test_animal_search_matches_custom_field_values(): void
    {
        $staff = User::factory()->staff()->create();
        $definition = CustomFieldDefinition::factory()->create([
            'label' => 'Microchip note',
            'slug' => 'microchip-note',
            'type' => CustomFieldType::Text,
        ]);
        $hit = Animal::factory()->create(['name' => 'Alpha', 'species' => 'Python']);
        $miss = Animal::factory()->create(['name' => 'Beta', 'species' => 'Boa']);

        CustomFieldValue::factory()->create([
            'definition_id' => $definition->id,
            'animal_id' => $hit->id,
            'value' => 'chip-Z9-unique',
        ]);
        CustomFieldValue::factory()->create([
            'definition_id' => $definition->id,
            'animal_id' => $miss->id,
            'value' => 'other',
        ]);

        $this->actingAs($staff)
            ->get(route('animals.index', ['q' => 'chip-Z9-unique']))
            ->assertOk()
            ->assertSee('Alpha', false)
            ->assertDontSee('Beta', false);
    }

    public function test_volunteer_cannot_manage_definitions(): void
    {
        $volunteer = User::factory()->state(['role' => UserRole::Volunteer])->create();
        $definition = CustomFieldDefinition::factory()->create(['label' => 'Humidity target']);

        $this->actingAs($volunteer)
            ->get(route('admin.custom-fields.index'))
            ->assertForbidden();

        $this->actingAs($volunteer)
            ->post(route('admin.custom-fields.store'), [
                'label' => 'Blocked',
                'type' => CustomFieldType::Text->value,
            ])
            ->assertForbidden();

        $this->actingAs($volunteer)
            ->get(route('admin.custom-fields.edit', $definition))
            ->assertForbidden();
    }
}
