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
use App\Services\CitesRegister;
use App\Services\DashboardAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
            ->assertSee(CitesRegister::NO_ARTICLE_TEN, false)
            ->assertSee('data-not-legal-advice', false);

        $this->actingAs($staff)
            ->get(AnimalTab::Legal->url($covered))
            ->assertOk()
            ->assertDontSee(CitesRegister::NO_ARTICLE_TEN, false);

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
        $this->assertSame(['Missing cert', CitesRegister::NO_ARTICLE_TEN], [$lines[3][1], $lines[3][7]]);

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

    public function test_annex_x_exempt_animal_is_not_flagged_and_shows_as_exempt(): void
    {
        $exempt = Animal::factory()->annexXExempt()->create(['name' => 'Captive-bred Testudo', 'species' => "Hermann's tortoise"]);
        $missing = Animal::factory()->create(['name' => 'Wild-caught Testudo', 'species' => "Hermann's tortoise", 'cites_annex' => CitesAnnex::A]);
        $staff = User::factory()->staff()->create();

        $this->assertSame(1, app(DashboardAlerts::class)->missingArticleTen()->count);
        $listed = $this->actingAs($staff)
            ->get(route('animals.index', ['filter' => 'missing_article_10']))
            ->viewData('animals')->pluck('name')->all();
        $this->assertSame(['Wild-caught Testudo'], $listed);

        $this->actingAs($staff)
            ->get(route('compliance.cites.index'))
            ->assertOk()
            ->assertSee('Exempt (Annex X)');

        $csv = (string) $this->actingAs($staff)->get(route('compliance.cites.csv'))->getContent();
        $rows = collect(array_map('str_getcsv', array_values(array_filter(explode("\n", $csv)))))->keyBy(1);
        $this->assertSame('Exempt (Annex X)', $rows['Captive-bred Testudo'][7]);
        $this->assertSame(CitesRegister::NO_ARTICLE_TEN, $rows['Wild-caught Testudo'][7]);

        $this->actingAs($staff)
            ->get(AnimalTab::Legal->url($exempt))
            ->assertOk()
            ->assertSee('data-annex-x-exempt', false)
            ->assertSee('Captive-bred, breeder records held')
            ->assertDontSee(CitesRegister::NO_ARTICLE_TEN);

        $this->assertFalse($missing->isAnnexXExempt());
    }

    public function test_flag_wording_says_article_10_is_needed_before_sale_or_fee_paid_rehoming(): void
    {
        $animal = Animal::factory()->create(['name' => 'Hermann', 'cites_annex' => CitesAnnex::A]);
        $staff = User::factory()->staff()->create();
        $wording = 'No Article 10: needed before any sale or fee-paid rehoming (to be confirmed)';

        $this->assertSame($wording, CitesRegister::NO_ARTICLE_TEN);
        $this->actingAs($staff)->get(route('compliance.cites.index'))
            ->assertSee($wording)
            ->assertSee('data-fee-rehoming-note', false);
        $this->actingAs($staff)->get(route('compliance.cites.csv'))->assertSee($wording, false);
        $this->actingAs($staff)->get(AnimalTab::Legal->url($animal))->assertSee($wording);
        $this->actingAs($staff)->get(route('home'))
            ->assertSee('No Article 10 (Annex A)')
            ->assertSee('fee-paid rehoming (to be confirmed)');
        $this->assertSame(1, app(DashboardAlerts::class)->missingArticleTen()->count);
    }

    public function test_recent_export_permit_covers_article_10_for_six_months(): void
    {
        $animal = Animal::factory()->create(['name' => 'Imported tortoise', 'cites_annex' => CitesAnnex::A]);
        CitesCertificate::factory()->for($animal)->create(['type' => CitesCertificateType::ExportPermit, 'issued_on' => '2026-09-01']);
        $undated = Animal::factory()->create(['name' => 'Undated permit', 'cites_annex' => CitesAnnex::A]);
        CitesCertificate::factory()->for($undated)->create(['type' => CitesCertificateType::ExportPermit, 'issued_on' => null]);
        $register = app(CitesRegister::class);
        $staff = User::factory()->staff()->create();

        try {
            Carbon::setTestNow('2026-10-30 09:00:00');
            $this->assertFalse($animal->fresh()->isMissingArticleTen());
            $this->assertSame('Covered by export permit until 01/03/2027', $register->articleTenStatus($animal->fresh()->load('citesCertificates', 'latestMovement')));
            $this->assertTrue($undated->fresh()->isMissingArticleTen());
            $this->assertSame(['Undated permit'], Animal::query()->missingArticleTen()->pluck('name')->all());
            $this->actingAs($staff)
                ->get(AnimalTab::Legal->url($animal))
                ->assertSee('Covered by export permit until 01/03/2027');
            $this->actingAs($staff)
                ->get(route('compliance.cites.csv'))
                ->assertSee('Covered by export permit until 01/03/2027');

            Carbon::setTestNow('2027-03-01 09:00:00');
            $this->assertFalse($animal->fresh()->isMissingArticleTen());

            Carbon::setTestNow('2027-03-02 09:00:00');
            $this->assertTrue($animal->fresh()->isMissingArticleTen());
            $this->assertSame(CitesRegister::NO_ARTICLE_TEN, $register->articleTenStatus($animal->fresh()->load('citesCertificates', 'latestMovement')));
            $this->assertSame(['Imported tortoise', 'Undated permit'], Animal::query()->missingArticleTen()->orderBy('name')->pluck('name')->all());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_staff_set_and_clear_the_annex_x_exemption(): void
    {
        $animal = Animal::factory()->create(['name' => 'Shelly', 'species' => "Hermann's tortoise", 'sex' => 'female', 'cites_annex' => CitesAnnex::A]);
        $form = ['name' => 'Shelly', 'species' => "Hermann's tortoise", 'sex' => 'female', 'cites_annex' => 'A'];

        $this->actingAs(User::factory()->volunteer()->create())
            ->put(route('animals.update', $animal), [...$form, 'cites_exempt_annex_x' => '1'])
            ->assertForbidden();
        $this->assertFalse($animal->refresh()->cites_exempt_annex_x);

        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)
            ->put(route('animals.update', $animal), [...$form, 'cites_exempt_annex_x' => '1', 'cites_exemption_note' => 'Hatched here 2024'])
            ->assertRedirect(route('animals.show', $animal));
        $this->assertTrue($animal->refresh()->cites_exempt_annex_x);
        $this->assertSame(0, app(DashboardAlerts::class)->missingArticleTen()->count);

        $this->actingAs($staff)
            ->put(route('animals.update', $animal), $form)
            ->assertRedirect(route('animals.show', $animal));
        $this->assertFalse($animal->refresh()->cites_exempt_annex_x);
        $this->assertSame(1, app(DashboardAlerts::class)->missingArticleTen()->count);
        $this->assertTrue($animal->load('citesCertificates')->isMissingArticleTen());
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
