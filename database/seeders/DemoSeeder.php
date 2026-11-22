<?php

namespace Database\Seeders;

use App\Enums\CitesAnnex;
use App\Enums\CitesCertificateType;
use App\Enums\DwaInspectionOutcome;
use App\Enums\EquipmentType;
use App\Enums\MedicalRecordType;
use App\Enums\MovementType;
use App\Enums\PersonCategory;
use App\Enums\StockCategory;
use App\Enums\UserRole;
use App\Models\Animal;
use App\Models\CitesCertificate;
use App\Models\DiaryTask;
use App\Models\Diet;
use App\Models\DwaInspection;
use App\Models\DwaLicence;
use App\Models\Enclosure;
use App\Models\EnclosureEquipment;
use App\Models\EnclosureLog;
use App\Models\MedicalRecord;
use App\Models\Movement;
use App\Models\Person;
use App\Models\Setting;
use App\Models\StockItem;
use App\Models\User;
use App\Support\DevLogin;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Clearly fake demo data for the local Herd preview: en_GB Faker with a fixed seed,
 * example.test emails, Ofcom drama-range phone numbers (07700 900xxx) and the
 * non-existent ZZ99 postcode area. Idempotent and local-only.
 */
class DemoSeeder extends Seeder
{
    public const SEED = 20261109;

    /**
     * Free-text species covering reptiles, primates and birds (clearly labelled demo).
     *
     * @var list<string>
     */
    public const SPECIES = [
        'Bearded dragon (demo)',
        'Corn snake (demo)',
        'Sulcata tortoise (demo)',
        'Royal python (demo)',
        'Leopard gecko (demo)',
        'Common marmoset (demo)',
        'Capuchin monkey (demo)',
        'African grey parrot (demo)',
        'Eclectus parrot (demo)',
        'Budgerigar (demo)',
    ];

    public function run(): void
    {
        $this->assertSafeToSeed();

        $faker = fake('en_GB');
        $faker->seed(self::SEED);

        $this->call(DevUserSeeder::class);
        $this->call(LookupSeeder::class);

        $settings = [
            'organisation_name' => 'Demo Exotic Rescue (fake data)',
            'locale' => 'en_GB',
            'timezone' => 'Europe/London',
        ];
        $existingSettings = Setting::query()->first();
        if ($existingSettings === null) {
            Setting::query()->create($settings);
        } else {
            $existingSettings->fill($settings)->save();
        }

        $qa = $this->qaUsers();
        $enclosures = $this->seedEnclosures();
        $people = $this->seedPeople($faker);
        $animals = $this->seedAnimals($faker, $enclosures, $people, $qa);
        $this->seedHusbandry($enclosures, $animals, $qa);
        $this->seedCompliance($animals, $qa);
        $this->seedStock();
    }

    /**
     * Refuse outside local Herd-style setups. Allows the approved local DB name
     * `easm` as well as names ending in `_local`.
     */
    public function assertSafeToSeed(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoSeeder only runs with APP_ENV=local.');
        }

        foreach (['CLOUDRON_MYSQL_HOST', 'CLOUDRON_MYSQL_DATABASE', 'CLOUDRON_MYSQL_URL'] as $key) {
            if (filled(env($key))) {
                throw new RuntimeException('DemoSeeder refuses to run when Cloudron database variables are set.');
            }
        }

        $connection = (string) config('database.default');
        $config = config("database.connections.{$connection}", []);
        $driver = $config['driver'] ?? null;
        $host = $config['host'] ?? null;
        $database = (string) ($config['database'] ?? '');

        if ($driver !== 'sqlite' && ! in_array($host, ['127.0.0.1', 'localhost', null, ''], true)) {
            throw new RuntimeException('DemoSeeder only runs against a database on this computer.');
        }

