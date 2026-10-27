<?php

namespace Database\Factories;

use App\Enums\DocumentTemplateType;
use App\Models\Animal;
use App\Models\Document;
use App\Models\DocumentTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_template_id' => DocumentTemplate::factory(),
            'animal_id' => Animal::factory(),
            'person_id' => null,
            'type' => DocumentTemplateType::AdoptionContract,
            'title' => 'Adoption contract',
            'body_html' => '<p>Agreement text.</p>',
        ];
    }
}
