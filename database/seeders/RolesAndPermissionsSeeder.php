<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'students.view',
            'students.create',
            'students.update',
            'students.delete',
            'students.export',
            'students.import',
            'enrollments.view',
            'enrollments.create',
            'enrollments.update',
            'enrollments.cancel',
            'payments.view',
            'payments.create',
            'payments.cancel',
            'payments.print_receipt',
            'payments.reports',
            'grades.view',
            'grades.create',
            'grades.update',
            'grades.lock',
            'grades.unlock',
            'mock_exams.view',
            'mock_exams.manage',
            'mock_exams.print',
            'report_cards.view',
            'report_cards.generate',
            'report_cards.validate',
            'report_cards.publish',
            'report_cards.print',
            'attendance.view',
            'attendance.create',
            'attendance.update',
            'attendance.justify',
            'attendance.reports',
            'timetables.view',
            'timetables.manage',
            'timetables.print',
            'users.manage',
            'roles.manage',
            'activity_logs.view',
            'settings.manage',
            'academic_years.manage',
            'classes.manage',
            'subjects.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $roles = [
            'admin' => $permissions,
            'direction' => [
                'students.view',
                'students.export',
                'enrollments.view',
                'payments.view',
                'payments.reports',
                'grades.view',
                'mock_exams.view',
                'mock_exams.print',
                'report_cards.view',
                'report_cards.validate',
                'report_cards.print',
                'attendance.view',
                'attendance.reports',
                'timetables.view',
                'timetables.print',
                'activity_logs.view',
            ],
            'secretariat' => [
                'students.view',
                'students.create',
                'students.update',
                'students.export',
                'students.import',
                'enrollments.view',
                'enrollments.create',
                'enrollments.update',
                'classes.manage',
                'mock_exams.view',
                'mock_exams.manage',
                'mock_exams.print',
                'timetables.view',
                'timetables.manage',
                'timetables.print',
            ],
            'comptable' => [
                'students.view',
                'payments.view',
                'payments.create',
                'payments.cancel',
                'payments.print_receipt',
                'payments.reports',
            ],
            'enseignant' => [
                'students.view',
                'grades.view',
                'grades.create',
                'grades.update',
                'mock_exams.view',
                'attendance.view',
                'attendance.create',
                'timetables.view',
            ],
            'surveillant' => [
                'students.view',
                'attendance.view',
                'attendance.create',
                'attendance.update',
                'attendance.justify',
                'attendance.reports',
                'mock_exams.view',
                'mock_exams.print',
                'timetables.view',
            ],
            'parent' => [
                'report_cards.view',
                'attendance.view',
                'payments.view',
            ],
            'eleve' => [
                'report_cards.view',
                'grades.view',
                'attendance.view',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName);
            $role->syncPermissions($rolePermissions);
        }
    }
}
