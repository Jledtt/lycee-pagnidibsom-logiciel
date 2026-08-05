<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Seeder;

class TerminaleDemoStudentSeeder extends Seeder
{
    public function run(): void
    {
        $academicYear = AcademicYear::query()
            ->where('is_active', true)
            ->first()
            ?? AcademicYear::query()->firstOrCreate(
                ['name' => '2026-2027'],
                [
                    'starts_at' => '2026-10-01',
                    'ends_at' => '2027-07-31',
                    'is_active' => true,
                    'status' => 'active',
                ],
            );

        $level = Level::query()->firstOrCreate(
            ['name' => 'Terminale'],
            [
                'cycle' => 'Second cycle',
                'position' => 7,
            ],
        );

        $schoolClass = SchoolClass::query()->updateOrCreate(
            [
                'academic_year_id' => $academicYear->id,
                'name' => 'Terminale',
            ],
            [
                'level_id' => $level->id,
                'code' => 'TLE',
                'capacity' => 60,
                'status' => 'active',
            ],
        );

        foreach ($this->students() as $item) {
            $student = Student::query()->updateOrCreate(
                ['matricule' => $item['student']['matricule']],
                $item['student'],
            );

            foreach ($item['guardians'] as $guardianData) {
                $guardian = Guardian::query()->updateOrCreate(
                    ['phone_primary' => $guardianData['phone_primary']],
                    [
                        'first_name' => $guardianData['first_name'],
                        'last_name' => $guardianData['last_name'],
                        'phone_secondary' => $guardianData['phone_secondary'] ?? null,
                        'email' => $guardianData['email'] ?? null,
                        'address' => $guardianData['address'] ?? $item['student']['address'],
                        'profession' => $guardianData['profession'],
                        'service' => $guardianData['service'] ?? null,
                        'status' => 'active',
                    ],
                );

                $student->guardians()->syncWithoutDetaching([
                    $guardian->id => [
                        'relationship' => $guardianData['relationship'],
                        'is_primary' => $guardianData['is_primary'],
                        'can_receive_sms' => true,
                        'can_pickup_child' => true,
                    ],
                ]);
            }

            Enrollment::query()->updateOrCreate(
                [
                    'academic_year_id' => $academicYear->id,
                    'student_id' => $student->id,
                ],
                [
                    'school_class_id' => $schoolClass->id,
                    'enrollment_date' => '2026-07-22',
                    'type' => 'new',
                    'status' => 'active',
                    'previous_school' => $item['student']['origin_school'],
                    'notes' => 'Profil de demonstration Terminale.',
                ],
            );
        }
    }

