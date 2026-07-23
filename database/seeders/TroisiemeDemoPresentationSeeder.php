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
use App\Models\MockExam;
use App\Models\MockExamCandidate;
use App\Models\MockExamScore;
use App\Models\MockExamSubject;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermPeriod;
use App\Models\User;
use App\Services\TariffDefaultService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TroisiemeDemoPresentationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AcademicBaselineSeeder::class);

        $this->clearStudentDemoData();

        $academicYear = AcademicYear::query()->where('is_active', true)->first()
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
            ['name' => '3e'],
            ['cycle' => 'Premier cycle', 'position' => 4],
        );

        $schoolClass = SchoolClass::query()->updateOrCreate(
            ['academic_year_id' => $academicYear->id, 'name' => '3e'],
            [
                'level_id' => $level->id,
                'code' => '3E',
                'capacity' => 60,
                'status' => 'active',
            ],
        );

        $classSubjects = $this->upsertThirdGradeSubjects($schoolClass);
        app(TariffDefaultService::class)->applyToClass($academicYear, $schoolClass);

        $students = $this->createStudents($academicYear, $schoolClass);
        $this->createNormalGrades($academicYear, $schoolClass, $classSubjects, $students);
        $this->createMockExam($academicYear, $schoolClass, $classSubjects, $students);
    }

    private function clearStudentDemoData(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        foreach ($this->studentDataTables() as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    private function studentDataTables(): array
    {
        return [
            'mock_exam_scores',
            'mock_exam_candidates',
            'mock_exam_subjects',
            'mock_exam_classes',
            'mock_exams',
            'report_cards',
            'grades',
            'assessments',
            'payment_lines',
            'payments',
            'attendance_records',
            'attendance_sessions',
            'student_exit_authorizations',
            'student_documents',
            'disciplinary_records',
            'guardian_student',
            'guardians',
            'enrollments',
            'students',
        ];
    }

    private function upsertThirdGradeSubjects(SchoolClass $schoolClass): Collection
    {
        return collect($this->thirdGradeSubjects())
            ->map(function (array $item) use ($schoolClass) {
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

    private function thirdGradeSubjects(): array
    {
        return [
            ['code' => 'FR', 'name' => 'Francais', 'coefficient' => 5],
            ['code' => 'MATH', 'name' => 'Mathematiques', 'coefficient' => 4],
            ['code' => 'ANG', 'name' => 'Anglais', 'coefficient' => 2],
            ['code' => 'HG', 'name' => 'Histoire-Geographie', 'coefficient' => 3],
            ['code' => 'SVT', 'name' => 'Sciences de la Vie et de la Terre', 'coefficient' => 2],
            ['code' => 'PC', 'name' => 'Physique-Chimie', 'coefficient' => 3],
            ['code' => 'EPS', 'name' => 'Education Physique et Sportive', 'coefficient' => 2],
            ['code' => 'ECM', 'name' => 'Education civique et morale', 'coefficient' => 1],
            ['code' => 'ALL', 'name' => 'Allemand', 'coefficient' => 2],
            ['code' => 'TIC', 'name' => 'TIC', 'coefficient' => 2],
        ];
    }

    private function createStudents(AcademicYear $academicYear, SchoolClass $schoolClass): Collection
    {
        return collect($this->studentProfiles())
            ->map(function (array $profile, int $index) use ($academicYear, $schoolClass) {
                $student = Student::query()->updateOrCreate(
                    ['matricule' => sprintf('LPP-2026-3E-%03d', $index + 1)],
                    [
                        'first_name' => $profile['first_name'],
                        'last_name' => $profile['last_name'],
                        'gender' => $profile['gender'],
                        'birth_date' => $profile['birth_date'],
                        'birth_place' => $profile['birth_place'],
                        'desired_class' => '3e',
                        'origin_school' => $profile['origin_school'],
                        'previous_class' => '4e',
                        'repeated_class' => $profile['repeated_class'],
                        'address' => $profile['address'],
                        'nationality' => 'Burkinabe',
                        'ethnicity' => $profile['ethnicity'],
                        'religion' => $profile['religion'],
                        'sector' => $profile['sector'],
                        'district' => $profile['district'],
                        'home_phone' => $profile['home_phone'],
                        'health_notes' => $profile['health_notes'],
                        'health_conditions' => $profile['health_conditions'],
                        'sport_aptitude' => $profile['sport_aptitude'],
                        'emergency_contact_name' => $profile['emergency_name'],
                        'emergency_contact_phone' => $profile['emergency_phone'],
                        'school_info_whatsapp' => $profile['whatsapp'],
                        'status' => 'active',
                    ],
                );

                $guardian = Guardian::query()->updateOrCreate(
                    ['phone_primary' => $profile['guardian_phone']],
                    [
                        'first_name' => $profile['guardian_first_name'],
                        'last_name' => $profile['guardian_last_name'],
                        'phone_secondary' => $profile['guardian_secondary_phone'],
                        'email' => $profile['guardian_email'],
                        'address' => $profile['address'],
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
                        'enrollment_date' => '2026-07-23',
                        'type' => 'renewal',
                        'status' => 'active',
                        'previous_school' => $profile['origin_school'],
                        'notes' => 'Profil complet cree pour la demonstration.',
                        'created_by' => $this->adminUser()?->id,
                    ],
                );

                return $student->refresh();
            })
            ->values();
    }

    private function createNormalGrades(AcademicYear $academicYear, SchoolClass $schoolClass, Collection $classSubjects, Collection $students): void
    {
        $term = $this->firstTerm($academicYear);
        $devoirType = AssessmentType::query()->firstOrCreate(
            ['name' => 'Devoir'],
            ['weight' => 40, 'status' => 'active'],
        );
        $compositionType = AssessmentType::query()->firstOrCreate(
            ['name' => 'Composition'],
            ['weight' => 60, 'status' => 'active'],
        );
        $periods = $this->termPeriods($term);
        $admin = $this->adminUser();

        $classSubjects->each(function (ClassSubject $classSubject, int $subjectIndex) use ($academicYear, $schoolClass, $students, $term, $periods, $devoirType, $compositionType, $admin) {
            $periods->each(function (TermPeriod $period, int $periodIndex) use ($academicYear, $schoolClass, $students, $term, $devoirType, $classSubject, $subjectIndex, $admin) {
                $assessment = $this->upsertAssessment([
                    'academic_year_id' => $academicYear->id,
                    'term_id' => $term->id,
                    'term_period_id' => $period->id,
                    'school_class_id' => $schoolClass->id,
                    'subject_id' => $classSubject->subject_id,
                    'assessment_type_id' => $devoirType->id,
                    'title' => $period->name.' - '.$classSubject->subject->name,
                    'max_score' => 20,
                    'assessment_date' => Carbon::parse('2026-11-04')->addDays(($periodIndex * 14) + $subjectIndex)->toDateString(),
                    'entry_mode' => Assessment::ENTRY_MODE_STANDARD,
                    'is_locked' => false,
                ]);

                $this->fillGrades($assessment, $students, $subjectIndex, $periodIndex, $admin);
            });

            $assessment = $this->upsertAssessment([
                'academic_year_id' => $academicYear->id,
                'term_id' => $term->id,
                'term_period_id' => $periods->last()?->id,
                'school_class_id' => $schoolClass->id,
                'subject_id' => $classSubject->subject_id,
                'assessment_type_id' => $compositionType->id,
                'title' => 'Composition Trimestre 1 - '.$classSubject->subject->name,
                'max_score' => 20,
                'assessment_date' => Carbon::parse('2026-12-12')->addDays($subjectIndex)->toDateString(),
                'entry_mode' => Assessment::ENTRY_MODE_STANDARD,
                'is_locked' => false,
            ]);

            $this->fillGrades($assessment, $students, $subjectIndex, 4, $admin);
        });
    }

    private function upsertAssessment(array $data): Assessment
    {
        $attributes = [
            'academic_year_id' => $data['academic_year_id'],
            'term_id' => $data['term_id'],
            'school_class_id' => $data['school_class_id'],
            'subject_id' => $data['subject_id'],
            'assessment_type_id' => $data['assessment_type_id'],
            'title' => $data['title'],
        ];

        if (Schema::hasColumn('assessments', 'term_period_id')) {
            $attributes['term_period_id'] = $data['term_period_id'];
        }

        $values = [
            'teacher_id' => null,
            'max_score' => $data['max_score'],
            'assessment_date' => $data['assessment_date'],
            'is_locked' => $data['is_locked'],
        ];

        if (Schema::hasColumn('assessments', 'entry_mode')) {
            $values['entry_mode'] = $data['entry_mode'];
        }

        return Assessment::query()->updateOrCreate($attributes, $values);
    }

    private function fillGrades(Assessment $assessment, Collection $students, int $subjectIndex, int $assessmentIndex, ?User $admin): void
    {
        $students->each(function (Student $student, int $studentIndex) use ($assessment, $subjectIndex, $assessmentIndex, $admin) {
            $values = [
                'score' => $this->scoreFor($studentIndex, $subjectIndex, $assessmentIndex),
                'is_absent' => false,
                'comment' => 'Note de demonstration',
                'entered_by' => $admin?->id,
            ];

            if (Schema::hasColumn('grades', 'status')) {
                $values['status'] = Grade::STATUS_GRADED;
            }

            Grade::query()->updateOrCreate(
                ['assessment_id' => $assessment->id, 'student_id' => $student->id],
                $values,
            );
        });
    }

    private function createMockExam(AcademicYear $academicYear, SchoolClass $schoolClass, Collection $classSubjects, Collection $students): void
    {
        $mockExamData = [
            'exam_type' => 'bepc_blanc',
            'starts_on' => '2027-03-02',
            'ends_on' => '2027-03-06',
            'status' => 'finished',
            'notes' => 'Examen blanc cree pour la presentation de la classe de 3e.',
        ];

        if (Schema::hasColumn('mock_exams', 'term_id')) {
            $mockExamData['term_id'] = $this->firstTerm($academicYear)->id;
        }

        if (Schema::hasColumn('mock_exams', 'result_status')) {
            $mockExamData['result_status'] = 'provisoire';
        }

        $mockExam = MockExam::query()->updateOrCreate(
            ['academic_year_id' => $academicYear->id, 'name' => 'BEPC Blanc 1 - 3e'],
            $mockExamData,
        );

        $mockExam->classes()->syncWithoutDetaching([$schoolClass->id]);

        $candidates = $students->map(function (Student $student, int $index) use ($mockExam, $schoolClass) {
            return MockExamCandidate::query()->updateOrCreate(
                ['mock_exam_id' => $mockExam->id, 'student_id' => $student->id],
                [
                    'school_class_id' => $schoolClass->id,
                    'anonymous_code' => sprintf('X%02d', $index + 1),
                    'room_name' => $index < 5 ? 'Salle 1' : 'Salle 2',
                    'status' => 'active',
                    'jury_decision' => null,
                    'jury_observation' => null,
                ],
            );
        })->values();

        $classSubjects->each(function (ClassSubject $classSubject, int $subjectIndex) use ($mockExam, $candidates) {
            $subject = $classSubject->subject;
            $isSport = $subject->code === 'EPS';
            $mockExamSubject = $this->upsertMockExamSubject($mockExam, $classSubject, $subjectIndex, $isSport);

            $candidates->each(function (MockExamCandidate $candidate, int $candidateIndex) use ($mockExamSubject, $subjectIndex, $isSport) {
                MockExamScore::query()->updateOrCreate(
                    [
                        'mock_exam_subject_id' => $mockExamSubject->id,
                        'mock_exam_candidate_id' => $candidate->id,
                    ],
                    [
                        'score' => $this->scoreFor($candidateIndex, $subjectIndex, 7, true),
                        'is_absent' => false,
                        'observation' => $isSport && $candidateIndex === 7 ? 'Dispense medicale levee' : null,
                    ],
                );
            });
        });
    }

    private function upsertMockExamSubject(MockExam $mockExam, ClassSubject $classSubject, int $subjectIndex, bool $isSport): MockExamSubject
    {
        $values = [
            'max_score' => 20,
            'coefficient' => $classSubject->coefficient,
            'position' => $subjectIndex + 1,
        ];

        $optional = [
            'exam_date' => Carbon::parse('2027-03-02')->addDays($subjectIndex)->toDateString(),
            'starts_at' => $subjectIndex % 2 === 0 ? '07:30' : '10:00',
            'ends_at' => $subjectIndex % 2 === 0 ? '09:30' : '12:00',
            'supervisor_one' => $subjectIndex % 2 === 0 ? 'DAH Sam Sie' : 'KEREGUE Sompeguea',
            'supervisor_two' => $subjectIndex % 2 === 0 ? 'SONG-NABA Belko Leon' : 'KIEMA Zakaria',
            'expected_copies' => 10,
            'received_copies' => 10,
            'absent_count' => 0,
            'incident_notes' => 'RAS',
            'copies_received_at' => Carbon::parse('2027-03-07 12:00')->addHours($subjectIndex),
            'copy_receiver_name' => 'Vie scolaire',
            'correction_teacher_name' => 'Professeur '.$classSubject->subject->name,
            'fee_rate' => 2500,
            'fee_amount' => 25000,
            'fee_status' => 'pending',
        ];

        foreach ($optional as $column => $value) {
            if (Schema::hasColumn('mock_exam_subjects', $column)) {
                $values[$column] = $value;
            }
        }

        return MockExamSubject::query()->updateOrCreate(
            [
                'mock_exam_id' => $mockExam->id,
                'subject_id' => $classSubject->subject_id,
                'exam_part' => $isSport ? 'sport' : 'written',
            ],
            $values,
        );
    }

    private function firstTerm(AcademicYear $academicYear): Term
    {
        return Term::query()->firstOrCreate(
            ['academic_year_id' => $academicYear->id, 'position' => 1],
            [
                'name' => 'Trimestre 1',
                'type' => 'trimester',
                'starts_at' => '2026-10-01',
                'ends_at' => '2026-12-20',
                'is_closed' => false,
            ],
        );
    }

    private function termPeriods(Term $term): Collection
    {
        $items = [
            ['name' => '1er devoir', 'position' => 1, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31'],
            ['name' => '2e devoir', 'position' => 2, 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-30'],
            ['name' => '3e devoir', 'position' => 3, 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-20'],
        ];

        return collect($items)
            ->map(fn (array $item) => TermPeriod::query()->firstOrCreate(
                ['term_id' => $term->id, 'position' => $item['position']],
                $item + ['status' => 'active'],
            ))
            ->values();
    }

    private function scoreFor(int $studentIndex, int $subjectIndex, int $assessmentIndex, bool $exam = false): float
    {
        $base = $exam ? 8.75 : 9.25;
        $score = $base
            + (($studentIndex * 1.1) % 5.4)
            + (($subjectIndex * 0.65) % 3.2)
            + (($assessmentIndex * 0.45) % 2.4);

        return round(min(18.75, max(6.5, $score)), 2);
    }

    private function adminUser(): ?User
    {
        return User::query()
            ->where('email', 'infoslyceepagnidibsom@gmail.com')
            ->orWhere('username', 'admin')
            ->first();
    }

    private function studentProfiles(): array
    {
        return [
            [
                'first_name' => 'Aicha',
                'last_name' => 'Bado',
                'gender' => 'female',
                'birth_date' => '2011-04-12',
                'birth_place' => 'Ouagadougou',
                'origin_school' => 'Lycee Prive Pagnidibsom',
                'repeated_class' => 'Aucune',
                'address' => 'Secteur 28, Ouagadougou',
                'ethnicity' => 'Mossi',
                'religion' => 'Musulmane',
                'sector' => '28',
                'district' => 'Pagnidibsom',
                'home_phone' => '70010001',
                'health_notes' => 'RAS',
                'health_conditions' => [],
                'sport_aptitude' => true,
                'emergency_name' => 'Issa Bado',
                'emergency_phone' => '71010001',
                'whatsapp' => '76010001',
                'guardian_first_name' => 'Issa',
                'guardian_last_name' => 'Bado',
                'guardian_phone' => '71010001',
                'guardian_secondary_phone' => '72010001',
                'guardian_email' => 'issa.bado@example.com',
                'guardian_profession' => 'Commercant',
                'guardian_service' => 'Marche de Pagnidibsom',
                'guardian_relationship' => 'father',
            ],
            [
                'first_name' => 'Moussa',
                'last_name' => 'Kabre',
                'gender' => 'male',
                'birth_date' => '2010-09-25',
                'birth_place' => 'Koudougou',
                'origin_school' => 'Lycee Prive Pagnidibsom',
                'repeated_class' => 'Aucune',
                'address' => 'Karpala, Ouagadougou',
                'ethnicity' => 'Mossi',
                'religion' => 'Chretienne',
                'sector' => '51',
                'district' => 'Karpala',
                'home_phone' => '70010002',
                'health_notes' => 'Asthme leger signale',
                'health_conditions' => ['asthme'],
                'sport_aptitude' => true,
                'emergency_name' => 'Adama Kabre',
                'emergency_phone' => '71010002',
                'whatsapp' => '76010002',
                'guardian_first_name' => 'Adama',
                'guardian_last_name' => 'Kabre',
                'guardian_phone' => '71010002',
                'guardian_secondary_phone' => null,
                'guardian_email' => 'adama.kabre@example.com',
                'guardian_profession' => 'Menuisier',
                'guardian_service' => 'Atelier familial',
                'guardian_relationship' => 'father',
            ],
            [
                'first_name' => 'Fatoumata',
                'last_name' => 'Ouedraogo',
                'gender' => 'female',
                'birth_date' => '2011-01-18',
                'birth_place' => 'Ouahigouya',
                'origin_school' => 'Lycee Prive Pagnidibsom',
                'repeated_class' => 'Aucune',
                'address' => 'Tampouy, Ouagadougou',
                'ethnicity' => 'Mossi',
                'religion' => 'Musulmane',
                'sector' => '22',
                'district' => 'Tampouy',
                'home_phone' => '70010003',
                'health_notes' => 'RAS',
                'health_conditions' => [],
                'sport_aptitude' => true,
                'emergency_name' => 'Aminata Ouedraogo',
                'emergency_phone' => '71010003',
                'whatsapp' => '76010003',
                'guardian_first_name' => 'Aminata',
                'guardian_last_name' => 'Ouedraogo',
                'guardian_phone' => '71010003',
                'guardian_secondary_phone' => '72010003',
                'guardian_email' => 'aminata.ouedraogo@example.com',
                'guardian_profession' => 'Enseignante',
                'guardian_service' => 'Ecole primaire',
                'guardian_relationship' => 'mother',
            ],
            [
                'first_name' => 'Ibrahim',
                'last_name' => 'Sawadogo',
                'gender' => 'male',
                'birth_date' => '2010-11-08',
                'birth_place' => 'Ouagadougou',
                'origin_school' => 'Lycee Prive Pagnidibsom',
                'repeated_class' => '4e',
                'address' => 'Zone 1, Ouagadougou',
                'ethnicity' => 'Mossi',
                'religion' => 'Musulmane',
                'sector' => '15',
                'district' => 'Zone 1',
                'home_phone' => '70010004',
                'health_notes' => 'Controle de vue conseille',
                'health_conditions' => [],
                'sport_aptitude' => true,
                'emergency_name' => 'Mariam Sawadogo',
                'emergency_phone' => '71010004',
                'whatsapp' => '76010004',
                'guardian_first_name' => 'Mariam',
                'guardian_last_name' => 'Sawadogo',
                'guardian_phone' => '71010004',
                'guardian_secondary_phone' => null,
                'guardian_email' => 'mariam.sawadogo@example.com',
                'guardian_profession' => 'Infirmiere',
                'guardian_service' => 'Centre medical',
                'guardian_relationship' => 'mother',
            ],
            [
                'first_name' => 'Estelle',
                'last_name' => 'Zongo',
                'gender' => 'female',
                'birth_date' => '2011-06-30',
                'birth_place' => 'Bobo-Dioulasso',
                'origin_school' => 'Lycee Prive Pagnidibsom',
                'repeated_class' => 'Aucune',
                'address' => 'Wemtenga, Ouagadougou',
                'ethnicity' => 'Gourounsi',
                'religion' => 'Chretienne',
                'sector' => '29',
                'district' => 'Wemtenga',
                'home_phone' => '70010005',
                'health_notes' => 'RAS',
                'health_conditions' => [],
                'sport_aptitude' => true,
                'emergency_name' => 'Joseph Zongo',
                'emergency_phone' => '71010005',
                'whatsapp' => '76010005',
                'guardian_first_name' => 'Joseph',
                'guardian_last_name' => 'Zongo',
                'guardian_phone' => '71010005',
                'guardian_secondary_phone' => '72010005',
                'guardian_email' => 'joseph.zongo@example.com',
                'guardian_profession' => 'Comptable',
                'guardian_service' => 'Entreprise privee',
                'guardian_relationship' => 'father',
            ],
            [
                'first_name' => 'Ousmane',
                'last_name' => 'Nana',
                'gender' => 'male',
                'birth_date' => '2010-12-31',
                'birth_place' => 'Ouagadougou',
                'origin_school' => 'Lycee Prive Pagnidibsom',
                'repeated_class' => 'Aucune',
                'address' => 'Gounghin, Ouagadougou',
                'ethnicity' => 'Mossi',
                'religion' => 'Musulmane',
                'sector' => '8',
                'district' => 'Gounghin',
                'home_phone' => '70010006',
                'health_notes' => 'RAS',
                'health_conditions' => [],
                'sport_aptitude' => true,
                'emergency_name' => 'Salif Nana',
                'emergency_phone' => '71010006',
                'whatsapp' => '76010006',
                'guardian_first_name' => 'Salif',
                'guardian_last_name' => 'Nana',
                'guardian_phone' => '71010006',
                'guardian_secondary_phone' => null,
                'guardian_email' => 'salif.nana@example.com',
                'guardian_profession' => 'Chauffeur',
                'guardian_service' => 'Transport',
                'guardian_relationship' => 'father',
            ],
            [
                'first_name' => 'Bintou',
                'last_name' => 'Compaore',
                'gender' => 'female',
                'birth_date' => '2011-03-14',
                'birth_place' => 'Kaya',
                'origin_school' => 'Lycee Prive Pagnidibsom',
                'repeated_class' => 'Aucune',
                'address' => 'Dassasgho, Ouagadougou',
                'ethnicity' => 'Bissa',
                'religion' => 'Chretienne',
                'sector' => '41',
                'district' => 'Dassasgho',
                'home_phone' => '70010007',
                'health_notes' => 'Allergie poussiere',
                'health_conditions' => ['allergie'],
                'sport_aptitude' => true,
                'emergency_name' => 'Paul Compaore',
                'emergency_phone' => '71010007',
                'whatsapp' => '76010007',
                'guardian_first_name' => 'Paul',
                'guardian_last_name' => 'Compaore',
                'guardian_phone' => '71010007',
                'guardian_secondary_phone' => '72010007',
                'guardian_email' => 'paul.compaore@example.com',
                'guardian_profession' => 'Technicien',
                'guardian_service' => 'Maintenance',
                'guardian_relationship' => 'father',
            ],
            [
                'first_name' => 'Serge',
                'last_name' => 'Kinda',
                'gender' => 'male',
                'birth_date' => '2010-05-07',
                'birth_place' => 'Ouagadougou',
                'origin_school' => 'Lycee Prive Pagnidibsom',
                'repeated_class' => 'Aucune',
                'address' => 'Pissy, Ouagadougou',
                'ethnicity' => 'Mossi',
                'religion' => 'Chretienne',
                'sector' => '17',
                'district' => 'Pissy',
                'home_phone' => '70010008',
                'health_notes' => 'Dispense EPS ponctuelle possible',
                'health_conditions' => [],
                'sport_aptitude' => true,
                'emergency_name' => 'Therese Kinda',
                'emergency_phone' => '71010008',
                'whatsapp' => '76010008',
                'guardian_first_name' => 'Therese',
                'guardian_last_name' => 'Kinda',
                'guardian_phone' => '71010008',
                'guardian_secondary_phone' => null,
                'guardian_email' => 'therese.kinda@example.com',
                'guardian_profession' => 'Restauratrice',
                'guardian_service' => 'Restaurant familial',
                'guardian_relationship' => 'mother',
            ],
            [
                'first_name' => 'Grace',
                'last_name' => 'Nikiema',
                'gender' => 'female',
                'birth_date' => '2011-08-21',
                'birth_place' => 'Ziniare',
                'origin_school' => 'Lycee Prive Pagnidibsom',
                'repeated_class' => 'Aucune',
                'address' => 'Patte d Oie, Ouagadougou',
                'ethnicity' => 'Mossi',
                'religion' => 'Chretienne',
                'sector' => '52',
                'district' => 'Patte d Oie',
                'home_phone' => '70010009',
                'health_notes' => 'RAS',
                'health_conditions' => [],
                'sport_aptitude' => true,
                'emergency_name' => 'Etienne Nikiema',
                'emergency_phone' => '71010009',
                'whatsapp' => '76010009',
                'guardian_first_name' => 'Etienne',
                'guardian_last_name' => 'Nikiema',
                'guardian_phone' => '71010009',
                'guardian_secondary_phone' => '72010009',
                'guardian_email' => 'etienne.nikiema@example.com',
                'guardian_profession' => 'Agent commercial',
                'guardian_service' => 'Societe privee',
                'guardian_relationship' => 'father',
            ],
            [
                'first_name' => 'Abdoul',
                'last_name' => 'Traore',
                'gender' => 'male',
                'birth_date' => '2010-02-03',
                'birth_place' => 'Banfora',
                'origin_school' => 'Lycee Prive Pagnidibsom',
                'repeated_class' => 'Aucune',
                'address' => 'Koulouba, Ouagadougou',
                'ethnicity' => 'Dioula',
                'religion' => 'Musulmane',
                'sector' => '4',
                'district' => 'Koulouba',
                'home_phone' => '70010010',
                'health_notes' => 'RAS',
                'health_conditions' => [],
                'sport_aptitude' => true,
                'emergency_name' => 'Mamadou Traore',
                'emergency_phone' => '71010010',
                'whatsapp' => '76010010',
                'guardian_first_name' => 'Mamadou',
                'guardian_last_name' => 'Traore',
                'guardian_phone' => '71010010',
                'guardian_secondary_phone' => null,
                'guardian_email' => 'mamadou.traore@example.com',
                'guardian_profession' => 'Entrepreneur',
                'guardian_service' => 'BTP',
                'guardian_relationship' => 'father',
            ],
        ];
    }
}
