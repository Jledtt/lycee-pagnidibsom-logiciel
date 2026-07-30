<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class E2eWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('e2e'), 403, 'Ce seeder est réservé aux tests navigateur.');

        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->where('name', '5e')->firstOrFail();
        $teacher = User::query()->where('username', 'enseignant')->firstOrFail();
        $subject = Subject::query()->where('code', 'FR')->firstOrFail();

        $schoolClass = SchoolClass::query()->updateOrCreate(
            [
                'academic_year_id' => $academicYear->id,
                'name' => 'E2E 5e A',
            ],
            [
                'level_id' => $level->id,
                'code' => 'E2E-5A',
                'main_teacher_id' => $teacher->id,
                'capacity' => 40,
                'status' => 'active',
            ],
        );

        $student = Student::query()->updateOrCreate(
            ['matricule' => 'LPP-E2E-001'],
            [
                'first_name' => 'Aminata',
                'last_name' => 'Workflow',
                'gender' => 'female',
                'birth_date' => '2012-04-15',
                'desired_class' => '5e',
                'status' => 'active',
            ],
        );

        $guardian = Guardian::query()->updateOrCreate(
            ['email' => 'parent.e2e@gmail.com'],
            [
                'first_name' => 'Mariam',
                'last_name' => 'Workflow',
                'phone_primary' => '70000001',
                'status' => 'active',
            ],
        );
        $student->guardians()->syncWithoutDetaching([
            $guardian->id => [
                'relationship' => 'tutor',
                'is_primary' => true,
                'can_receive_sms' => true,
                'can_pickup_child' => true,
            ],
        ]);

        Enrollment::query()->updateOrCreate(
            [
                'academic_year_id' => $academicYear->id,
                'student_id' => $student->id,
            ],
            [
                'school_class_id' => $schoolClass->id,
                'enrollment_date' => '2026-10-01',
                'type' => 'new',
                'status' => 'active',
            ],
        );

        $feeType = FeeType::query()->where('code', 'INS')->firstOrFail();
        FeeSchedule::query()->updateOrCreate(
            [
                'academic_year_id' => $academicYear->id,
                'school_class_id' => $schoolClass->id,
                'fee_type_id' => $feeType->id,
                'period' => 'Inscription E2E',
            ],
            [
                'amount' => 25000,
                'due_date' => '2026-10-31',
            ],
        );

        TeacherProfile::query()->updateOrCreate(
            ['user_id' => $teacher->id],
            [
                'employee_number' => 'PROF-E2E-001',
                'specialty' => $subject->name,
                'identity_document_type' => 'CNIB',
                'identity_document_number' => 'B-E2E-001',
                'default_hourly_rate' => 2500,
                'withholding_tax_rate' => 2,
                'payment_method' => 'Virement',
            ],
        );
        ClassSubject::query()->updateOrCreate(
            [
                'school_class_id' => $schoolClass->id,
                'subject_id' => $subject->id,
            ],
            [
                'teacher_id' => $teacher->id,
                'coefficient' => 2,
                'is_active' => true,
            ],
        );
    }
}
