<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    private const SVGS = ['images/pwa/easm.svg', 'images/pwa/easm-maskable.svg', 'images/easm-logo.svg'];

    public function test_logo_and_icon_svgs_are_small_hand_built_vectors(): void
    {
        foreach (self::SVGS as $path) {
            $svg = (string) file_get_contents(public_path($path));

            $this->assertLessThan(20 * 1024, strlen($svg), "$path is 20 KB or more");
            $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg"', $svg);
            $this->assertStringNotContainsString('<image', $svg, "$path embeds a raster image");
            $this->assertStringNotContainsString('base64', $svg, "$path embeds encoded data");
            $this->assertStringContainsString('aria-label="EASM"', $svg);
        }
    }

    public function test_manifest_offers_any_and_maskable_icons_that_exist(): void
    {
        $icons = collect($this->get(route('webmanifest'))->assertOk()->json('icons'));

        $this->assertSame(['any', 'maskable'], $icons->pluck('purpose')->unique()->sort()->values()->all());
        $this->assertTrue($icons->contains(fn (array $icon): bool => $icon['purpose'] === 'maskable' && $icon['sizes'] === '512x512'));

        foreach ($icons as $icon) {
            $this->assertFileExists(public_path(ltrim((string) parse_url($icon['src'], PHP_URL_PATH), '/')));
        }
    }

    public function test_png_icons_and_favicon_have_the_right_sizes(): void
    {
        foreach (['easm-180' => 180, 'easm-192' => 192, 'easm-512' => 512, 'easm-maskable-512' => 512] as $name => $size) {
            $info = getimagesize(public_path("images/pwa/$name.png"));
            $this->assertIsArray($info);
            $this->assertSame([$size, $size], [$info[0], $info[1]], "$name.png is not {$size}px");
        }

        $ico = (string) file_get_contents(public_path('favicon.ico'));
        $header = unpack('vreserved/vtype/vcount', substr($ico, 0, 6));
        $this->assertSame(['reserved' => 0, 'type' => 1, 'count' => 3], $header);

        $sizes = [];
        for ($i = 0; $i < 3; $i++) {
            $sizes[] = ord($ico[6 + 16 * $i]);
        }
        $this->assertSame([16, 32, 48], $sizes);
    }

    public function test_every_logo_image_has_the_alt_text_easm(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('<img class="sidebar-brand-icon" src="'.asset('images/pwa/easm.svg').'" alt="EASM"', false)
            ->assertSee('<link rel="apple-touch-icon" sizes="180x180" href="'.asset('images/pwa/easm-180.png').'">', false);

        auth()->logout();

        $logo = 'src="'.asset('images/easm-logo.svg').'" alt="EASM"';

        $this->get(route('login'))->assertOk()->assertSee($logo, false);
        $this->get(route('public.adopt.index'))->assertOk()->assertSee($logo, false);

        config(['easm.installed' => false, 'easm.installed_path' => storage_path('framework/testing/not-installed')]);
        $this->get(route('install.show'))->assertOk()->assertSee($logo, false);
    }
}
