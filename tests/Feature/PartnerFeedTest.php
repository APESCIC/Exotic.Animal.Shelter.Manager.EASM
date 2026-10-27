<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerFeedTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'partner-feed-test-token-0123456789abcdef';

    public function test_feed_is_not_found_until_switched_on_with_the_right_token(): void
    {
        Animal::factory()->create(['name' => 'Ruby', 'is_adoptable' => true]);
        Setting::query()->create(['organisation_name' => 'Test Shelter', 'partner_feed_token' => self::TOKEN]);

        $this->get(route('feeds.adoptable', ['format' => 'json', 'token' => self::TOKEN]))->assertNotFound();

        Setting::current()->update(['partner_feed_enabled' => true]);

        $this->get(route('feeds.adoptable', ['format' => 'json']))->assertNotFound();
        $this->get(route('feeds.adoptable', ['format' => 'json', 'token' => 'wrong']))->assertNotFound();
        $this->get('/feeds/adoptable.xml?token='.self::TOKEN)->assertNotFound();
        $this->get(route('feeds.adoptable', ['format' => 'json', 'token' => self::TOKEN]))->assertOk();
    }

    public function test_json_feed_lists_only_public_safe_adoptable_animals(): void
    {
        $this->enableFeed();
        Animal::factory()->create([
            'name' => 'Ruby',
            'species' => 'Ball python',
            'is_adoptable' => true,
            'cites' => 'Appendix II',
            'dwa' => 'DWA-1',
            'location' => 'Reptile room',
        ]);
        Animal::factory()->create(['name' => 'Hidden', 'is_adoptable' => false]);
        Animal::factory()->create(['name' => 'Late', 'is_adoptable' => true, 'deceased_at' => '2026-01-01']);

        $this->getJson(route('feeds.adoptable', ['format' => 'json', 'token' => self::TOKEN]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('organisation', 'Test Shelter')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.name', 'Ruby')
            ->assertJsonPath('data.0.species', 'Ball python')
            ->assertJsonMissingPath('data.0.cites')
            ->assertJsonMissingPath('data.0.dwa')
            ->assertJsonMissingPath('data.0.location')
            ->assertJsonMissing(['name' => 'Hidden'])
            ->assertJsonMissing(['name' => 'Late']);
    }

    public function test_csv_feed_has_a_header_row_and_neutralises_formulas(): void
    {
        $this->enableFeed();
        Animal::factory()->create(['name' => 'Ziggy', 'species' => 'Corn snake', 'colour' => '=HYPERLINK("x")', 'is_adoptable' => true]);

        $response = $this->get(route('feeds.adoptable', ['format' => 'csv', 'token' => self::TOKEN]));

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
        $rows = array_map('str_getcsv', array_filter(explode("\n", (string) $response->getContent())));
        $this->assertSame('id', $rows[0][0]);
        $this->assertContains('species', $rows[0]);
        $this->assertSame('Ziggy', $rows[1][1]);
        $this->assertSame('Corn snake', $rows[1][2]);
        $this->assertContains("'=HYPERLINK(\"x\")", $rows[1]);
    }

    public function test_rss_feed_is_valid_xml_with_one_item_per_animal(): void
    {
        $this->enableFeed();
        $animal = Animal::factory()->create(['name' => 'Ziggy & Co', 'species' => 'Corn snake', 'is_adoptable' => true]);

        $response = $this->get(route('feeds.adoptable', ['format' => 'rss', 'token' => self::TOKEN]));

        $response->assertOk();
        $this->assertStringStartsWith('application/rss+xml', (string) $response->headers->get('Content-Type'));
        $xml = simplexml_load_string((string) $response->getContent());
        $this->assertNotFalse($xml);
        $this->assertCount(1, $xml->channel->item);
        $this->assertSame('Ziggy & Co (Corn snake)', (string) $xml->channel->item[0]->title);
        $this->assertSame(route('public.adopt.show', $animal), (string) $xml->channel->item[0]->link);
    }

    public function test_admin_can_switch_on_regenerate_and_download(): void
    {
        $admin = User::factory()->admin()->create();
        Animal::factory()->create(['name' => 'Ruby', 'is_adoptable' => true]);

        $this->actingAs($admin)
            ->get(route('admin.publishing.edit'))
            ->assertOk()
            ->assertSee('The feed is off', false);

        $this->actingAs($admin)
            ->put(route('admin.publishing.update'), ['partner_feed_enabled' => '1'])
            ->assertRedirect(route('admin.publishing.edit'));

        $settings = Setting::current();
        $this->assertTrue($settings->partner_feed_enabled);
        $firstToken = $settings->partner_feed_token;
        $this->assertNotEmpty($firstToken);

        $this->actingAs($admin)
            ->get(route('admin.publishing.edit'))
            ->assertSee(route('feeds.adoptable', ['format' => 'csv', 'token' => $firstToken]), false);

        $this->actingAs($admin)
            ->post(route('admin.publishing.regenerate'))
            ->assertRedirect(route('admin.publishing.edit'));

        $secondToken = Setting::current()->partner_feed_token;
        $this->assertNotSame($firstToken, $secondToken);
        $this->get(route('feeds.adoptable', ['format' => 'json', 'token' => $firstToken]))->assertNotFound();
        $this->get(route('feeds.adoptable', ['format' => 'json', 'token' => $secondToken]))->assertOk();

        $download = $this->actingAs($admin)->get(route('admin.publishing.csv'));
        $download->assertOk();
        $this->assertStringContainsString('attachment', (string) $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Ruby', (string) $download->getContent());

        $this->actingAs($admin)
            ->put(route('admin.publishing.update'), ['partner_feed_enabled' => '0']);
        $this->get(route('feeds.adoptable', ['format' => 'json', 'token' => $secondToken]))->assertNotFound();
    }

    public function test_staff_cannot_manage_the_feed(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('admin.publishing.edit'))->assertForbidden();
        $this->actingAs($staff)->put(route('admin.publishing.update'), ['partner_feed_enabled' => '1'])->assertForbidden();
        $this->actingAs($staff)->post(route('admin.publishing.regenerate'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.publishing.csv'))->assertForbidden();
    }

    private function enableFeed(): void
    {
        Setting::query()->create([
            'organisation_name' => 'Test Shelter',
            'partner_feed_enabled' => true,
            'partner_feed_token' => self::TOKEN,
        ]);
    }
}
