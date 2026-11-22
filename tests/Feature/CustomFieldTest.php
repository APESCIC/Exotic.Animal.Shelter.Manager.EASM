<?php

namespace Tests\Feature;

use App\Enums\CustomFieldType;
use App\Enums\UserRole;
use App\Models\Animal;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\LookupList;
use App\Models\User;
use Database\Seeders\LookupSeeder;
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
                'entity' => 'animal',
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

    public function test_lookup_and_multi_lookup_fields_save_on_animals(): void
    {
        $this->seed(LookupSeeder::class);
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $colour = LookupList::key('colour');
        $flags = LookupList::key('flags');
        $this->assertNotNull($colour);
        $this->assertNotNull($flags);

        $this->actingAs($admin)
            ->post(route('admin.custom-fields.store'), [
                'entity' => 'animal',
                'label' => 'Preferred colour',
                'type' => CustomFieldType::Lookup->value,
                'lookup_list_id' => $colour->id,
                'active' => '1',
                'show_on_new_record' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.custom-fields.store'), [
                'entity' => 'animal',
                'label' => 'Care tags',
                'type' => CustomFieldType::MultiLookup->value,
                'lookup_list_id' => $flags->id,
                'active' => '1',
                'show_on_new_record' => '1',
            ])
            ->assertRedirect();

        $lookup = CustomFieldDefinition::query()->where('slug', 'preferred-colour')->firstOrFail();
        $multi = CustomFieldDefinition::query()->where('slug', 'care-tags')->firstOrFail();
        $animal = Animal::factory()->create(['species' => 'Python']);

        $this->actingAs($staff)
            ->put(route('animals.update', $animal), [
                'name' => $animal->name,
                'species' => 'Python',
                'sex' => $animal->sex->value,
                'custom_fields' => [
                    $lookup->id => 'Yellow',
                    $multi->id => ['Bite risk', 'Special diet'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('custom_field_values', [
            'definition_id' => $lookup->id,
            'animal_id' => $animal->id,
            'value' => 'Yellow',
        ]);

        $multiValue = CustomFieldValue::query()
            ->where('definition_id', $multi->id)
            ->where('animal_id', $animal->id)
            ->value('value');
        $this->assertSame(['Bite risk', 'Special diet'], json_decode((string) $multiValue, true));
    }

    public function test_mandatory_field_blocks_save(): void
    {
        $staff = User::factory()->staff()->create();
        $definition = CustomFieldDefinition::factory()->create([
            'label' => 'Must fill',
            'is_mandatory' => true,
            'type' => CustomFieldType::Text,
        ]);
        $animal = Animal::factory()->create(['species' => 'Gecko']);

        $this->actingAs($staff)
            ->from(route('animals.edit', $animal))
            ->put(route('animals.update', $animal), [
                'name' => $animal->name,
                'species' => 'Gecko',
                'sex' => $animal->sex->value,
                'custom_fields' => [
                    $definition->id => '',
                ],
            ])
            ->assertRedirect(route('animals.edit', $animal))
            ->assertSessionHasErrors('custom_fields.'.$definition->id);
    }

    public function test_species_filter_and_show_on_new_and_retire(): void
    {
        $staff = User::factory()->staff()->create();
        $filtered = CustomFieldDefinition::factory()->create([
            'label' => 'Dragon only',
            'species_filter' => 'Bearded dragon',
            'type' => CustomFieldType::Text,
            'show_on_new_record' => true,
        ]);
        $hiddenOnNew = CustomFieldDefinition::factory()->create([
            'label' => 'Edit only note',
            'show_on_new_record' => false,
            'type' => CustomFieldType::Text,
        ]);
        $retired = CustomFieldDefinition::factory()->create([
            'label' => 'Old field',
            'active' => false,
            'type' => CustomFieldType::Text,
        ]);

        $dragon = Animal::factory()->create(['species' => 'Bearded dragon']);
        $snake = Animal::factory()->create(['species' => 'Corn snake']);
        CustomFieldValue::factory()->create([
            'definition_id' => $retired->id,
            'animal_id' => $dragon->id,
            'value' => 'kept',
        ]);

        $this->actingAs($staff)
            ->get(route('animals.edit', $dragon))
            ->assertOk()
            ->assertSee('Dragon only', false)
            ->assertDontSee('name="custom_fields['.$retired->id.']"', false);

        $this->actingAs($staff)
            ->get(route('animals.edit', $snake))
            ->assertOk()
            ->assertDontSee('Dragon only', false);

        $this->actingAs($staff)
            ->get(route('animals.create'))
            ->assertOk()
            ->assertSee('Dragon only', false)
            ->assertDontSee('Edit only note', false);

        $this->actingAs($staff)
            ->get(route('animals.show', $dragon))
            ->assertOk()
            ->assertSee('Old field', false)
            ->assertSee('kept', false);

        $this->actingAs($staff)
            ->get(route('animals.edit', $dragon))
            ->assertOk()
            ->assertSee('Edit only note', false);
    }

    public function test_tooltip_is_exposed_with_aria_describedby(): void
    {
        $staff = User::factory()->staff()->create();
        $definition = CustomFieldDefinition::factory()->create([
            'label' => 'Humidity',
            'tooltip' => 'Target daytime humidity',
            'type' => CustomFieldType::Text,
        ]);
        $animal = Animal::factory()->create();

        $this->actingAs($staff)
            ->get(route('animals.edit', $animal))
            ->assertOk()
            ->assertSee('aria-describedby="custom_field_'.$definition->id.'_hint"', false)
            ->assertSee('Target daytime humidity', false);
    }
}
