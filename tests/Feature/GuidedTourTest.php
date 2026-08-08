<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GuidedTourService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class GuidedTourTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_every_guided_tour(): void
    {
        $admin = $this->userWithRole('admin');
        $tours = app(GuidedTourService::class)->visibleFor($admin);

        $this->assertSame(
            ['dashboard', 'timetable-planning', 'payments', 'students'],
            $tours->pluck('key')->all(),
        );
        $this->assertTrue($tours->every(fn (array $tour): bool => $tour['stepCount'] > 0));
    }

    public function test_guided_tours_follow_role_permissions(): void
    {
        $secretariatTours = app(GuidedTourService::class)
            ->visibleFor($this->userWithRole('secretariat'))
            ->pluck('key');
        $accountingTours = app(GuidedTourService::class)
            ->visibleFor($this->userWithRole('comptable'))
            ->pluck('key');

        $this->assertTrue($secretariatTours->contains('timetable-planning'));
        $this->assertTrue($secretariatTours->contains('students'));
        $this->assertFalse($secretariatTours->contains('payments'));

        $this->assertTrue($accountingTours->contains('payments'));
        $this->assertTrue($accountingTours->contains('students'));
        $this->assertFalse($accountingTours->contains('timetable-planning'));
    }

    public function test_step_permissions_hide_unauthorized_actions(): void
    {
        $teacher = $this->userWithRole('enseignant');
        $studentTour = app(GuidedTourService::class)
            ->visibleFor($teacher)
            ->firstWhere('key', 'students');

        $this->assertNotNull($studentTour);
        $this->assertSame(
            ['students-search', 'students-list'],
            collect($studentTour['steps'])->pluck('target')->all(),
        );
    }

    public function test_documentation_lists_only_authorized_guided_tours(): void
    {
        $secretariat = $this->userWithRole('secretariat');

        $this->actingAs($secretariat)
            ->get(route('help.index'))
            ->assertOk()
            ->assertSee('Visites guidées')
            ->assertSee('data-guided-tour-card="dashboard"', false)
            ->assertSee('data-guided-tour-card="timetable-planning"', false)
            ->assertSee('data-guided-tour-card="students"', false)
            ->assertDontSee('data-guided-tour-card="payments"', false);
    }

    public function test_every_guided_tour_uses_an_existing_route_and_valid_steps(): void
    {
        foreach (config('guided_tours.tours') as $key => $tour) {
            $this->assertTrue(Route::has($tour['route']), "La route de la visite {$key} n'existe pas.");
            $this->assertNotEmpty($tour['title'], "La visite {$key} n'a pas de titre.");
            $this->assertNotEmpty($tour['description'], "La visite {$key} n'a pas de description.");
            $this->assertNotEmpty($tour['steps'], "La visite {$key} n'a pas d'étapes.");

            foreach ($tour['steps'] as $step) {
                $this->assertNotEmpty($step['target'], "Une étape de {$key} n'a pas de cible.");
                $this->assertNotEmpty($step['title'], "Une étape de {$key} n'a pas de titre.");
                $this->assertNotEmpty($step['description'], "Une étape de {$key} n'a pas de description.");
            }
        }
    }

    private function userWithRole(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'username' => $role.'-guided-tour-test-'.User::query()->count(),
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
