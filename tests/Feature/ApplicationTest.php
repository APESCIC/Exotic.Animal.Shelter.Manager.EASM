<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\PersonCategory;
use App\Models\Animal;
use App\Models\Application;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_submit_adopter_volunteer_and_foster_applications(): void
    {
        foreach ([
            ApplicationType::Adopter,
            ApplicationType::Volunteer,
            ApplicationType::Foster,
        ] as $type) {
            $this->post(route('public.apply.store', $type->value), [
                'name' => $type->label().' Applicant',
                'email' => $type->value.'@example.test',
                'phone' => '07123456789',
                'address_line1' => '1 Shelter Lane',
                'town_city' => 'Bristol',
                'postcode' => 'BS1 1AA',
                'message' => 'Please consider me.',
            ])->assertRedirect(route('public.apply.thanks'));

            $application = Application::query()->where('email', $type->value.'@example.test')->first();
            $this->assertNotNull($application);
            $this->assertSame($type, $application->type);
            $this->assertSame(ApplicationStatus::Submitted, $application->status);
        }

        $this->get(route('public.apply.thanks'))->assertOk();
    }

    public function test_guest_can_apply_for_specific_adoptable_animal(): void
    {
        $animal = Animal::factory()->create([
            'name' => 'Spike',
            'species' => 'Bearded dragon',
            'is_adoptable' => true,
        ]);

        $this->get(route('public.adopt.apply', $animal))
            ->assertOk()
            ->assertSee('Spike', false);

        $this->post(route('public.adopt.apply.store', $animal), [
            'name' => 'Alex Adopter',
            'email' => 'alex@example.test',
            'message' => 'I would love to home Spike.',
        ])->assertRedirect(route('public.apply.thanks'));

        $application = Application::query()->where('email', 'alex@example.test')->first();
        $this->assertNotNull($application);
        $this->assertSame(ApplicationType::Adopter, $application->type);
        $this->assertSame($animal->id, $application->animal_id);
        $this->assertSame(ApplicationStatus::Submitted, $application->status);
    }

    public function test_guest_cannot_open_staff_applications_queue(): void
    {
        $this->get(route('applications.index'))
            ->assertRedirect(route('login'));
    }

    public function test_staff_can_review_and_accept_application_creating_person(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create(['name' => 'Kaa', 'is_adoptable' => true]);
        $application = Application::factory()->adopter()->create([
            'name' => 'Sam Foster-Hope',
            'email' => 'sam@example.test',
            'phone' => '07000000001',
            'address_line1' => '2 Quarantine Road',
            'town_city' => 'Exeter',
            'county' => 'Devon',
            'postcode' => 'EX1 1AA',
            'message' => 'Experienced with snakes.',
            'animal_id' => $animal->id,
            'status' => ApplicationStatus::Submitted,
        ]);

        $this->actingAs($staff)
            ->get(route('applications.index'))
            ->assertOk()
            ->assertSee('Sam Foster-Hope', false)
            ->assertSee('Kaa', false);

        $this->actingAs($staff)
            ->patch(route('applications.status', $application), [
                'status' => ApplicationStatus::UnderReview->value,
                'status_note' => 'Checking references',
            ])
            ->assertRedirect(route('applications.show', $application));

        $application->refresh();
        $this->assertSame(ApplicationStatus::UnderReview, $application->status);
        $this->assertSame('Checking references', $application->status_note);
        $this->assertSame($staff->id, $application->reviewed_by);
        $this->assertNotNull($application->reviewed_at);

        $this->actingAs($staff)
            ->post(route('applications.accept', $application), [
                'status_note' => 'Homecheck passed',
            ])
            ->assertRedirect(route('applications.show', $application));

        $application->refresh();
        $this->assertSame(ApplicationStatus::Accepted, $application->status);
        $this->assertNotNull($application->person_id);

        $person = Person::query()->find($application->person_id);
        $this->assertNotNull($person);
        $this->assertSame('Sam Foster-Hope', $person->name);
        $this->assertSame(PersonCategory::Adopter, $person->category);
        $this->assertSame('sam@example.test', $person->email);
        $this->assertSame('2 Quarantine Road', $person->address_line1);
        $this->assertSame('EX1 1AA', $person->postcode);
        $this->assertSame(1, Animal::query()->count());
    }

    public function test_accept_maps_volunteer_and_foster_categories(): void
    {
        $staff = User::factory()->staff()->create();

        $volunteer = Application::factory()->volunteer()->create([
            'name' => 'Val Volunteer',
            'email' => 'val@example.test',
        ]);
        $foster = Application::factory()->foster()->create([
            'name' => 'Fran Foster',
            'email' => 'fran@example.test',
        ]);

        $this->actingAs($staff)->post(route('applications.accept', $volunteer));
        $this->actingAs($staff)->post(route('applications.accept', $foster));

        $this->assertSame(PersonCategory::Volunteer, $volunteer->fresh()->person->category);
        $this->assertSame(PersonCategory::Foster, $foster->fresh()->person->category);
    }

    public function test_staff_can_reject_application(): void
    {
        $staff = User::factory()->staff()->create();
        $application = Application::factory()->create([
            'name' => 'Reject Me',
            'status' => ApplicationStatus::Submitted,
        ]);

        $this->actingAs($staff)
            ->patch(route('applications.status', $application), [
                'status' => ApplicationStatus::Rejected->value,
                'status_note' => 'Not a fit right now',
            ])
            ->assertRedirect(route('applications.show', $application));

        $application->refresh();
        $this->assertSame(ApplicationStatus::Rejected, $application->status);
        $this->assertNull($application->person_id);
        $this->assertSame(0, Person::query()->count());
    }

    public function test_readonly_can_view_but_not_change_applications(): void
    {
        $readonly = User::factory()->readonly()->create();
        $application = Application::factory()->create(['name' => 'Visible App']);

        $this->actingAs($readonly)
            ->get(route('applications.index'))
            ->assertOk()
            ->assertSee('Visible App', false);

        $this->actingAs($readonly)
            ->get(route('applications.show', $application))
            ->assertOk();

        $this->actingAs($readonly)
            ->patch(route('applications.status', $application), [
                'status' => ApplicationStatus::UnderReview->value,
            ])
            ->assertForbidden();

        $this->actingAs($readonly)
            ->post(route('applications.accept', $application))
            ->assertForbidden();
    }

    public function test_volunteer_cannot_accept_applications(): void
    {
        $volunteer = User::factory()->volunteer()->create();
        $application = Application::factory()->create();

        $this->actingAs($volunteer)
            ->post(route('applications.accept', $application))
            ->assertForbidden();
    }
}
