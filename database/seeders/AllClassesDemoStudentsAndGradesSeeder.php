<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class AllClassesDemoStudentsAndGradesSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AcademicBaselineSeeder::class);

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

        $this->ensureBaseClasses($academicYear);

        $term = $this->firstTerm($academicYear);
        $periods = $this->termPeriods($term);
        $devoirType = AssessmentType::query()->updateOrCreate(
            ['name' => 'Devoir'],
            ['weight' => 40, 'status' => 'active'],
        );

        SchoolClass::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->values()
            ->each(function (SchoolClass $schoolClass, int $classIndex) use ($academicYear, $term, $periods, $devoirType): void {
                $classSubjects = $this->classSubjects($schoolClass);
                $students = $this->students($academicYear, $schoolClass, $classIndex);

                $this->grades($academicYear, $term, $periods, $devoirType, $schoolClass, $classSubjects, $students, $classIndex);
            });
    }

    private function ensureBaseClasses(AcademicYear $academicYear): void
    {
        foreach ($this->baseClasses() as $item) {
            $level = Level::query()->firstOrCreate(
                ['name' => $item['level']],
                [
                    'cycle' => $item['cycle'],
                    'position' => $item['position'],
                ],
            );

            SchoolClass::query()->updateOrCreate(
                [
                    'academic_year_id' => $academicYear->id,
                    'name' => $item['name'],
                ],
                [
                    'level_id' => $level->id,
                    'code' => $item['code'],
                    'capacity' => 60,
                    'status' => 'active',
                ],
            );
        }
    }

    private function firstTerm(AcademicYear $academicYear): Term
    {
        return Term::query()->firstOrCreate(
            [
                'academic_year_id' => $academicYear->id,
                'position' => 1,
            ],
            [
                'name' => 'Trimestre 1',
                'type' => 'trimester',
                'starts_at' => '2026-10-01',
                'ends_at' => '2026-12-31',
                'is_closed' => false,
            ],
        );
    }

    private function termPeriods(Term $term): Collection
    {
        return collect([
            ['name' => '1er devoir', 'position' => 1, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31'],
            ['name' => '2e devoir', 'position' => 2, 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-30'],
            ['name' => '3e devoir', 'position' => 3, 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-31'],
        ])->map(fn (array $item) => TermPeriod::query()->updateOrCreate(
            [
                'term_id' => $term->id,
                'position' => $item['position'],
            ],
            [
                'name' => $item['name'],
                'starts_on' => $item['starts_on'],
                'ends_on' => $item['ends_on'],
                'status' => 'active',
            ],
        ))->values();
    }

    private function classSubjects(SchoolClass $schoolClass): Collection
    {
        $existing = ClassSubject::query()
            ->with('subject')
            ->where('school_class_id', $schoolClass->id)
            ->where('is_active', true)
            ->orderBy('subject_id')
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing->values();
        }

        return collect($this->subjectPlanFor($schoolClass))
            ->map(function (array $item) use ($schoolClass): ClassSubject {
                $subject = Subject::query()->updateOrCreate(
                    ['code' => $item['code']],
                    ['name' => $item['name'], 'status' => 'active'],
                );

                return ClassSubject::query()->updateOrCreate(
                    [
                        'school_class_id' => $schoolClass->id,
                        'subject_id' => $subject->id,
                    ],
                    [
                        'teacher_id' => null,
                        'coefficient' => $item['coefficient'],
                        'is_active' => true,
                    ],
                )->load('subject');
            })
            ->values();
    }

    private function students(AcademicYear $academicYear, SchoolClass $schoolClass, int $classIndex): Collection
    {
        return collect($this->profiles())
            ->map(function (array $profile, int $studentIndex) use ($academicYear, $schoolClass, $classIndex): Student {
                $serial = (($classIndex + 1) * 100) + ($studentIndex + 1);
                $classCode = $this->classCode($schoolClass);
                $birthYear = $this->birthYearFor($schoolClass);
                $matricule = sprintf('LPP-DEMO-%s-%02d', $classCode, $studentIndex + 1);

                $student = Student::query()->updateOrCreate(
                    ['matricule' => $matricule],
                    [
                        'first_name' => $profile['first_name'],
                        'last_name' => $profile['last_name'],
                        'gender' => $profile['gender'],
                        'birth_date' => sprintf('%d-%02d-%02d', $birthYear, ($studentIndex % 9) + 1, (($studentIndex * 3) % 26) + 1),
                        'birth_place' => $profile['birth_place'],
                        'desired_class' => $schoolClass->name,
                        'origin_school' => $profile['origin_school'],
                        'previous_class' => $this->previousClassFor($schoolClass),
                        'repeated_class' => $studentIndex % 5 === 0 ? $this->previousClassFor($schoolClass) : 'Aucune',
                        'address' => 'Ouagadougou - '.$profile['district'],
                        'nationality' => 'Burkinabè',
                        'ethnicity' => $profile['ethnicity'],
                        'religion' => $profile['religion'],
                        'sector' => 'Secteur '.(20 + $studentIndex),
                        'district' => $profile['district'],
                        'home_phone' => sprintf('70%06d', $serial),
                        'health_notes' => $profile['health_notes'],
                        'health_conditions' => $profile['health_conditions'],
                        'sport_aptitude' => $profile['sport_aptitude'],
                        'emergency_contact_name' => $profile['guardian_first_name'].' '.$profile['guardian_last_name'],
                        'emergency_contact_phone' => sprintf('71%06d', $serial),
                        'school_info_whatsapp' => sprintf('78%06d', $serial),
                        'status' => 'active',
                    ],
                );

                $guardian = Guardian::query()->updateOrCreate(
                    ['phone_primary' => sprintf('71%06d', $serial)],
                    [
                        'first_name' => $profile['guardian_first_name'],
                        'last_name' => $profile['guardian_last_name'],
                        'phone_secondary' => sprintf('76%06d', $serial),
                        'email' => sprintf('parent.demo.%s.%02d@example.com', strtolower($classCode), $studentIndex + 1),
                        'address' => 'Ouagadougou - '.$profile['district'],
                        'profession' => $profile['guardian_profession'],
                        'service' => $profile['guardian_service'],
                        'status' => 'active',
                    ],
                );

                $student->guardians()->syncWithoutDetaching([
                    $guardian->id => [
                        'relationship' => $profile['guardian_relationship'],
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
                        'enrollment_date' => '2026-07-24',
                        'type' => $studentIndex % 3 === 0 ? 'new' : 'renewal',
                        'status' => 'active',
                        'previous_school' => $profile['origin_school'],
                        'notes' => 'Profil de demonstration complet pour presentation.',
                    ],
                );

                return $student;
            })
            ->values();
    }

    private function grades(
        AcademicYear $academicYear,
        Term $term,
        Collection $periods,
        AssessmentType $devoirType,
        SchoolClass $schoolClass,
        Collection $classSubjects,
        Collection $students,
        int $classIndex,
    ): void {
        $periods->each(function (TermPeriod $period, int $periodIndex) use ($academicYear, $term, $devoirType, $schoolClass, $classSubjects, $students, $classIndex): void {
            $classSubjects->each(function (ClassSubject $classSubject, int $subjectIndex) use ($academicYear, $term, $period, $periodIndex, $devoirType, $schoolClass, $students, $classIndex): void {
                $subject = $classSubject->subject;

                $assessment = Assessment::query()->updateOrCreate(
                    [
                        'academic_year_id' => $academicYear->id,
                        'term_id' => $term->id,
                        'term_period_id' => $period->id,
                        'school_class_id' => $schoolClass->id,
                        'subject_id' => $subject->id,
                        'assessment_type_id' => $devoirType->id,
                    ],
                    [
                        'teacher_id' => $classSubject->teacher_id,
                        'title' => $period->name.' - '.$subject->name,
                        'max_score' => 20,
                        'assessment_date' => $period->starts_on,
                        'entry_mode' => $subject->code === 'EPS'
                            ? Assessment::ENTRY_MODE_ORAL_SPORT
                            : Assessment::ENTRY_MODE_STANDARD,
                        'is_locked' => false,
                    ],
                );

                $students->each(function (Student $student, int $studentIndex) use ($assessment, $classIndex, $subjectIndex, $periodIndex): void {
                    $dispensed = $assessment->entry_mode === Assessment::ENTRY_MODE_ORAL_SPORT && $studentIndex === 8;

                    Grade::query()->updateOrCreate(
                        [
                            'assessment_id' => $assessment->id,
                            'student_id' => $student->id,
                        ],
                        [
                            'score' => $dispensed ? null : $this->scoreFor($classIndex, $studentIndex, $subjectIndex, $periodIndex),
                            'is_absent' => false,
                            'status' => $dispensed ? Grade::STATUS_DISPENSED : Grade::STATUS_GRADED,
                            'comment' => $dispensed ? 'Dispense EPS' : null,
                            'entered_by' => null,
                        ],
                    );
                });
            });
        });
    }

    private function scoreFor(int $classIndex, int $studentIndex, int $subjectIndex, int $periodIndex): float
    {
        $score = 7.5
            + (($studentIndex * 1.25) % 5.5)
            + (($subjectIndex * 0.65) % 3.5)
            + ($periodIndex * 0.7)
            + (($classIndex % 3) * 0.35);

        if ($studentIndex === 0) {
            $score += 2.2;
        }

        if ($studentIndex === 9) {
            $score -= 1.7;
        }

        return round(max(4.5, min(18.75, $score)), 2);
    }

    private function subjectPlanFor(SchoolClass $schoolClass): array
    {
        $name = strtolower($schoolClass->name);

        if (str_contains($name, '2nde c')) {
            return $this->subjectsByCodes(['FR', 'MATH', 'PC', 'SVT', 'ANG', 'HG', 'EPS', 'ECM', 'TIC']);
        }

        if (str_contains($name, '2nde a')) {
            return $this->subjectsByCodes(['FR', 'PHILO', 'ANG', 'HG', 'MATH', 'SVT', 'EPS', 'ECM', 'ALL', 'ESP']);
        }

        if (str_contains($name, 'terminal')) {
            return $this->subjectsByCodes(['FR', 'PHILO', 'ANG', 'HG', 'MATH', 'PC', 'SVT', 'EPS', 'ECM']);
        }

        return $this->subjectsByCodes(['FR', 'MATH', 'ANG', 'HG', 'SVT', 'PC', 'EPS', 'ECM', 'ALL', 'TIC']);
    }

    private function subjectsByCodes(array $codes): array
    {
        $subjects = [
            'FR' => ['name' => 'Francais', 'coefficient' => 5],
            'MATH' => ['name' => 'Mathematiques', 'coefficient' => 4],
            'ANG' => ['name' => 'Anglais', 'coefficient' => 2],
            'HG' => ['name' => 'Histoire-Geographie', 'coefficient' => 3],
            'SVT' => ['name' => 'SVT', 'coefficient' => 2],
            'PC' => ['name' => 'Physique-Chimie', 'coefficient' => 3],
            'EPS' => ['name' => 'EPS', 'coefficient' => 2],
            'ECM' => ['name' => 'Education civique et morale', 'coefficient' => 1],
            'ALL' => ['name' => 'Allemand', 'coefficient' => 2],
            'ESP' => ['name' => 'Espagnol', 'coefficient' => 2],
            'TIC' => ['name' => 'TIC', 'coefficient' => 1],
            'PHILO' => ['name' => 'Philosophie', 'coefficient' => 2],
        ];

        return collect($codes)
            ->map(fn (string $code) => [
                'code' => $code,
                'name' => $subjects[$code]['name'],
                'coefficient' => $subjects[$code]['coefficient'],
            ])
            ->all();
    }

    private function baseClasses(): array
    {
        return [
            ['name' => '6e', 'code' => '6E', 'level' => '6e', 'cycle' => 'Premier cycle', 'position' => 1],
            ['name' => '5e', 'code' => '5E', 'level' => '5e', 'cycle' => 'Premier cycle', 'position' => 2],
            ['name' => '4e', 'code' => '4E', 'level' => '4e', 'cycle' => 'Premier cycle', 'position' => 3],
            ['name' => '3e', 'code' => '3E', 'level' => '3e', 'cycle' => 'Premier cycle', 'position' => 4],
            ['name' => '2nde A', 'code' => '2A', 'level' => '2nde', 'cycle' => 'Second cycle', 'position' => 5],
            ['name' => '2nde C', 'code' => '2C', 'level' => '2nde', 'cycle' => 'Second cycle', 'position' => 5],
            ['name' => 'Terminale', 'code' => 'TLE', 'level' => 'Terminale', 'cycle' => 'Second cycle', 'position' => 7],
        ];
    }

    private function profiles(): array
    {
        return [
            ['first_name' => 'Awa', 'last_name' => 'Ouedraogo', 'gender' => 'female', 'birth_place' => 'Ouagadougou', 'origin_school' => 'École Wend Panga', 'ethnicity' => 'Mossi', 'religion' => 'Musulmane', 'district' => 'Pagnidibsom', 'health_notes' => 'RAS', 'health_conditions' => [], 'sport_aptitude' => true, 'guardian_first_name' => 'Moussa', 'guardian_last_name' => 'Ouedraogo', 'guardian_profession' => 'Commerçant', 'guardian_service' => 'Marché', 'guardian_relationship' => 'father'],
            ['first_name' => 'Issa', 'last_name' => 'Kabore', 'gender' => 'male', 'birth_place' => 'Koudougou', 'origin_school' => 'École Sainte Famille', 'ethnicity' => 'Mossi', 'religion' => 'Chrétienne', 'district' => 'Tampouy', 'health_notes' => 'RAS', 'health_conditions' => [], 'sport_aptitude' => true, 'guardian_first_name' => 'Adama', 'guardian_last_name' => 'Kabore', 'guardian_profession' => 'Menuisier', 'guardian_service' => 'Atelier', 'guardian_relationship' => 'father'],
            ['first_name' => 'Mariam', 'last_name' => 'Compaore', 'gender' => 'female', 'birth_place' => 'Bobo-Dioulasso', 'origin_school' => 'Complexe Scolaire La Grace', 'ethnicity' => 'Bissa', 'religion' => 'Musulmane', 'district' => 'Karpala', 'health_notes' => 'RAS', 'health_conditions' => [], 'sport_aptitude' => true, 'guardian_first_name' => 'Aminata', 'guardian_last_name' => 'Compaore', 'guardian_profession' => 'Couturiere', 'guardian_service' => 'Atelier familial', 'guardian_relationship' => 'mother'],
            ['first_name' => 'Abdoul Karim', 'last_name' => 'Sawadogo', 'gender' => 'male', 'birth_place' => 'Ouahigouya', 'origin_school' => 'École La Source', 'ethnicity' => 'Mossi', 'religion' => 'Musulmane', 'district' => 'Dassasgho', 'health_notes' => 'Asthme léger signalé.', 'health_conditions' => ['asthme'], 'sport_aptitude' => false, 'guardian_first_name' => 'Souleymane', 'guardian_last_name' => 'Sawadogo', 'guardian_profession' => 'Agent commercial', 'guardian_service' => 'Commerce général', 'guardian_relationship' => 'father'],
            ['first_name' => 'Clarisse', 'last_name' => 'Bambara', 'gender' => 'female', 'birth_place' => 'Kaya', 'origin_school' => 'École Saint Joseph', 'ethnicity' => 'Gourmantche', 'religion' => 'Chrétienne', 'district' => 'Zogona', 'health_notes' => 'RAS', 'health_conditions' => [], 'sport_aptitude' => true, 'guardian_first_name' => 'Paul', 'guardian_last_name' => 'Bambara', 'guardian_profession' => 'Technicien', 'guardian_service' => 'Maintenance', 'guardian_relationship' => 'father'],
            ['first_name' => 'Boubacar', 'last_name' => 'Traore', 'gender' => 'male', 'birth_place' => 'Dédougou', 'origin_school' => 'École Lumière', 'ethnicity' => 'Dioula', 'religion' => 'Musulmane', 'district' => 'Wemtenga', 'health_notes' => 'RAS', 'health_conditions' => [], 'sport_aptitude' => true, 'guardian_first_name' => 'Fatoumata', 'guardian_last_name' => 'Traore', 'guardian_profession' => 'Restauratrice', 'guardian_service' => 'Restaurant familial', 'guardian_relationship' => 'mother'],
            ['first_name' => 'Estelle', 'last_name' => 'Somda', 'gender' => 'female', 'birth_place' => 'Gaoua', 'origin_school' => 'École Le Progrès', 'ethnicity' => 'Dagara', 'religion' => 'Chrétienne', 'district' => 'Gounghin', 'health_notes' => 'RAS', 'health_conditions' => [], 'sport_aptitude' => true, 'guardian_first_name' => 'Joseph', 'guardian_last_name' => 'Somda', 'guardian_profession' => 'Fonctionnaire', 'guardian_service' => 'Administration', 'guardian_relationship' => 'father'],
            ['first_name' => 'Noëlie', 'last_name' => 'Nikiema', 'gender' => 'female', 'birth_place' => 'Ouagadougou', 'origin_school' => 'École Pagnidibsom', 'ethnicity' => 'Mossi', 'religion' => 'Chrétienne', 'district' => 'Patte-d’Oie', 'health_notes' => 'RAS', 'health_conditions' => [], 'sport_aptitude' => true, 'guardian_first_name' => 'Thérèse', 'guardian_last_name' => 'Nikiema', 'guardian_profession' => 'Enseignante', 'guardian_service' => 'Primaire privé', 'guardian_relationship' => 'mother'],
            ['first_name' => 'Yacouba', 'last_name' => 'Zongo', 'gender' => 'male', 'birth_place' => 'Fada N’Gourma', 'origin_school' => 'École bilingue', 'ethnicity' => 'Gourmantche', 'religion' => 'Musulmane', 'district' => 'Kossodo', 'health_notes' => 'Dispense d’EPS temporaire.', 'health_conditions' => [], 'sport_aptitude' => false, 'guardian_first_name' => 'Mahamadi', 'guardian_last_name' => 'Zongo', 'guardian_profession' => 'Transporteur', 'guardian_service' => 'Transport', 'guardian_relationship' => 'father'],
            ['first_name' => 'Naomie', 'last_name' => 'Ilboudo', 'gender' => 'female', 'birth_place' => 'Tenkodogo', 'origin_school' => 'École Notre-Dame', 'ethnicity' => 'Mossi', 'religion' => 'Chrétienne', 'district' => 'Pissy', 'health_notes' => 'RAS', 'health_conditions' => [], 'sport_aptitude' => true, 'guardian_first_name' => 'Denis', 'guardian_last_name' => 'Ilboudo', 'guardian_profession' => 'Comptable', 'guardian_service' => 'Entreprise privée', 'guardian_relationship' => 'father'],
        ];
    }

    private function classCode(SchoolClass $schoolClass): string
    {
        $code = preg_replace('/[^A-Za-z0-9]+/', '', $schoolClass->code ?: $schoolClass->name);

        return strtoupper($code ?: 'CLASSE'.$schoolClass->id);
    }

    private function birthYearFor(SchoolClass $schoolClass): int
    {
        $name = strtolower($schoolClass->name);

        return match (true) {
            str_contains($name, 'terminal') => 2008,
            str_contains($name, '2nde') => 2010,
            str_contains($name, '3') => 2011,
            str_contains($name, '4') => 2012,
            str_contains($name, '5') => 2013,
            default => 2014,
        };
    }

    private function previousClassFor(SchoolClass $schoolClass): string
    {
        $name = strtolower($schoolClass->name);

        return match (true) {
            str_contains($name, 'terminal') => '1re',
            str_contains($name, '2nde') => '3e',
            str_contains($name, '3') => '4e',
            str_contains($name, '4') => '5e',
            str_contains($name, '5') => '6e',
            default => 'CM2',
        };
    }
}
