<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_dashboard_hides_financial_widgets(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Élèves actifs');
        $response->assertSee('Inscriptions');
        $response->assertDontSee('Encaisser');
        $response->assertDontSee('Encaisse du jour');
        $response->assertDontSee('Total attendu');
        $response->assertDontSee('Derniers paiements');
    }

    public function test_comptable_can_open_financial_dashboard_and_payments(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('comptable');

        $dashboard = $this->actingAs($user)->get(route('dashboard'));
        $payments = $this->actingAs($user)->get(route('payments.index'));

        $dashboard->assertOk();
        $dashboard->assertSee('Encaisser');
        $dashboard->assertSee('Encaisse du jour');
        $dashboard->assertSee('Derniers paiements');
        $payments->assertOk();
    }

    public function test_enseignant_cannot_open_financial_pages(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('enseignant');

        $this->actingAs($user)->get(route('grades.index'))->assertOk();
        $this->actingAs($user)->get(route('attendance.index'))->assertOk();
        $this->actingAs($user)->get(route('payments.index'))->assertForbidden();
        $this->actingAs($user)->get(route('accounting.cash-journal'))->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-test',
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
