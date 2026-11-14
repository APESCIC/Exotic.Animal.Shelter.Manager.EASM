<?php

namespace Database\Seeders;

use App\Enums\MedicalRecordType;
use App\Enums\MovementType;
use App\Enums\PersonCategory;
use App\Enums\UserRole;
use App\Models\Animal;
use App\Models\DiaryTask;
use App\Models\Enclosure;
use App\Models\MedicalRecord;
use App\Models\Movement;
use App\Models\Person;
use App\Models\Setting;
use App\Models\User;
use App\Support\DevLogin;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Clearly fake demo data for the local Herd preview: en_GB Faker with a fixed seed,
 * example.test emails, Ofcom drama-range phone numbers (07700 900xxx) and the
 * non-existent ZZ99 postcode area. Refuses to run unless APP_ENV=local.
 */
class DemoSeeder extends Seeder
{
    public const SEED = 20261109;

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoSeeder only runs with APP_ENV=local.');
        }

        $faker = fake('en_GB');
        $faker->seed(self::SEED);

        $this->call(DevUserSeeder::class);

        if (Setting::current() === null) {
            Setting::query()->create([
                'organisation_name' => 'Demo Exotic Rescue (fake data)',
                'locale' => 'en_GB',
                'timezone' => 'Europe/London',
            ]);
        }

        $enclosures = collect(range(1, 10))->map(fn (int $i): Enclosure => Enclosure::factory()->create([
            'name' => sprintf('Demo ENC-%02d', $i),
            'zone' => ['Reptile House', 'Aviary', 'Quarantine', 'Outdoor'][$i % 4],
        ]));

        $people = collect(range(1, 30))->map(fn (int $i): Person => Person::factory()->create([
            'name' => $faker->name(),
            'category' => PersonCategory::cases()[$i % count(PersonCategory::cases())],
            'email' => sprintf('demo-person-%02d@example.test', $i),
            'phone' => sprintf('07700 900%03d', $i),
            'address_line1' => sprintf('%d Demo Street', $i),
            'town_city' => 'Demotown',
            'county' => null,
            'postcode' => sprintf('ZZ99 %dZZ', $i % 10),
            'notes' => 'Demo record: not a real person.',
        ]));

        $staff = User::query()->where('email', DevLogin::email(UserRole::Staff))->firstOrFail();
        $species = ['Bearded dragon', 'Corn snake', 'African grey parrot', 'Sulcata tortoise', 'Royal python', 'Leopard gecko'];

        foreach (range(1, 40) as $i) {
            $animal = Animal::factory()->create([
                'name' => 'Demo '.$faker->firstName(),
                'species' => $species[$i % count($species)],
                'identifying_code' => sprintf('DEMO-%04d', $i),
                'enclosure_id' => $enclosures[$i % $enclosures->count()]->id,
                'is_adoptable' => $i % 3 === 0,
            ]);

            Movement::factory()->for($animal)->create([
                'type' => MovementType::Intake,
                'moved_at' => now()->subDays(200 - $i * 4)->toDateString(),
                'person_id' => $people[$i % $people->count()]->id,
            ]);

            if ($i % 5 === 0) {
                Movement::factory()->for($animal)->create([
                    'type' => MovementType::Adoption,
                    'moved_at' => now()->subDays(30 - $i % 20)->toDateString(),
                    'person_id' => $people[($i * 7) % $people->count()]->id,
                ]);
            }

            MedicalRecord::factory()->for($animal)->create([
                'type' => MedicalRecordType::cases()[$i % count(MedicalRecordType::cases())],
                'due_on' => now()->addDays($i % 30)->toDateString(),
            ]);

            if ($i % 4 === 0) {
                DiaryTask::factory()->for($animal)->create([
                    'assigned_to' => $staff->id,
                    'created_by' => $staff->id,
                    'due_on' => now()->addDays($i % 14)->toDateString(),
                ]);
            }
        }
    }
}
