<?php

namespace Tests\Feature;

use App\Models\User;
use App\Update\UpdateChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpdateCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'easm.updates.enabled' => true,
            'easm.updates.repository' => 'APESCIC/Exotic.Animal.Shelter.Manager.EASM',
            'easm.updates.channel' => 'stable',
            'easm.updates.check_interval_hours' => 24,
            'easm.updates.cron_token' => null,
        ]);
    }

    public function test_at_most_one_check_a_day_with_frozen_clock(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                'tag_name' => 'v0.9.0',
                'html_url' => 'https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/releases/tag/v0.9.0',
                'body' => "## What changed\nUpdater landing.",
                'prerelease' => false,
                'draft' => false,
            ], 200),
        ]);

        $admin = User::factory()->admin()->create();
        Carbon::setTestNow(Carbon::parse('2026-11-14 10:00:00', 'UTC'));

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        Http::assertSentCount(1);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('home'))->assertOk();
        Http::assertSentCount(1);

        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:01', 'UTC'));
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        Http::assertSentCount(2);

        Carbon::setTestNow();
    }

    public function test_banner_is_admin_only(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                'tag_name' => 'v0.9.0',
                'html_url' => 'https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/releases/tag/v0.9.0',
                'body' => 'Changelog for admins.',
                'prerelease' => false,
                'draft' => false,
            ], 200),
        ]);

        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();

        $adminPage = $this->actingAs($admin)->get(route('admin.dashboard'));
        $adminPage->assertOk()
            ->assertSee('data-update-banner', false)
            ->assertSee('Update', false)
            ->assertSee('0.9.0', false)
            ->assertSee('Changelog for admins', false);

        Cache::flush();
        Http::fake([
            'api.github.com/*' => Http::response([
                'tag_name' => 'v0.9.0',
                'html_url' => 'https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/releases/tag/v0.9.0',
                'body' => 'Changelog for admins.',
                'prerelease' => false,
                'draft' => false,
            ], 200),
        ]);

        $staffPage = $this->actingAs($staff)->get(route('home'));
        $staffPage->assertOk()
            ->assertDontSee('data-update-banner', false)
            ->assertDontSee('Changelog for admins', false);

        Http::assertSentCount(0);
    }

    public function test_blocked_network_shows_check_manually_without_error_page(): void
    {
        Http::fake([
            'api.github.com/*' => function () {
                throw new ConnectionException('Connection timed out');
            },
        ]);

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertOk()
            ->assertSee('data-update-banner', false)
            ->assertSee('Check manually', false)
            ->assertDontSee('Connection timed out', false)
            ->assertDontSee('Server Error', false);

        $status = app(UpdateChecker::class)->status();
        $this->assertNotNull($status);
        $this->assertFalse($status->ok);
        $this->assertFalse($status->updateAvailable);
    }

    public function test_web_cron_requires_token_and_can_force_check(): void
    {
        $this->get(route('cron.update-check'))->assertNotFound();

        config(['easm.updates.cron_token' => 'secret-cron-token']);

        $this->get(route('cron.update-check', ['token' => 'wrong']))->assertForbidden();

        Http::fake([
            'api.github.com/*' => Http::response([
                'tag_name' => 'v0.9.0',
                'html_url' => 'https://github.com/APESCIC/Exotic.Animal.Shelter.Manager.EASM/releases/tag/v0.9.0',
                'body' => 'Notes',
                'prerelease' => false,
                'draft' => false,
            ], 200),
        ]);

        $this->get(route('cron.update-check', ['token' => 'secret-cron-token']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('update_available', true)
            ->assertJsonPath('latest_version', '0.9.0');

        Http::assertSentCount(1);
    }
}
