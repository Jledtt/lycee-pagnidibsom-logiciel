<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_creation_requires_identity_fields(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create(['username' => 'student-validation-test', 'status' => 'active']);
        $user->assignRole('secretariat');

        $response = $this->actingAs($user)->post(route('students.store'), []);

        $response->assertSessionHasErrors(['first_name', 'last_name', 'gender']);
    }
}