    private function students(): array
    {
        return [
            [
                'student' => [
                    'matricule' => 'LPP-2026-TLE-001',
                    'first_name' => 'Mariam',
                    'last_name' => 'Ouedraogo',
                    'gender' => 'female',
                    'birth_date' => '2008-03-14',
                    'birth_place' => 'Ouagadougou',
                    'desired_class' => 'Terminale',
                    'origin_school' => 'Lycée Privé Pagnidibsom',
                    'previous_class' => '1re D',
                    'repeated_class' => 'Aucune',
                    'address' => 'Ouagadougou - Pagnidibsom',
                    'nationality' => 'Burkinabè',
                    'ethnicity' => 'Mossi',
                    'religion' => 'Musulmane',
                    'sector' => 'Secteur 29',
                    'district' => 'Pagnidibsom',
                    'home_phone' => '70010203',
                    'health_notes' => 'RAS',
                    'health_conditions' => [],
                    'sport_aptitude' => true,
                    'emergency_contact_name' => 'Salif Ouedraogo',
                    'emergency_contact_phone' => '70111213',
                    'school_info_whatsapp' => '70222324',
                    'status' => 'active',
                ],
                'guardians' => [
                    [
                        'first_name' => 'Salif',
                        'last_name' => 'Ouedraogo',
                        'phone_primary' => '70111213',
                        'profession' => 'Commerçant',
                        'service' => 'Marché de Pagnidibsom',
                        'relationship' => 'father',
                        'is_primary' => true,
                    ],
                    [
                        'first_name' => 'Aminata',
                        'last_name' => 'Sawadogo',
                        'phone_primary' => '76111213',
                        'profession' => 'Menagere',
                        'service' => null,
                        'relationship' => 'mother',
                        'is_primary' => false,
                    ],
                ],
            ],
            [
                'student' => [
                    'matricule' => 'LPP-2026-TLE-002',
                    'first_name' => 'Abdoul Karim',
                    'last_name' => 'Kabore',
                    'gender' => 'male',
                    'birth_date' => '2007-11-22',
                    'birth_place' => 'Koudougou',
                    'desired_class' => 'Terminale',
                    'origin_school' => 'Lycee Provincial de Koudougou',
                    'previous_class' => '1re A',
                    'repeated_class' => 'Aucune',
                    'address' => 'Ouagadougou - Tampouy',
                    'nationality' => 'Burkinabè',
                    'ethnicity' => 'Mossi',
                    'religion' => 'Musulmane',
                    'sector' => 'Secteur 16',
                    'district' => 'Tampouy',
                    'home_phone' => '78040506',
                    'health_notes' => 'Asthme leger signale par le tuteur.',
                    'health_conditions' => ['asthme'],
                    'sport_aptitude' => false,
                    'emergency_contact_name' => 'Moussa Kabore',
                    'emergency_contact_phone' => '71040506',
                    'school_info_whatsapp' => '78040506',
                    'status' => 'active',
                ],
                'guardians' => [
                    [
                        'first_name' => 'Moussa',
                        'last_name' => 'Kabore',
                        'phone_primary' => '71040506',
                        'profession' => 'Agent commercial',
                        'service' => 'Commerce general',
                        'relationship' => 'father',
                        'is_primary' => true,
                    ],
                    [
                        'first_name' => 'Fati',
                        'last_name' => 'Compaore',
                        'phone_primary' => '76040506',
                        'profession' => 'Couturiere',
                        'service' => 'Atelier familial',
                        'relationship' => 'mother',
                        'is_primary' => false,
                    ],
                ],
            ],
            [
                'student' => [
                    'matricule' => 'LPP-2026-TLE-003',
                    'first_name' => 'Estelle Nadege',
                    'last_name' => 'Somda',
                    'gender' => 'female',
                    'birth_date' => '2008-06-05',
                    'birth_place' => 'Bobo-Dioulasso',
                    'desired_class' => 'Terminale',
                    'origin_school' => 'Lycee Municipal de Bobo-Dioulasso',
                    'previous_class' => '1re C',
                    'repeated_class' => 'Aucune',
                    'address' => 'Ouagadougou - Karpala',
                    'nationality' => 'Burkinabè',
                    'ethnicity' => 'Bissa',
                    'religion' => 'Chrétienne',
                    'sector' => 'Secteur 51',
                    'district' => 'Karpala',
                    'home_phone' => '78990011',
                    'health_notes' => 'RAS',
                    'health_conditions' => [],
                    'sport_aptitude' => true,
                    'emergency_contact_name' => 'Paul Somda',
                    'emergency_contact_phone' => '71990011',
                    'school_info_whatsapp' => '77990011',
                    'status' => 'active',
                ],
                'guardians' => [
                    [
                        'first_name' => 'Paul',
                        'last_name' => 'Somda',
                        'phone_primary' => '71990011',
                        'profession' => 'Technicien',
                        'service' => 'Maintenance industrielle',
                        'relationship' => 'father',
                        'is_primary' => true,
                    ],
                    [
                        'first_name' => 'Clarisse',
                        'last_name' => 'Bambara',
                        'phone_primary' => '76990011',
                        'profession' => 'Enseignante',
                        'service' => 'Primaire privé',
                        'relationship' => 'mother',
                        'is_primary' => false,
                    ],
                ],
            ],
        ];
    }
}
