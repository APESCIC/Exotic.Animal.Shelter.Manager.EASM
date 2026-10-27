<?php

namespace Tests\Feature;

use App\Enums\AnimalMediaKind;
use App\Enums\AnimalTab;
use App\Models\Animal;
use App\Models\AnimalMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnimalMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_upload_photo_and_pdf_onto_animal(): void
    {
        Storage::fake('public');

        $staff = User::factory()->staff()->create(['name' => 'Media Staff']);
        $animal = Animal::factory()->create(['name' => 'Spike']);

        $this->actingAs($staff)
            ->post(route('animals.media.store', $animal), [
                'media_file' => UploadedFile::fake()->image('enclosure.jpg', 200, 200),
            ])
            ->assertRedirect(AnimalTab::Media->url($animal));

        $photo = AnimalMedia::query()->where('animal_id', $animal->id)->first();
        $this->assertNotNull($photo);
        $this->assertSame(AnimalMediaKind::Photo, $photo->kind);
        $this->assertSame($staff->id, $photo->uploaded_by);
        $this->assertStringStartsWith('media/animals/'.$animal->id.'/', $photo->disk_path);
        Storage::disk('public')->assertExists($photo->disk_path);

        $this->actingAs($staff)
            ->post(route('animals.media.store', $animal), [
                'media_file' => UploadedFile::fake()->create('care-plan.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(AnimalTab::Media->url($animal));

        $pdf = AnimalMedia::query()
            ->where('animal_id', $animal->id)
            ->where('kind', AnimalMediaKind::Document->value)
            ->first();
        $this->assertNotNull($pdf);
        Storage::disk('public')->assertExists($pdf->disk_path);

        $this->actingAs($staff)
            ->get(AnimalTab::Media->url($animal))
            ->assertOk()
            ->assertSee('Media', false)
            ->assertSee('enclosure.jpg', false)
            ->assertSee('care-plan.pdf', false)
            ->assertSee('Download PDF', false);
    }

    public function test_readonly_cannot_upload_media_but_can_view(): void
    {
        Storage::fake('public');

        $readonly = User::factory()->readonly()->create();
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create(['name' => 'Tess']);
        $media = AnimalMedia::factory()->create([
            'animal_id' => $animal->id,
            'uploaded_by' => $staff->id,
            'original_name' => 'visible.jpg',
            'disk_path' => 'media/animals/'.$animal->id.'/visible.jpg',
        ]);
        Storage::disk('public')->put($media->disk_path, 'fake');

        $this->actingAs($readonly)
            ->get(route('animals.media.create', $animal))
            ->assertForbidden();

        $this->actingAs($readonly)
            ->post(route('animals.media.store', $animal), [
                'media_file' => UploadedFile::fake()->image('blocked.jpg'),
            ])
            ->assertForbidden();

        $this->actingAs($readonly)
            ->delete(route('media.destroy', $media))
            ->assertForbidden();

        $this->actingAs($readonly)
            ->get(AnimalTab::Media->url($animal))
            ->assertOk()
            ->assertSee('visible.jpg', false)
            ->assertDontSee('Upload media', false);
    }

    public function test_staff_can_delete_media(): void
    {
        Storage::fake('public');

        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create();
        $path = 'media/animals/'.$animal->id.'/remove-me.jpg';
        Storage::disk('public')->put($path, 'fake');
        $media = AnimalMedia::factory()->create([
            'animal_id' => $animal->id,
            'uploaded_by' => $staff->id,
            'disk_path' => $path,
            'original_name' => 'remove-me.jpg',
        ]);

        $this->actingAs($staff)
            ->delete(route('media.destroy', $media))
            ->assertRedirect(AnimalTab::Media->url($animal));

        $this->assertDatabaseMissing('animal_media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($path);
    }
}
