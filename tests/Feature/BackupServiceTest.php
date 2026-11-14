<?php

namespace Tests\Feature;

use App\Backup\BackupService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $backupRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupRoot = storage_path('framework/testing/backups-'.uniqid('', true));
        File::ensureDirectoryExists($this->backupRoot);
        config(['easm.backups.path' => $this->backupRoot, 'easm.backups.keep' => 3]);
        $this->app->forgetInstance(BackupService::class);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->backupRoot)) {
            File::deleteDirectory($this->backupRoot);
        }

        parent::tearDown();
    }

    public function test_dump_restore_round_trip_without_mysqldump(): void
    {
        $marker = 'backup-round-trip-'.uniqid();
        User::factory()->create([
            'name' => $marker,
            'email' => $marker.'@example.test',
        ]);

        $service = $this->app->make(BackupService::class);
        $id = $service->create();

        $this->assertDirectoryExists($service->pathFor($id));
        $sqlPath = $service->pathFor($id).'/database.sql';
        $this->assertFileExists($sqlPath);
        $this->assertFileExists($service->pathFor($id).'/files.zip');

        $sql = (string) file_get_contents($sqlPath);
        $this->assertStringContainsString('-- EASM backup', $sql);
        $this->assertStringContainsString('DROP TABLE IF EXISTS', $sql);
        $this->assertStringContainsString($marker, $sql);
        // Pure-PHP dump: no shell / mysqldump fingerprint.
        $this->assertStringNotContainsString('mysqldump', $sql);

        User::query()->where('email', $marker.'@example.test')->delete();
        $this->assertSame(0, User::query()->where('email', $marker.'@example.test')->count());

        $service->restore($id);

        $this->assertSame(1, User::query()->where('email', $marker.'@example.test')->count());
        $this->assertSame($marker, User::query()->where('email', $marker.'@example.test')->value('name'));
    }

    public function test_fourth_backup_prunes_the_oldest(): void
    {
        $service = $this->app->make(BackupService::class);
        $base = Carbon::parse('2026-11-14 12:00:00');

        $ids = [];
        for ($i = 0; $i < 4; $i++) {
            Carbon::setTestNow($base->copy()->addSeconds($i));
            $ids[] = $service->create();
        }
        Carbon::setTestNow();

        $remaining = array_column($service->list(), 'id');
        $this->assertCount(3, $remaining);
        $this->assertNotContains($ids[0], $remaining);
        $this->assertContains($ids[1], $remaining);
        $this->assertContains($ids[2], $remaining);
        $this->assertContains($ids[3], $remaining);
        $this->assertDirectoryDoesNotExist($service->pathFor($ids[0]));
    }

    public function test_admin_can_list_create_and_download_backups(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.backups.index'))
            ->assertOk()
            ->assertSee('Create backup now', false);

        $this->actingAs($admin)
            ->post(route('admin.backups.store'))
            ->assertRedirect(route('admin.backups.index'));

        $service = $this->app->make(BackupService::class);
        $backups = $service->list();
        $this->assertNotEmpty($backups);
        $id = $backups[0]['id'];

        $download = $this->actingAs($admin)->get(route('admin.backups.download', $id));
        $download->assertOk();
        $this->assertStringContainsString('attachment', (string) $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString('easm-backup-'.$id, (string) $download->headers->get('Content-Disposition'));
    }

    public function test_staff_cannot_access_backups(): void
    {
        $staff = User::factory()->staff()->create();
        $service = $this->app->make(BackupService::class);
        $id = $service->create();

        $this->actingAs($staff)->get(route('admin.backups.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.backups.store'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.backups.download', $id))->assertForbidden();
    }

    public function test_backup_files_are_not_reachable_over_http(): void
    {
        $service = $this->app->make(BackupService::class);
        $id = $service->create();
        $sql = $service->pathFor($id).'/database.sql';
        $this->assertFileExists($sql);

        $relative = 'app/backups/'.$id.'/database.sql';
        $this->get('/storage/'.$relative)->assertNotFound();
        $this->get('/storage/app/backups/'.$id.'/database.sql')->assertNotFound();
        $this->get('/../storage/app/backups/'.$id.'/database.sql')->assertNotFound();
    }

    public function test_dump_steps_are_bounded(): void
    {
        User::factory()->count(5)->create();
        $service = $this->app->make(BackupService::class);
        $started = $service->start();

        $steps = 0;
        do {
            $startedAt = microtime(true);
            $step = $service->dumpStep($started['id']);
            $elapsed = microtime(true) - $startedAt;
            $this->assertLessThan(30, $elapsed, 'Dump step exceeded 30 seconds.');
            $steps++;
            $this->assertLessThan(500, $steps, 'Dump never finished.');
        } while (! $step['done']);

        $this->assertTrue($step['done']);
        $this->assertGreaterThan(0, $steps);
        $this->assertSame('sqlite', DB::getDriverName());
    }
}
