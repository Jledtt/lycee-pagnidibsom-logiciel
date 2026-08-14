<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Services\GuardianAssignmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GuardianManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_can_create_an_administrative_guardian_without_creating_an_account(): void
    {
        $this->seed(DatabaseSeeder::class);
        $secretary = $this->userWithRole('secretariat');
        $student = $this->student('RESP-01');
        $userCount = User::query()->count();

        $response = $this->actingAs($secretary)->post(route('guardians.store'), [
            'first_name' => 'Awa',
            'last_name' => 'Sawadogo',
            'phone_primary' => '70001122',
            'email' => 'awa@example.test',
            'profession' => 'Commerçante',
            'status' => 'active',
            'student_id' => $student->id,
            'relationship' => 'mother',
            'can_receive_sms' => '1',
        ]);

        $guardian = Guardian::query()->where('phone_primary', '70001122')->firstOrFail();

        $response->assertRedirect(route('guardians.show', $guardian));
        $this->assertSame($userCount, User::query()->count());
        $this->assertNull($guardian->user_id);
        $this->assertDatabaseHas('guardian_student', [
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'relationship' => 'mother',
            'is_primary' => true,
            'can_receive_sms' => true,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'created',
            'auditable_type' => Guardian::class,
            'auditable_id' => (string) $guardian->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'guardian.student_attached',
            'auditable_type' => Guardian::class,
            'auditable_id' => (string) $guardian->id,
        ]);
    }

    public function test_one_guardian_can_be_linked_to_multiple_students(): void
    {
        $this->seed(DatabaseSeeder::class);
        $secretary = $this->userWithRole('secretariat');
        $firstStudent = $this->student('RESP-02-A');
        $secondStudent = $this->student('RESP-02-B');
        $guardian = $this->guardian('Kaboré', '70002233');
        app(GuardianAssignmentService::class)->attach($guardian, $firstStudent, 'father', true, true, true);

        $this->actingAs($secretary)
            ->post(route('guardians.students.store', $guardian), [
                'student_id' => $secondStudent->id,
                'relationship' => 'father',
                'is_primary' => '1',
                'can_receive_sms' => '1',
                'can_pickup_child' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('guardian_student', [
            'guardian_id' => $guardian->id,
            'student_id' => $firstStudent->id,
        ]);
        $this->assertDatabaseHas('guardian_student', [
            'guardian_id' => $guardian->id,
            'student_id' => $secondStudent->id,
            'can_pickup_child' => true,
        ]);
    }

    public function test_replacing_a_relationship_preserves_the_previous_guardian_record(): void
    {
        $this->seed(DatabaseSeeder::class);
        $secretary = $this->userWithRole('secretariat');
        $student = $this->student('RESP-03');
        $previousMother = $this->guardian('Ouédraogo', '70003344');
        app(GuardianAssignmentService::class)->attach($previousMother, $student, 'mother', true, true, false);

        $this->actingAs($secretary)->post(route('guardians.store'), [
            'first_name' => 'Mariam',
            'last_name' => 'Kinda',
            'phone_primary' => '70004455',
            'status' => 'active',
            'student_id' => $student->id,
            'relationship' => 'mother',
            'can_receive_sms' => '1',
        ])->assertRedirect();

        $newMother = Guardian::query()->where('phone_primary', '70004455')->firstOrFail();

        $this->assertDatabaseHas('guardians', ['id' => $previousMother->id]);
        $this->assertDatabaseMissing('guardian_student', [
            'guardian_id' => $previousMother->id,
            'student_id' => $student->id,
        ]);
        $this->assertDatabaseHas('guardian_student', [
            'guardian_id' => $newMother->id,
            'student_id' => $student->id,
            'relationship' => 'mother',
            'is_primary' => true,
        ]);
    }

    public function test_removing_the_primary_guardian_promotes_an_existing_relationship_without_deleting_people(): void
    {
        $this->seed(DatabaseSeeder::class);
        $secretary = $this->userWithRole('secretariat');
        $student = $this->student('RESP-04');
        $father = $this->guardian('Nana', '70005566');
        $mother = $this->guardian('Compaoré', '70006677');
        $assignments = app(GuardianAssignmentService::class);
        $assignments->attach($father, $student, 'father', true, true, false);
        $assignments->attach($mother, $student, 'mother', false, true, false);

        $this->actingAs($secretary)
            ->delete(route('guardians.students.destroy', [$father, $student]))
            ->assertRedirect();

        $this->assertDatabaseHas('guardians', ['id' => $father->id]);
        $this->assertDatabaseHas('guardians', ['id' => $mother->id]);
        $this->assertDatabaseMissing('guardian_student', [
            'guardian_id' => $father->id,
            'student_id' => $student->id,
        ]);
        $this->assertDatabaseHas('guardian_student', [
            'guardian_id' => $mother->id,
            'student_id' => $student->id,
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'guardian.primary_promoted',
            'auditable_type' => Guardian::class,
            'auditable_id' => (string) $mother->id,
        ]);
    }

    public function test_only_administration_direction_and_secretariat_can_open_guardian_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $guardian = $this->guardian('Accès', '70007788');

        foreach (['admin', 'direction', 'secretariat'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('guardians.index'))->assertOk();
            $this->actingAs($user)->get(route('guardians.create'))->assertOk();
            $this->actingAs($user)->get(route('guardians.show', $guardian))->assertOk();
            $this->actingAs($user)->get(route('guardians.edit', $guardian))->assertOk();
        }

        foreach (['comptable', 'enseignant', 'surveillant'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('guardians.show', $guardian))
                ->assertForbidden();
        }
    }

    public function test_unauthorized_roles_cannot_modify_guardians_even_by_direct_url(): void
    {
        $this->seed(DatabaseSeeder::class);
        $guardian = $this->guardian('Protégé', '70008899');
        $student = $this->student('RESP-05');

        foreach (['comptable', 'enseignant', 'surveillant'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->post(route('guardians.students.store', $guardian), [
                    'student_id' => $student->id,
                    'relationship' => 'other',
                ])
                ->assertForbidden();
        }

        $this->assertSame(0, DB::table('guardian_student')
            ->where('guardian_id', $guardian->id)
            ->where('student_id', $student->id)
            ->count());
    }

    private function student(string $suffix): Student
    {
        return Student::query()->create([
            'matricule' => 'LPP-'.$suffix,
            'first_name' => 'Élève',
            'last_name' => $suffix,
            'gender' => 'female',
            'status' => 'active',
        ]);
    }

    private function guardian(string $lastName, string $phone): Guardian
    {
        return Guardian::query()->create([
            'first_name' => 'Responsable',
            'last_name' => $lastName,
            'phone_primary' => $phone,
            'status' => 'active',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-guardian-'.str()->lower(str()->random(6)),
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
