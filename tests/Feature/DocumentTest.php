<?php

namespace Tests\Feature;

use App\Enums\AnimalTab;
use App\Enums\DocumentTemplateType;
use App\Models\Animal;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    public function test_default_uk_templates_are_installed(): void
    {
        $this->assertEqualsCanonicalizing(
            [DocumentTemplateType::AdoptionContract, DocumentTemplateType::Invoice, DocumentTemplateType::EnclosureCard],
            DocumentTemplate::query()->pluck('type')->all(),
        );
    }

    public function test_staff_can_generate_an_adoption_contract_from_a_template(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create(['name' => 'Ziggy', 'species' => 'Bearded dragon', 'identifying_code' => 'CHIP-123']);
        $person = Person::factory()->create([
            'name' => 'Ada Adopter',
            'address_line1' => '1 High Street',
            'address_line2' => null,
            'town_city' => null,
            'county' => null,
            'postcode' => 'AB1 2CD',
        ]);
        $template = DocumentTemplate::query()->where('type', DocumentTemplateType::AdoptionContract)->firstOrFail();

        $response = $this->actingAs($staff)->post(route('documents.store'), [
            'document_template_id' => $template->id,
            'animal_id' => $animal->id,
            'person_id' => $person->id,
            'notes' => 'Needs UVB lamp',
        ]);

        $document = Document::query()->firstOrFail();
        $response->assertRedirect(route('documents.show', $document));

        $this->assertSame(DocumentTemplateType::AdoptionContract, $document->type);
        $this->assertSame($staff->id, $document->created_by);
        $this->assertStringContainsString('Ziggy', $document->body_html);
        $this->assertStringContainsString('Bearded dragon', $document->body_html);
        $this->assertStringContainsString('CHIP-123', $document->body_html);
        $this->assertStringContainsString('Ada Adopter', $document->body_html);
        $this->assertStringContainsString('1 High Street, AB1 2CD', $document->body_html);
        $this->assertStringContainsString('Needs UVB lamp', $document->body_html);
        $this->assertStringContainsString('<h2>Adoption agreement</h2>', $document->body_html);

        $this->actingAs($staff)
            ->get(AnimalTab::Documents->url($animal))
            ->assertOk()
            ->assertSee($document->title, false)
            ->assertSee('Awaiting signature', false);

        $this->actingAs($staff)
            ->get(route('people.show', $person))
            ->assertOk()
            ->assertSee($document->title, false);
    }

    public function test_template_text_and_values_are_escaped_not_executed(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create(['name' => '<script>alert(1)</script>']);
        $template = DocumentTemplate::factory()->create([
            'body' => 'Hello <b>{animal.name}</b> {{ 1 + 1 }} {unknown.token}',
        ]);

        $this->actingAs($staff)->post(route('documents.store'), [
            'document_template_id' => $template->id,
            'animal_id' => $animal->id,
        ]);

        $html = Document::query()->firstOrFail()->body_html;
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('{{ 1 + 1 }}', $html);
        $this->assertStringContainsString('{unknown.token}', $html);
    }

    public function test_generating_needs_an_animal_or_a_person_and_an_active_template(): void
    {
        $staff = User::factory()->staff()->create();
        $inactive = DocumentTemplate::factory()->inactive()->create();

        $this->actingAs($staff)
            ->post(route('documents.store'), ['document_template_id' => $inactive->id, 'animal_id' => Animal::factory()->create()->id])
            ->assertSessionHasErrors('document_template_id');

        $active = DocumentTemplate::factory()->create();
        $this->actingAs($staff)
            ->post(route('documents.store'), ['document_template_id' => $active->id])
            ->assertSessionHasErrors(['animal_id', 'person_id']);

        $this->assertSame(0, Document::query()->count());
    }

    public function test_staff_can_sign_and_the_signed_pdf_is_archived(): void
    {
        Storage::fake('local');

        $staff = User::factory()->staff()->create();
        $document = Document::factory()->create(['title' => 'Adoption contract · Ziggy']);

        $this->actingAs($staff)
            ->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee('data-signature-canvas', false)
            ->assertSee('js/signature-pad.js', false);

        $this->actingAs($staff)
            ->post(route('documents.sign', $document), [
                'signed_by_name' => 'Ada Adopter',
                'signature' => self::PNG,
            ])
            ->assertRedirect(route('documents.show', $document));

        $document->refresh();
        $this->assertTrue($document->isSigned());
        $this->assertSame('Ada Adopter', $document->signed_by_name);
        Storage::disk('local')->assertExists($document->signature_path);
        Storage::disk('local')->assertExists($document->pdf_path);
        $this->assertStringStartsWith('%PDF', (string) Storage::disk('local')->get($document->pdf_path));

        $download = $this->actingAs($staff)->get(route('documents.pdf', $document));
        $download->assertOk();
        $this->assertStringContainsString('attachment', (string) $download->headers->get('Content-Disposition'));

        $this->actingAs($staff)
            ->get(route('documents.show', $document))
            ->assertSee('Signed by', false)
            ->assertDontSee('data-signature-canvas', false);
    }

    public function test_signed_documents_cannot_be_signed_again(): void
    {
        Storage::fake('local');

        $staff = User::factory()->staff()->create();
        $document = Document::factory()->create([
            'signed_by_name' => 'First Signer',
            'signed_at' => now(),
        ]);

        $this->actingAs($staff)
            ->post(route('documents.sign', $document), ['signed_by_name' => 'Second Signer', 'signature' => self::PNG])
            ->assertSessionHasErrors('signature');

        $this->assertSame('First Signer', $document->refresh()->signed_by_name);
    }

    public function test_signature_must_be_a_png_image(): void
    {
        Storage::fake('local');

        $staff = User::factory()->staff()->create();
        $document = Document::factory()->create();

        foreach (['', 'not-a-data-uri', 'data:image/png;base64,'.base64_encode('<svg/>'), 'data:image/svg+xml;base64,PHN2Zy8+'] as $bad) {
            $this->actingAs($staff)
                ->post(route('documents.sign', $document), ['signed_by_name' => 'Ada', 'signature' => $bad])
                ->assertSessionHasErrors('signature');
        }

        $this->assertFalse($document->refresh()->isSigned());
    }

    public function test_unsigned_documents_download_as_pdf(): void
    {
        $document = Document::factory()->create();

        $response = $this->actingAs(User::factory()->readonly()->create())
            ->get(route('documents.pdf', $document));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_readonly_and_volunteers_cannot_generate_or_sign(): void
    {
        $document = Document::factory()->create();
        $template = DocumentTemplate::factory()->create();
        $animal = Animal::factory()->create();

        foreach ([User::factory()->readonly()->create(), User::factory()->volunteer()->create()] as $user) {
            $this->actingAs($user)->get(route('documents.create'))->assertForbidden();
            $this->actingAs($user)
                ->post(route('documents.store'), ['document_template_id' => $template->id, 'animal_id' => $animal->id])
                ->assertForbidden();
            $this->actingAs($user)
                ->post(route('documents.sign', $document), ['signed_by_name' => 'X', 'signature' => self::PNG])
                ->assertForbidden();
            $this->actingAs($user)
                ->get(route('documents.show', $document))
                ->assertOk()
                ->assertDontSee('data-signature-canvas', false);
        }
    }

    public function test_guests_cannot_see_documents(): void
    {
        $document = Document::factory()->create();

        $this->get(route('documents.show', $document))->assertRedirect(route('login'));
        $this->get(route('documents.pdf', $document))->assertRedirect(route('login'));
    }

    public function test_admin_manages_templates_and_staff_cannot(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.document-templates.index'))
            ->assertOk()
            ->assertSee('Adoption contract', false);

        $this->actingAs($admin)
            ->post(route('admin.document-templates.store'), [
                'name' => 'Foster agreement',
                'type' => DocumentTemplateType::AdoptionContract->value,
                'body' => "# Foster agreement\n\n{person.name} fosters {animal.name}.",
                'active' => '1',
            ])
            ->assertRedirect(route('admin.document-templates.index'));

        $template = DocumentTemplate::query()->where('name', 'Foster agreement')->firstOrFail();
        $this->assertTrue($template->active);

        $this->actingAs($admin)
            ->get(route('admin.document-templates.edit', $template))
            ->assertOk()
            ->assertSee('{animal.name}', false);

        $this->actingAs($admin)
            ->put(route('admin.document-templates.update', $template), [
                'name' => 'Foster agreement v2',
                'type' => DocumentTemplateType::AdoptionContract->value,
                'body' => 'Updated',
            ])
            ->assertRedirect(route('admin.document-templates.index'));

        $template->refresh();
        $this->assertSame('Foster agreement v2', $template->name);
        $this->assertFalse($template->active);

        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get(route('admin.document-templates.index'))->assertForbidden();
        $this->actingAs($staff)
            ->post(route('admin.document-templates.store'), ['name' => 'X', 'type' => 'invoice', 'body' => 'X'])
            ->assertForbidden();
    }
}
