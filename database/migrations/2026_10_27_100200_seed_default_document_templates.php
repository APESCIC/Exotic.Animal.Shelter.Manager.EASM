<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('document_templates')->insert([
            [
                'name' => 'Adoption contract',
                'type' => 'adoption_contract',
                'body' => <<<'TEXT'
# Adoption agreement

This agreement is between {organisation.name} ("the shelter") and {person.name} of {person.address} ("the adopter").

Animal: {animal.name}, {animal.species} ({animal.sex})
Identifying code or microchip: {animal.identifying_code}
Date of birth: {animal.date_of_birth}

The adopter agrees to:
- provide housing, heating, lighting, diet and veterinary care suitable for this species;
- keep to any Dangerous Wild Animals Act 1976 licence ({animal.dwa}) and CITES requirements ({animal.cites}) that apply;
- not sell, breed from or give away the animal without the shelter's written consent;
- return the animal to the shelter if they can no longer care for it;
- allow the shelter reasonable welfare follow-up visits.

Notes: {document.notes}

Dated {today}.
TEXT,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Invoice',
                'type' => 'invoice',
                'body' => <<<'TEXT'
# Invoice

From: {organisation.name}
To: {person.name}, {person.address}
Date: {today}

Animal: {animal.name} ({animal.species})

Items and amounts:
{document.notes}

Please quote the animal's name when paying.
TEXT,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Enclosure card',
                'type' => 'enclosure_card',
                'body' => <<<'TEXT'
# {animal.name}

Species: {animal.species}
Sex: {animal.sex}
Date of birth: {animal.date_of_birth}
Identifying code: {animal.identifying_code}
Location: {animal.location}
Enclosure: {animal.enclosure}
CITES: {animal.cites}
DWA: {animal.dwa}

{document.notes}
TEXT,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('document_templates')
            ->whereIn('name', ['Adoption contract', 'Invoice', 'Enclosure card'])
            ->delete();
    }
};
