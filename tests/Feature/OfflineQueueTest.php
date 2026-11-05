<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\ClientRequest;
use App\Models\DiaryTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflineQueueTest extends TestCase
{
    use RefreshDatabase;

    private const JSON = ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];

    public function test_replaying_a_queued_write_saves_it_once(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create();
        $headers = [...self::JSON, 'X-Client-Request-Id' => '3f0c9a52-1b7e-4c1a-9d55-8c2f61e0a001'];
        $payload = ['observed_on' => '2026-11-05', 'body' => 'Ate two locusts'];

        $this->actingAs($staff)
            ->withHeaders($headers)
            ->post(route('animals.observations.store', $animal), $payload)
            ->assertOk()
            ->assertJson(['saved' => true, 'message' => 'Observation recorded.']);

        $this->actingAs($staff)
            ->withHeaders($headers)
            ->post(route('animals.observations.store', $animal), $payload)
            ->assertOk()
            ->assertJson(['duplicate' => true]);

        $this->assertSame(1, $animal->observations()->count());
        $this->assertSame(1, ClientRequest::query()->count());
    }

    public function test_form_field_request_id_also_deduplicates_and_keeps_the_redirect(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create();
        $payload = ['observed_on' => '2026-11-05', 'body' => 'Shed complete', '_client_request_id' => 'c1d2e3f4-0000-4000-8000-000000000002'];

        $this->actingAs($staff)
            ->post(route('animals.observations.store', $animal), $payload)
            ->assertRedirect()
            ->assertSessionHas('status', 'Observation recorded.');

        $this->actingAs($staff)
            ->post(route('animals.observations.store', $animal), $payload)
            ->assertRedirect()
            ->assertSessionHas('status', 'Already saved.');

        $this->assertSame(1, $animal->observations()->count());
    }

    public function test_a_rejected_write_releases_the_id_so_a_corrected_retry_saves(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create();
        $headers = [...self::JSON, 'X-Client-Request-Id' => 'aaaabbbb-cccc-4ddd-8eee-ffff00000003'];

        $this->actingAs($staff)
            ->withHeaders($headers)
            ->post(route('animals.observations.store', $animal), ['observed_on' => '2026-11-05', 'body' => ''])
            ->assertUnprocessable();

        $this->assertSame(0, ClientRequest::query()->count());

        $this->actingAs($staff)
            ->withHeaders($headers)
            ->post(route('animals.observations.store', $animal), ['observed_on' => '2026-11-05', 'body' => 'Fixed'])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $this->assertSame(1, $animal->observations()->count());
    }

    public function test_forbidden_writes_are_not_recorded_and_ids_are_per_user(): void
    {
        $task = DiaryTask::factory()->create(['due_on' => today()]);
        $headers = [...self::JSON, 'X-Client-Request-Id' => '12345678-aaaa-4bbb-8ccc-000000000004'];

        $this->actingAs(User::factory()->readonly()->create())
            ->withHeaders($headers)
            ->post(route('diary.complete', $task))
            ->assertForbidden();

        $this->assertSame(0, ClientRequest::query()->count());
        $this->assertNull($task->fresh()->completed_at);

        $this->actingAs(User::factory()->staff()->create())
            ->withHeaders($headers)
            ->post(route('diary.complete', $task))
            ->assertOk()
            ->assertJson(['saved' => true, 'message' => 'Diary task marked complete.']);

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_requests_without_an_id_are_untouched(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create();

        $this->actingAs($staff)
            ->post(route('animals.observations.store', $animal), ['observed_on' => '2026-11-05', 'body' => 'One'])
            ->assertRedirect();
        $this->actingAs($staff)
            ->post(route('animals.observations.store', $animal), ['observed_on' => '2026-11-05', 'body' => 'One'])
            ->assertRedirect();

        $this->assertSame(2, $animal->observations()->count());
        $this->assertSame(0, ClientRequest::query()->count());
    }

    public function test_token_endpoint_returns_a_fresh_csrf_token(): void
    {
        $this->actingAs(User::factory()->volunteer()->create())
            ->getJson(route('mobile.token.json'))
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_token_endpoint_tells_a_signed_out_phone_to_sign_in(): void
    {
        $this->getJson(route('mobile.token.json'))->assertUnauthorized();
    }
}
