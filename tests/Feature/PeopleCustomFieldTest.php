<?php

namespace Tests\Feature;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Models\Animal;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\LookupList;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeopleCustomFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_people_fields_do_not_appear_on_animals(): void
    {
        $this->seed(LookupSeeder::class);
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $flags = LookupList::key('flags');

        $this->actingAs($admin)
            ->post(route('admin.custom-fields.store'), [
                'entity' => CustomFieldEntity::Person->value,
                'label' => 'Home type',
                'type' => CustomFieldType::Lookup->value,
                'lookup_list_id' => $flags->id,
                'active' => '1',
                'show_on_new_record' => '1',
            ])
            ->assertRedirect();

        $definition = CustomFieldDefinition::query()->where('slug', 'home-type')->firstOrFail();
        $this->assertSame(CustomFieldEntity::Person, $definition->entity);

        $animal = Animal::factory()->create();
        $this->actingAs($staff)
            ->get(route('animals.edit', $animal))
            ->assertOk()
            ->assertDontSee('Home type', false);
    }

    public function test_people_custom_fields_save_show_and_validate(): void
    {
        $staff = User::factory()->staff()->create();
        $definition = CustomFieldDefinition::factory()->create([
            'entity' => CustomFieldEntity::Person,
            'label' => 'Transport note',
            'type' => CustomFieldType::Text,
            'is_mandatory' => true,
        ]);

        $person = Person::factory()->create(['name' => 'Pat']);

        $this->actingAs($staff)
            ->from(route('people.edit', $person))
            ->put(route('people.update', $person), [
                'name' => 'Pat',
                'category' => 'adopter',
                'custom_fields' => [
                    $definition->id => '',
                ],
            ])
            ->assertRedirect(route('people.edit', $person))
            ->assertSessionHasErrors('custom_fields.'.$definition->id);

        $this->actingAs($staff)
            ->put(route('people.update', $person), [
                'name' => 'Pat',
                'category' => 'adopter',
                'custom_fields' => [
                    $definition->id => 'Has a van',
                ],
            ])
            ->assertRedirect(route('people.show', $person));

        $this->assertDatabaseHas('custom_field_values', [
            'definition_id' => $definition->id,
            'person_id' => $person->id,
            'animal_id' => null,
            'value' => 'Has a van',
        ]);

        $this->actingAs($staff)
            ->get(route('people.show', $person))
            ->assertOk()
            ->assertSee('Transport note', false)
            ->assertSee('Has a van', false);
    }

    public function test_retired_people_fields_keep_saved_values(): void
    {
        $staff = User::factory()->staff()->create();
        $definition = CustomFieldDefinition::factory()->create([
            'entity' => CustomFieldEntity::Person,
            'label' => 'Legacy note',
            'active' => false,
            'type' => CustomFieldType::Text,
        ]);
        $person = Person::factory()->create();
        CustomFieldValue::factory()->create([
            'definition_id' => $definition->id,
            'animal_id' => null,
            'person_id' => $person->id,
            'value' => 'still here',
        ]);

        $this->actingAs($staff)
            ->get(route('people.show', $person))
            ->assertOk()
            ->assertSee('Legacy note', false)
            ->assertSee('still here', false);

        $this->actingAs($staff)
            ->get(route('people.edit', $person))
            ->assertOk()
            ->assertDontSee('name="custom_fields['.$definition->id.']"', false);
    }

    public function test_animal_values_untouched_by_people_migration_shape(): void
    {
        $animal = Animal::factory()->create();
        $definition = CustomFieldDefinition::factory()->create([
            'entity' => CustomFieldEntity::Animal,
            'label' => 'UVB',
        ]);
        CustomFieldValue::factory()->create([
            'definition_id' => $definition->id,
            'animal_id' => $animal->id,
            'value' => '12h',
        ]);

        $this->assertDatabaseHas('custom_field_values', [
            'definition_id' => $definition->id,
            'animal_id' => $animal->id,
            'person_id' => null,
            'value' => '12h',
        ]);
    }
}
