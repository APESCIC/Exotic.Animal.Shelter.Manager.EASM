<?php

namespace Tests\Feature;

use App\Enums\MedicalRecordType;
use App\Models\Animal;
use App\Models\DiaryTask;
use App\Models\Document;
use App\Models\Enclosure;
use App\Models\MedicalRecord;
use App\Models\RotaShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_today_lists_my_shift_due_tasks_and_medical_with_touch_actions(): void
    {
        Carbon::setTestNow('2026-11-05 08:00:00');
        $staff = User::factory()->staff()->create();
        $iggy = Animal::factory()->create(['name' => 'Iggy']);
        $due = DiaryTask::factory()->for($iggy)->create(['title' => 'Mist enclosure', 'due_on' => '2026-11-05']);
        $overdue = DiaryTask::factory()->general()->create(['title' => 'Order locusts', 'due_on' => '2026-11-01']);
        DiaryTask::factory()->create(['title' => 'Next week job', 'due_on' => '2026-11-12']);
        DiaryTask::factory()->completed()->create(['title' => 'Already done', 'due_on' => '2026-11-05']);
        $wormer = MedicalRecord::factory()->for($iggy)->create(['type' => MedicalRecordType::Treatment, 'name' => 'Wormer', 'due_on' => '2026-11-04']);
        MedicalRecord::factory()->for($iggy)->create(['name' => 'Future jab', 'due_on' => '2026-12-01']);
        $shift = RotaShift::factory()->for($staff)->create(['starts_at' => '2026-11-05 09:00', 'ends_at' => '2026-11-05 17:00', 'area' => 'Reptile house']);
        RotaShift::factory()->create(['starts_at' => '2026-11-05 09:00', 'ends_at' => '2026-11-05 17:00']);

        $response = $this->actingAs($staff)->get(route('mobile.index'))->assertOk();

        $response->assertSee('data-mobile-shift="'.$shift->id.'"', false)
            ->assertSee('09:00–17:00')
            ->assertSee('data-mobile-task="'.$due->id.'"', false)
            ->assertSee('data-mobile-task="'.$overdue->id.'"', false)
            ->assertDontSee('Next week job')
            ->assertDontSee('Already done')
            ->assertSee('data-mobile-medical="'.$wormer->id.'"', false)
            ->assertDontSee('Future jab')
            ->assertSee('action="'.route('diary.complete', $due).'"', false)
            ->assertSee('action="'.route('medical.given', $wormer).'"', false)
            ->assertSee('data-offline-queue', false);
        $this->assertSame(1, substr_count($response->getContent(), 'data-mobile-shift='));
    }

    public function test_volunteers_see_tasks_without_actions_they_cannot_take(): void
    {
        $task = DiaryTask::factory()->create(['due_on' => today()]);

        $this->actingAs(User::factory()->volunteer()->create())
            ->get(route('mobile.index'))
            ->assertOk()
            ->assertSee('data-mobile-task="'.$task->id.'"', false)
            ->assertDontSee('action="'.route('diary.complete', $task).'"', false);
    }

    public function test_mark_medical_given(): void
    {
        Carbon::setTestNow('2026-11-05 10:00:00');
        $record = MedicalRecord::factory()->create(['due_on' => '2026-11-05', 'given_on' => null]);

        $this->actingAs(User::factory()->readonly()->create())
            ->post(route('medical.given', $record))
            ->assertForbidden();

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('medical.given', $record), ['given_on' => '2026-11-04'])
            ->assertRedirect()
            ->assertSessionHas('status', $record->name.' marked as given.');
        $this->assertSame('2026-11-04', $record->fresh()->given_on->toDateString());

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('medical.given', $record), ['given_on' => '2026-11-05']);
        $this->assertSame('2026-11-04', $record->fresh()->given_on->toDateString());

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('medical.given', MedicalRecord::factory()->create(['given_on' => null])), ['given_on' => '2026-11-06'])
            ->assertSessionHasErrors('given_on');
    }

    public function test_animal_page_offers_log_photo_tasks_and_signing(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create(['name' => 'Shelly']);
        $document = Document::factory()->create(['animal_id' => $animal->id, 'title' => 'Fostering agreement', 'signed_at' => null]);

        $this->actingAs($staff)
            ->get(route('mobile.animals.show', $animal))
            ->assertOk()
            ->assertSee('action="'.route('animals.observations.store', $animal).'"', false)
            ->assertSee('capture="environment"', false)
            ->assertSee('action="'.route('animals.media.store', $animal).'"', false)
            ->assertSee(route('documents.show', $document))
            ->assertSee('Fostering agreement');

        $this->actingAs(User::factory()->readonly()->create())
            ->get(route('mobile.animals.show', $animal))
            ->assertOk()
            ->assertDontSee('capture="environment"', false);
    }

    public function test_photo_upload_from_the_phone(): void
    {
        Storage::fake('public');
        $animal = Animal::factory()->create();

        $this->actingAs(User::factory()->staff()->create())
            ->withHeaders(['Accept' => 'application/json', 'X-Client-Request-Id' => 'feedface-0000-4000-8000-000000000010'])
            ->post(route('animals.media.store', $animal), ['media_file' => UploadedFile::fake()->image('camera.jpg')])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $this->assertSame(1, $animal->media()->count());
    }

    public function test_animal_search_lists_animals_in_care(): void
    {
        Animal::factory()->create(['name' => 'Basil', 'species' => 'Bearded dragon']);
        Animal::factory()->create(['name' => 'Gone', 'deceased_at' => now()]);

        $this->actingAs(User::factory()->volunteer()->create())
            ->get(route('mobile.animals', ['q' => 'bearded']))
            ->assertOk()
            ->assertSee('Basil')
            ->assertDontSee('Gone');
    }

    public function test_enclosure_page_records_readings_and_lists_animals(): void
    {
        $enclosure = Enclosure::factory()->create(['name' => 'Vivarium 3']);
        Animal::factory()->create(['name' => 'Inside', 'enclosure_id' => $enclosure->id]);

        $this->actingAs(User::factory()->volunteer()->create())
            ->get(route('mobile.enclosures.show', $enclosure))
            ->assertOk()
            ->assertSee('Vivarium 3')
            ->assertSee('Inside')
            ->assertSee('action="'.route('enclosures.logs.store', $enclosure).'"', false)
            ->assertSee('data-offline-now', false);
    }

    public function test_qr_labels_link_to_the_mobile_enclosure_page(): void
    {
        $enclosure = Enclosure::factory()->create(['name' => 'Aviary 1']);

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('enclosures.qr-labels'))
            ->assertOk()
            ->assertSee('data-qr-label="'.$enclosure->id.'"', false)
            ->assertSee('<svg', false)
            ->assertSee(route('mobile.enclosures.show', $enclosure));

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('enclosures.qr', $enclosure))
            ->assertOk()
            ->assertSee('Aviary 1');

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('mobile.scan'))
            ->assertOk()
            ->assertSee('data-qr-scan', false)
            ->assertSee(route('mobile.enclosures.show', $enclosure));
    }

    public function test_web_manifest_and_service_worker_are_served(): void
    {
        $this->get(route('webmanifest'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJson([
                'start_url' => route('mobile.index'),
                'display' => 'standalone',
            ]);

        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));
        $this->assertFileExists(public_path('icons/easm-192.png'));
        $this->assertFileExists(public_path('icons/easm-512.png'));

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('home'))
            ->assertSee('rel="manifest"', false)
            ->assertSee('name="csrf-token"', false)
            ->assertSee('data-offline-status', false);
    }
}
