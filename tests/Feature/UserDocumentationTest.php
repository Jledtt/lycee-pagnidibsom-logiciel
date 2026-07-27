<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UserDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_documentation_requires_authentication(): void
    {
        $this->get(route('help.index'))->assertRedirect(route('login'));
        $this->get(route('help.show', 'dashboard'))->assertRedirect(route('login'));
    }

    public function test_admin_can_browse_the_complete_documentation(): void
    {
        $admin = $this->userWithRole('admin');
        $topicCount = count(config('user_documentation.topics'));

        $this->actingAs($admin)
            ->get(route('help.index'))
            ->assertOk()
            ->assertSee('Documentation utilisateur')
            ->assertSee((string) $topicCount)
            ->assertSee('guides disponibles')
            ->assertSee('Enregistrer un paiement et imprimer le reçu')
            ->assertSee('Créer et contrôler les sauvegardes');

        $this->actingAs($admin)
            ->get(route('help.show', 'payments'))
            ->assertOk()
            ->assertSee('Étapes à suivre')
            ->assertSee('Ouvrir le module')
            ->assertSee('En cas d’erreur, annulez le paiement');
    }

    public function test_documentation_is_filtered_by_role_permissions(): void
    {
        $teacher = $this->userWithRole('enseignant');

        $this->actingAs($teacher)
            ->get(route('help.index'))
            ->assertOk()
            ->assertSee('Créer une évaluation et saisir les notes')
            ->assertSee('Faire l’appel et justifier une absence')
            ->assertDontSee('Enregistrer un paiement et imprimer le reçu')
            ->assertDontSee('Configurer les rôles et permissions');

        $this->actingAs($teacher)
            ->get(route('help.show', 'payments'))
            ->assertNotFound();
    }

    public function test_documentation_search_finds_relevant_topics(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('help.index', ['search' => 'restauration']))
            ->assertOk()
            ->assertSee('Créer et contrôler les sauvegardes')
            ->assertDontSee('Créer une évaluation et saisir les notes');
    }

    public function test_every_documented_module_route_exists_and_every_topic_has_steps(): void
    {
        foreach (config('user_documentation.topics') as $slug => $topic) {
            $this->assertNotEmpty($topic['title'], "Le guide {$slug} n'a pas de titre.");
            $this->assertNotEmpty($topic['summary'], "Le guide {$slug} n'a pas de résumé.");
            $this->assertNotEmpty($topic['steps'], "Le guide {$slug} n'a pas d'étapes.");

            if ($topic['route'] ?? null) {
                $this->assertTrue(Route::has($topic['route']), "La route du guide {$slug} n'existe pas.");
            }
        }
    }

    private function userWithRole(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'username' => $role.'-documentation-test',
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
