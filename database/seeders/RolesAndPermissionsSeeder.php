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
            'guardians.view',
            'guardians.manage',
            'enrollments.view',
            'enrollments.create',
            'enrollments.update',
            'enrollments.cancel',
            'payments.view',
            'payments.create',
            'payments.backdate',
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
            'discipline.view',
            'discipline.manage',
            'timetables.view',
            'timetables.manage',
            'timetables.print',
            'users.manage',
            'roles.manage',
            'activity_logs.view',
            'settings.manage',
            'academic_years.manage',
            'academic_tracks.view',
            'academic_tracks.manage',
            'classes.manage',
            'subjects.manage',
            'communications.view',
            'communications.send',
            'communications.templates.manage',
            'teachers.view',
            'teachers.manage',
            'teacher_attendance.view',
            'teacher_attendance.manage',
            'teacher_fees.view',
            'teacher_fees.manage',
            'teacher_fees.approve',
            'teacher_fees.pay',
            'teacher_documents.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        Role::query()
            ->whereIn('name', ['parent', 'eleve'])
            ->get()
            ->each(function (Role $role): void {
                $role->users()->detach();
                $role->delete();
            });

        $roles = [
            'admin' => $permissions,
            'direction' => [
                'students.view',
                'students.export',
                'guardians.view',
                'guardians.manage',
                'enrollments.view',
                'payments.view',
                'payments.reports',
                'payments.backdate',
                'grades.view',
                'mock_exams.view',
                'mock_exams.print',
                'report_cards.view',
                'report_cards.validate',
                'report_cards.print',
                'attendance.view',
                'attendance.reports',
                'discipline.view',
                'discipline.manage',
                'timetables.view',
                'timetables.print',
                'activity_logs.view',
                'communications.view',
                'communications.send',
                'communications.templates.manage',
                'teachers.view',
                'teachers.manage',
                'teacher_attendance.view',
                'teacher_attendance.manage',
                'teacher_fees.view',
                'teacher_fees.manage',
                'teacher_fees.approve',
                'teacher_documents.manage',
                'academic_tracks.view',
                'academic_tracks.manage',
            ],
            'secretariat' => [
                'students.view',
                'students.create',
                'students.update',
                'students.export',
                'students.import',
                'guardians.view',
                'guardians.manage',
                'enrollments.view',
                'enrollments.create',
                'enrollments.update',
                'classes.manage',
                'academic_tracks.view',
                'academic_tracks.manage',
                'mock_exams.view',
                'mock_exams.manage',
                'mock_exams.print',
                'timetables.view',
                'timetables.manage',
                'timetables.print',
                'communications.view',
                'communications.send',
                'teachers.view',
                'teachers.manage',
                'teacher_attendance.view',
                'teacher_attendance.manage',
                'teacher_documents.manage',
            ],
            'comptable' => [
                'students.view',
                'payments.view',
                'payments.create',
                'payments.cancel',
                'payments.print_receipt',
                'payments.reports',
                'communications.view',
                'teachers.view',
                'teacher_fees.view',
                'teacher_fees.manage',
                'teacher_fees.pay',
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
                'teachers.view',
                'teacher_attendance.view',
                'teacher_fees.view',
            ],
            'surveillant' => [
                'students.view',
                'attendance.view',
                'attendance.create',
                'attendance.update',
                'attendance.justify',
                'attendance.reports',
                'discipline.view',
                'discipline.manage',
                'mock_exams.view',
                'mock_exams.print',
                'timetables.view',
                'teachers.view',
                'teacher_attendance.view',
                'teacher_attendance.manage',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName);
            $role->syncPermissions($rolePermissions);
        }
    }
}
