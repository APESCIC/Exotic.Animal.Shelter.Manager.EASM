<?php

namespace Tests\Feature;

use App\Enums\AnimalTab;
use App\Enums\CitesAnnex;
use App\Enums\CitesCertificateType;
use App\Enums\MovementType;
use App\Models\Animal;
use App\Models\CitesCertificate;
use App\Models\Movement;
use App\Models\User;
use App\Services\DashboardAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CitesRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_annex_a_animal_without_article_10_is_flagged(): void
    {
        $missing = Animal::factory()->create(['name' => 'Hermann', 'cites_annex' => CitesAnnex::A]);
        $covered = Animal::factory()->create(['name' => 'Covered tortoise', 'cites_annex' => CitesAnnex::A]);
        CitesCertificate::factory()->for($covered)->create(['type' => CitesCertificateType::Article10]);
        $permitOnly = Animal::factory()->create(['name' => 'Permit only', 'cites_annex' => CitesAnnex::A]);
        CitesCertificate::factory()->for($permitOnly)->create(['type' => CitesCertificateType::ImportPermit]);
        Animal::factory()->create(['name' => 'Annex B python', 'cites_annex' => CitesAnnex::B]);
        Animal::factory()->create(['name' => 'Late tortoise', 'cites_annex' => CitesAnnex::A, 'deceased_at' => now()]);
        $adopted = Animal::factory()->create(['name' => 'Adopted tortoise', 'cites_annex' => CitesAnnex::A]);
        Movement::factory()->for($adopted)->create(['type' => MovementType::Adoption]);

        $alert = app(DashboardAlerts::class)->missingArticleTen();
        $this->assertSame(2, $alert->count);
        $this->assertSame(route('animals.index', ['filter' => 'missing_article_10']), $alert->url);

        $staff = User::factory()->staff()->create();
        $listed = $this->actingAs($staff)
            ->get($alert->url)
            ->assertOk()
            ->assertSee('data-active-filter="missing_article_10"', false)
            ->viewData('animals')->pluck('name')->sort()->values()->all();
        $this->assertSame(['Hermann', 'Permit only'], $listed);

        $this->actingAs($staff)
            ->get(AnimalTab::Legal->url($missing))
            ->assertOk()
            ->assertSee('Article 10 missing', false)
            ->assertSee('data-not-legal-advice', false);

        $this->actingAs($staff)
            ->get(AnimalTab::Legal->url($covered))
            ->assertOk()
            ->assertDontSee('Article 10 missing', false);

        $this->actingAs($staff)
            ->get(route('compliance.cites.index'))
            ->assertOk()
            ->assertSee('data-missing-article-10', false);
    }

    public function test_register_exports_to_csv(): void
    {
        $tortoise = Animal::factory()->create([
            'name' => 'Hermann',
            'species' => "Hermann's tortoise",
            'identifying_code' => 'CHIP-1',
            'cites_annex' => CitesAnnex::A,
            'cites' => 'Appendix II',
        ]);
        CitesCertificate::factory()->for($tortoise)->create([
            'type' => CitesCertificateType::Article10,
            'certificate_number' => 'GB-A10-0001',
            'issued_on' => '2026-03-01',
        ]);
        Animal::factory()->create(['name' => 'Missing cert', 'species' => 'Spur-thighed tortoise', 'cites_annex' => CitesAnnex::A, 'cites' => null]);
        Animal::factory()->create(['name' => 'Legacy ref', 'species' => 'Royal python', 'cites' => 'Appendix II', 'cites_annex' => null]);
        Animal::factory()->create(['name' => 'Not on register', 'species' => 'Corn snake', 'cites' => 'None', 'cites_annex' => CitesAnnex::NotListed]);
        $gone = Animal::factory()->create(['name' => 'Rehomed', 'species' => 'Zebra tortoise', 'cites_annex' => CitesAnnex::B, 'cites' => null]);
        Movement::factory()->for($gone)->create(['type' => MovementType::Transfer, 'moved_at' => '2026-09-01']);

        $response = $this->actingAs(User::factory()->readonly()->create())
            ->get(route('compliance.cites.csv'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $lines = array_map('str_getcsv', array_values(array_filter(explode("\n", (string) $response->getContent()))));
        $this->assertSame(['Animal ID', 'Name', 'Species', 'Identifying code', 'Annex', 'CITES reference', 'Certificates', 'Article 10 on file', 'Status'], $lines[0]);
        $this->assertCount(4, $lines);
        $this->assertSame(
            [(string) $tortoise->id, 'Hermann', "Hermann's tortoise", 'CHIP-1', 'Annex A', 'Appendix II', 'Article 10 certificate GB-A10-0001 01/03/2026', 'Yes', 'In care'],
            $lines[1],
        );
        $this->assertSame(['Legacy ref', 'Not recorded', 'No'], [$lines[2][1], $lines[2][4], $lines[2][7]]);
        $this->assertSame(['Missing cert', 'Missing'], [$lines[3][1], $lines[3][7]]);

        $all = $this->actingAs(User::factory()->staff()->create())
            ->get(route('compliance.cites.csv', ['all' => 1]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Rehomed', (string) $all);
        $this->assertStringContainsString('Transfer 01/09/2026', (string) $all);
        $this->assertStringNotContainsString('Not on register', (string) $all);
    }

    public function test_staff_upload_a_private_certificate_that_signed_in_compliance_users_can_download(): void
    {
        Storage::fake(CitesCertificate::DISK);
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create(['cites_annex' => CitesAnnex::A]);

        $this->actingAs($staff)->post(route('animals.cites-certificates.store', $animal), [
            'type' => 'article_10',
            'certificate_number' => 'GB-A10-0042',
            'issued_on' => '2026-05-01',
            'certificate_file' => UploadedFile::fake()->create('article10.pdf', 120, 'application/pdf'),
        ])->assertRedirect(AnimalTab::Legal->url($animal));

        $certificate = CitesCertificate::query()->sole();
        $this->assertSame(CitesCertificateType::Article10, $certificate->type);
        $this->assertSame('article10.pdf', $certificate->original_name);
        $this->assertStringStartsWith('cites/'.$animal->id.'/', (string) $certificate->file_path);
        Storage::disk(CitesCertificate::DISK)->assertExists((string) $certificate->file_path);
        $this->assertFalse($animal->refresh()->load('citesCertificates')->isMissingArticleTen());

        $this->actingAs(User::factory()->readonly()->create())
            ->get(route('cites-certificates.download', $certificate))
            ->assertOk()
            ->assertDownload('article10.pdf');

        $this->actingAs(User::factory()->volunteer()->create())
            ->get(route('cites-certificates.download', $certificate))
            ->assertForbidden();

        $this->actingAs($staff)
            ->delete(route('cites-certificates.destroy', $certificate))
            ->assertRedirect(AnimalTab::Legal->url($animal));
        Storage::disk(CitesCertificate::DISK)->assertMissing((string) $certificate->file_path);
        $this->assertSame(0, CitesCertificate::query()->count());
    }

    public function test_certificate_uploads_are_validated_and_restricted(): void
    {
        Storage::fake(CitesCertificate::DISK);
        $animal = Animal::factory()->create();

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('animals.cites-certificates.store', $animal), [
                'type' => 'article_10',
                'certificate_number' => 'X',
                'certificate_file' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
            ])
            ->assertSessionHasErrors('certificate_file');

        $this->actingAs(User::factory()->volunteer()->create())
            ->post(route('animals.cites-certificates.store', $animal), ['type' => 'article_10', 'certificate_number' => 'X'])
            ->assertForbidden();

        $this->assertSame(0, CitesCertificate::query()->count());
    }

    public function test_volunteers_cannot_see_the_register_and_staff_set_the_annex(): void
    {
        $volunteer = User::factory()->volunteer()->create();
        $this->actingAs($volunteer)->get(route('compliance.cites.index'))->assertForbidden();
        $this->actingAs($volunteer)->get(route('compliance.cites.csv'))->assertForbidden();
        $this->actingAs($volunteer)->get(route('home'))->assertDontSee(route('compliance.cites.index'), false);

        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create(['name' => 'Kaa', 'species' => 'Royal python', 'sex' => 'female']);

        $this->actingAs($staff)->put(route('animals.update', $animal), [
            'name' => 'Kaa',
            'species' => 'Royal python',
            'sex' => 'female',
            'cites_annex' => 'B',
        ])->assertRedirect(route('animals.show', $animal));

        $this->assertSame(CitesAnnex::B, $animal->refresh()->cites_annex);
        $this->assertTrue($animal->hasCitesListing());
        $this->assertSame(1, app(DashboardAlerts::class)->citesInCare()->count);

        $this->actingAs($staff)
            ->get(route('compliance.cites.index'))
            ->assertOk()
            ->assertSee('Kaa', false)
            ->assertSee('Annex B', false);
    }
}
