<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TechnicalMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_help_page(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create(['username' => 'help-test', 'status' => 'active']);
        $user->assignRole('secretariat');

        $response = $this->actingAs($user)->get(route('help.index'));

        $response->assertOk();
        $response->assertSee('Aide et guide utilisateur');
        $response->assertSee('Demarrage rapide');
    }

    public function test_database_backup_command_creates_json_export(): void
    {
        $this->seed(DatabaseSeeder::class);
        $path = storage_path('framework/testing/backups');

        File::deleteDirectory($path);

        $this->artisan('lpp:backup-database', ['--path' => $path])
            ->assertExitCode(0);

        $this->assertNotEmpty(File::glob($path . DIRECTORY_SEPARATOR . 'lpp-sqlite-*.json'));

        File::deleteDirectory($path);
    }
}