        if ($driver !== 'sqlite' && $database !== '' && $database !== 'easm' && ! str_ends_with($database, '_local')) {
            throw new RuntimeException('DemoSeeder only runs against easm or a database name ending in _local.');
        }
    }

    /**
     * @return array<string, User>
     */
    private function qaUsers(): array
    {
        $users = [];
        foreach (UserRole::cases() as $role) {
            $users[$role->value] = User::query()->where('email', DevLogin::email($role))->firstOrFail();
        }

        return $users;
    }

    /**
     * @return Collection<int, Enclosure>
     */
    private function seedEnclosures()
    {
        $zones = ['Reptile House', 'Aviary', 'Quarantine', 'Primate House', 'Outdoor'];

        return collect(range(1, 10))->map(function (int $i) use ($zones): Enclosure {
            $name = sprintf('Demo ENC-%02d', $i);

            return Enclosure::query()->updateOrCreate(
                ['name' => $name],
                [
                    'zone' => $i === 3 ? 'Quarantine' : $zones[$i % count($zones)],
                    'notes' => $i === 3 ? 'Demo quarantine enclosure (fake data).' : 'Demo enclosure (fake data).',
                ],
            );
        });
    }

    /**
     * @param  Generator  $faker
     * @return Collection<int, Person>
     */
    private function seedPeople($faker)
    {
        return collect(range(1, 30))->map(function (int $i) use ($faker): Person {
            $email = sprintf('demo-person-%02d@example.test', $i);

            return Person::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $faker->name(),
                    'category' => PersonCategory::cases()[$i % count(PersonCategory::cases())],
                    'phone' => sprintf('07700 900%03d', $i),
                    'address_line1' => sprintf('%d Demo Street', $i),
                    'town_city' => 'Demotown',
                    'county' => null,
                    'postcode' => sprintf('ZZ99 %dZZ', $i % 10),
                    'notes' => 'Demo record: not a real person.',
                ],
            );
        });
    }

    /**
     * @param  Generator  $faker
     * @param  Collection<int, Enclosure>  $enclosures
     * @param  Collection<int, Person>  $people
     * @param  array<string, User>  $qa
     * @return Collection<int, Animal>
     */
    private function seedAnimals($faker, $enclosures, $people, array $qa)
    {
        $quarantine = $enclosures->firstWhere('zone', 'Quarantine') ?? $enclosures->get(2);

        return collect(range(1, 40))->map(function (int $i) use ($faker, $enclosures, $people, $qa, $quarantine): Animal {
            $code = sprintf('DEMO-%04d', $i);
            $species = self::SPECIES[$i % count(self::SPECIES)];
            $inQuarantine = $i === 3 || $i === 13;

            $animal = Animal::query()->updateOrCreate(
                ['identifying_code' => $code],
                [
                    'name' => 'Demo '.$faker->firstName(),
                    'species' => $species,
                    'enclosure_id' => $inQuarantine ? $quarantine->id : $enclosures[$i % $enclosures->count()]->id,
                    'is_adoptable' => $i % 3 === 0 && ! $inQuarantine,
                    'cites' => $i % 7 === 0 ? 'Yes' : null,
                    'cites_annex' => $i % 7 === 0 ? CitesAnnex::B : CitesAnnex::NotListed,
                    'dwa' => $i % 11 === 0 ? 'Yes' : null,
                    'flags' => 'Demo record: fake animal for local QA.',
                ],
            );

            Movement::query()->updateOrCreate(
                [
                    'animal_id' => $animal->id,
                    'type' => MovementType::Intake,
                    'moved_at' => now()->subDays(200 - $i * 4)->toDateString(),
                ],
                [
                    'person_id' => $people[$i % $people->count()]->id,
                    'notes' => 'Demo intake (fake data).',
                ],
            );

            if ($inQuarantine) {
                Movement::query()->updateOrCreate(
                    [
                        'animal_id' => $animal->id,
                        'type' => MovementType::Quarantine,
                        'moved_at' => now()->subDays(10)->toDateString(),
                    ],
                    [
                        'person_id' => null,
                        'notes' => 'Demo quarantine intake (fake data).',
                    ],
                );
            }

            if ($i % 5 === 0 && ! $inQuarantine) {
                Movement::query()->updateOrCreate(
                    [
                        'animal_id' => $animal->id,
                        'type' => MovementType::Adoption,
                        'moved_at' => now()->subDays(30 - ($i % 20))->toDateString(),
                    ],
                    [
                        'person_id' => $people[($i * 7) % $people->count()]->id,
                        'notes' => 'Demo adoption (fake data).',
                    ],
                );
            }

            MedicalRecord::query()->updateOrCreate(
                [
                    'animal_id' => $animal->id,
                    'type' => MedicalRecordType::cases()[$i % count(MedicalRecordType::cases())],
                    'name' => 'Demo medical '.$i,
                ],
                [
                    'due_on' => now()->addDays($i % 30)->toDateString(),
                    'notes' => 'Recorded for QA '.$qa['staff']->email.' (fake data).',
                ],
            );

            if ($i % 4 === 0) {
                $assignee = match ($i % 3) {
                    0 => $qa['volunteer'],
                    1 => $qa['staff'],
                    default => $qa['admin'],
                };

                DiaryTask::query()->updateOrCreate(
                    [
                        'animal_id' => $animal->id,
                        'title' => 'Demo task '.$i,
                        'due_on' => now()->addDays($i % 14)->toDateString(),
                    ],
                    [
                        'assigned_to' => $assignee->id,
                        'created_by' => $qa['admin']->id,
                        'body' => 'Demo diary task for '.$assignee->email.' (fake data).',
                    ],
                );
            }

            return $animal;
        });
    }

    /**
     * @param  Collection<int, Enclosure>  $enclosures
     * @param  Collection<int, Animal>  $animals
     * @param  array<string, User>  $qa
     */
    private function seedHusbandry($enclosures, $animals, array $qa): void
    {
        foreach ($enclosures as $index => $enclosure) {
            EnclosureEquipment::query()->updateOrCreate(
                [
                    'enclosure_id' => $enclosure->id,
                    'name' => 'Demo UVB '.$enclosure->name,
                ],
                [
                    'type' => EquipmentType::UvbLamp,
                    'installed_on' => now()->subDays(40 + $index)->toDateString(),
                    'replace_every_days' => 365,
                    'notes' => 'Demo equipment (fake data).',
                    'active' => true,
                ],
            );

            EnclosureLog::query()->updateOrCreate(
                [
                    'enclosure_id' => $enclosure->id,
                    'recorded_at' => now()->subHours($index + 1)->startOfHour(),
                ],
                [
                    'temperature_hot_c' => 32.0 + ($index % 5),
                    'temperature_cool_c' => 24.0 + ($index % 3),
                    'humidity_percent' => 40 + ($index * 3),
                    'notes' => 'Demo enclosure log by '.$qa['volunteer']->email.' (fake data).',
                    'user_id' => $qa['volunteer']->id,
                ],
            );
        }

        foreach ($animals->take(12) as $i => $animal) {
            Diet::query()->updateOrCreate(
                [
                    'animal_id' => $animal->id,
                    'name' => 'Demo diet '.$animal->identifying_code,
                ],
                [
                    'details' => 'Fake husbandry diet for local QA.',
                    'started_on' => now()->subDays(20)->toDateString(),
                    'ended_on' => null,
                    'notes' => 'Visible to staff/volunteer QA roles (fake data).',
                ],
            );
        }
    }

    /**
     * @param  Collection<int, Animal>  $animals
     * @param  array<string, User>  $qa
     */
    private function seedCompliance($animals, array $qa): void
    {
        $citesAnimal = $animals->first(fn (Animal $animal): bool => (bool) $animal->cites) ?? $animals->first();
        $dwaAnimal = $animals->first(fn (Animal $animal): bool => (bool) $animal->dwa) ?? $animals->skip(1)->first();

        CitesCertificate::query()->updateOrCreate(
            ['certificate_number' => 'GB-A10-DEMO-0001'],
            [
                'animal_id' => $citesAnimal->id,
                'type' => CitesCertificateType::Article10,
                'issued_on' => now()->subMonths(6)->toDateString(),
                'notes' => 'Demo Article 10 for '.$qa['readonly']->email.' view checks (fake data).',
                'uploaded_by' => $qa['staff']->id,
            ],
        );

        $licence = DwaLicence::query()->updateOrCreate(
            ['licence_number' => 'DWA-DEMO-0001'],
            [
                'issuing_authority' => 'Demo District Council',
                'species_covered' => 'Demo dangerous wild animals (fake data)',
                'conditions' => 'Local QA only — not a real licence.',
                'issued_on' => now()->subYear()->toDateString(),
                'expires_on' => now()->addYear()->toDateString(),
                'notes' => 'Demo DWA licence (fake data).',
            ],
        );

        $licence->animals()->syncWithoutDetaching([$dwaAnimal->id]);

        DwaInspection::query()->updateOrCreate(
            [
                'dwa_licence_id' => $licence->id,
                'inspected_on' => now()->subMonths(2)->toDateString(),
            ],
            [
                'inspector' => 'Demo Inspector',
                'outcome' => DwaInspectionOutcome::Satisfactory,
                'notes' => 'Demo inspection (fake data).',
                'recorded_by' => $qa['admin']->id,
            ],
        );
    }

    private function seedStock(): void
    {
        StockItem::query()->updateOrCreate(
            ['name' => 'Demo cricket tubs'],
            [
                'category' => StockCategory::Food,
                'unit' => 'tubs',
                'quantity' => '12.00',
                'reorder_level' => '3.00',
                'location' => 'Demo store',
                'notes' => 'Demo stock (fake data).',
                'active' => true,
            ],
        );

        StockItem::query()->updateOrCreate(
            ['name' => 'Demo fenbendazole'],
            [
                'category' => StockCategory::Medical,
                'unit' => 'doses',
                'quantity' => '20.00',
                'reorder_level' => '5.00',
                'location' => 'Demo meds fridge',
                'notes' => 'Demo medical stock (fake data).',
                'active' => true,
            ],
        );
    }
}
