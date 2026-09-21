<?php

namespace App\Services;

use App\Models\AcademicTrack;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PagnidibsomClassSubjectSetupService
{
    public function apply(?AcademicYear $academicYear = null): array
    {
        $academicYear ??= AcademicYear::query()->where('is_active', true)->firstOrFail();

        return DB::transaction(function () use ($academicYear) {
            $createdOrUpdated = [];
            $teachers = $this->teachersByName();
            $unresolvedTeachers = [];

            foreach ($this->classPlan() as $className => $plan) {
                $level = Level::query()->firstOrCreate(
                    ['name' => $plan['level']],
                    [
                        'cycle' => $plan['cycle'],
                        'position' => $plan['position'],
                    ],
                );

                $schoolClass = SchoolClass::query()->firstOrNew([
                    'academic_year_id' => $academicYear->id,
                    'name' => $className,
                ]);
                $schoolClass->level_id = $level->id;
                $schoolClass->academic_track_id = isset($plan['track'])
                    ? $this->academicTrack($plan['track'])->id
                    : null;
                $schoolClass->status = 'active';
                $schoolClass->code = filled($schoolClass->code) ? $schoolClass->code : $plan['code'];
                $schoolClass->capacity ??= $plan['capacity'];
                $schoolClass->save();

                $officialSubjectIds = [];

                foreach ($plan['subjects'] as $subjectCode => $coefficient) {
                    $subject = $this->subject($subjectCode);
                    $officialSubjectIds[] = $subject->id;

                    $assignment = ClassSubject::query()->firstOrNew([
                        'school_class_id' => $schoolClass->id,
                        'subject_id' => $subject->id,
                    ]);

                    if (! $assignment->exists) {
                        $assignment->coefficient = $coefficient;
                    }

                    if ($subjectCode === 'PC') {
                        $this->copyLegacyPhysicalScienceAssignment($schoolClass, $assignment);
                    }

                    $teacherName = $this->teacherPlan()[$className][$subjectCode] ?? null;

                    if (! $assignment->teacher_id && $teacherName) {
                        $teacher = $teachers->get($teacherName);

                        if ($teacher) {
                            $assignment->teacher_id = $teacher->id;
                        } else {
                            $unresolvedTeachers[] = [
                                'class' => $className,
                                'subject' => $subject->name,
                                'teacher' => $teacherName,
                            ];
                        }
                    }

                    $assignment->is_active = true;
                    $assignment->save();
                }

                $deactivated = ClassSubject::query()
                    ->where('school_class_id', $schoolClass->id)
                    ->whereNotIn('subject_id', $officialSubjectIds)
                    ->where(fn ($query) => $query
                        ->where('is_active', true)
                        ->orWhereNotNull('teacher_id'))
                    ->update([
                        'teacher_id' => null,
                        'is_active' => false,
                        'updated_at' => now(),
                    ]);

                $createdOrUpdated[] = [
                    'class' => $schoolClass->name,
                    'subjects' => count($plan['subjects']),
                    'teachers' => ClassSubject::query()
                        ->where('school_class_id', $schoolClass->id)
                        ->whereIn('subject_id', $officialSubjectIds)
                        ->where('is_active', true)
                        ->whereNotNull('teacher_id')
                        ->count(),
                    'deactivated' => $deactivated,
                ];
            }

            return [
                'academic_year' => $academicYear->name,
                'classes' => $createdOrUpdated,
                'unresolved_teachers' => collect($unresolvedTeachers)->unique()->values()->all(),
            ];
        });
    }

    public function classPlan(): array
    {
        return [
            '6e' => [
                'level' => '6e',
                'cycle' => 'Premier cycle',
                'position' => 1,
                'code' => '6E',
                'capacity' => 60,
                'subjects' => [
                    'ANG' => 2,
                    'ECM' => 2,
                    'EPS' => 2,
                    'FR' => 3,
                    'HG' => 2,
                    'MATH' => 3,
                    'SVT' => 2,
                ],
            ],
            '5e' => [
                'level' => '5e',
                'cycle' => 'Premier cycle',
                'position' => 2,
                'code' => '5E',
                'capacity' => 60,
                'subjects' => [
                    'ANG' => 2,
                    'ECM' => 2,
                    'EPS' => 2,
                    'FR' => 3,
                    'HG' => 2,
                    'MATH' => 3,
                    'SVT' => 2,
                ],
            ],
            '4e' => [
                'level' => '4e',
                'cycle' => 'Premier cycle',
                'position' => 3,
                'code' => '4E',
                'capacity' => 60,
                'subjects' => [
                    'ANG' => 2,
                    'ECM' => 2,
                    'EPS' => 2,
                    'FR' => 3,
                    'HG' => 2,
                    'MATH' => 3,
                    'PC' => 2,
                    'SVT' => 2,
                ],
            ],
            '3e' => [
                'level' => '3e',
                'cycle' => 'Premier cycle',
                'position' => 4,
                'code' => '3E',
                'capacity' => 60,
                'subjects' => [
                    'ANG' => 2,
                    'ECM' => 2,
                    'EPS' => 2,
                    'FR' => 3,
                    'HG' => 2,
                    'MATH' => 3,
                    'PC' => 2,
                    'SVT' => 2,
                ],
            ],
            '2nde A' => [
                'level' => '2nde',
                'cycle' => 'Second cycle',
                'position' => 5,
                'code' => '2NDA',
                'track' => 'A',
                'capacity' => 60,
                'subjects' => [
                    'ALL' => 3,
                    'ANG' => 4,
                    'ECM' => 2,
                    'EPS' => 2,
                    'FR' => 5,
                    'HG' => 3,
                    'MATH' => 3,
                    'PHILO' => 2,
                ],
            ],
            '2nde C' => [
                'level' => '2nde',
                'cycle' => 'Second cycle',
                'position' => 5,
                'code' => '2NDC',
                'track' => 'C',
                'capacity' => 60,
                'subjects' => [
                    'ANG' => 2,
                    'ECM' => 2,
                    'EPS' => 2,
                    'FR' => 3,
                    'HG' => 2,
                    'MATH' => 6,
                    'PC' => 6,
                    'PHILO' => 2,
                    'SVT' => 3,
                ],
            ],
            '1ère A' => [
                'level' => '1re',
                'cycle' => 'Second cycle',
                'position' => 6,
                'code' => '1REA',
                'track' => 'A',
                'capacity' => 60,
                'subjects' => [
                    'ALL' => 3,
                    'ANG' => 4,
                    'ECM' => 2,
                    'EPS' => 2,
                    'FR' => 5,
                    'HG' => 3,
                    'MATH' => 3,
                    'PHILO' => 3,
                ],
            ],
            '1ère D' => [
                'level' => '1re',
                'cycle' => 'Second cycle',
                'position' => 6,
                'code' => '1RED',
                'track' => 'D',
                'capacity' => 60,
                'subjects' => [
                    'ANG' => 2,
                    'ECM' => 2,
                    'EPS' => 2,
                    'FR' => 3,
                    'HG' => 2,
                    'MATH' => 5,
                    'PC' => 5,
                    'SVT' => 4,
                ],
            ],
        ];
    }

    public function suggestedSubjectsForClass(SchoolClass $schoolClass): array
    {
        $schoolClass->loadMissing(['level', 'academicTrack']);
        $classKey = $schoolClass->name;

        if (! isset($this->classPlan()[$classKey]) && $schoolClass->level && $schoolClass->academicTrack) {
            $classKey = trim($schoolClass->level->name.' '.$schoolClass->academicTrack->code);
        }

        $plan = $this->classPlan()[$classKey] ?? null;

        if (! $plan) {
            return [];
        }

        return collect($plan['subjects'])
            ->map(function (int|float $coefficient, string $code): array {
                $subject = $this->subjects()[$code];

                return [
                    'name' => $subject['name'],
                    'code' => $code,
                    'coefficient' => $coefficient,
                ];
            })
            ->values()
            ->all();
    }

    private function subject(string $code): Subject
    {
        $data = $this->subjects()[$code];

        $subject = Subject::query()
            ->where('code', $code)
            ->orWhere('name', $data['name'])
            ->first();

        if (! $subject) {
            $subject = Subject::query()->create([
                'name' => $data['name'],
                'code' => $code,
                'status' => 'active',
            ]);
        }

        if ($subject->name !== $data['name'] || $subject->code !== $code || $subject->status !== 'active') {
            $subject->forceFill([
                'name' => $data['name'],
                'code' => $code,
                'status' => 'active',
            ])->save();
        }

        return $subject;
    }

    private function academicTrack(string $code): AcademicTrack
    {
        return AcademicTrack::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => 'Série '.$code,
                'kind' => 'serie',
                'status' => 'active',
            ],
        );
    }

    private function copyLegacyPhysicalScienceAssignment(
        SchoolClass $schoolClass,
        ClassSubject $assignment,
    ): void {
        $legacy = ClassSubject::query()
            ->where('school_class_id', $schoolClass->id)
            ->whereHas('subject', fn ($query) => $query->where('code', 'SP'))
            ->first();

        if (! $legacy) {
            return;
        }

        if (! $assignment->exists) {
            $assignment->coefficient = $legacy->coefficient;
        }

        $assignment->weekly_hours ??= $legacy->weekly_hours;
        $assignment->teacher_id ??= $legacy->teacher_id;
    }

    private function teachersByName()
    {
        $names = collect($this->teacherPlan())
            ->flatMap(fn (array $subjects) => array_values($subjects))
            ->unique()
            ->values();

        return User::query()
            ->role('enseignant')
            ->where('status', 'active')
            ->whereIn('name', $names)
            ->get()
            ->keyBy('name');
    }

    private function teacherPlan(): array
    {
        return [
            '6e' => [
                'ANG' => 'ZONGO Mariam',
                'ECM' => 'GNEBGA Bissore Jérôme',
                'EPS' => 'OUEDRAOGO G. P. Hilaire',
                'FR' => 'ZONGO Florence',
                'HG' => 'KEREGUE Sompéguea',
                'MATH' => 'BADO Constant',
                'SVT' => 'OUEDRAOGO Vincent',
            ],
            '5e' => [
                'ANG' => 'KIEMA Philomène',
                'ECM' => 'GNEBGA Bissore Jérôme',
                'EPS' => 'MEDA D. Mathurin',
                'FR' => 'ZONGO Florence',
                'HG' => 'KEREGUE Sompéguea',
                'MATH' => 'BADO Constant',
                'SVT' => 'KABORE/KABRE Aïchatou',
            ],
            '4e' => [
                'ANG' => 'DEMBELE/SAWADOGO Bibata',
                'ECM' => 'GNEBGA Bissore Jérôme',
                'EPS' => 'NIKIEMA Zakaria',
                'FR' => 'BADIEL N. Philippe',
                'HG' => 'KOMBASSERE Salifou',
                'MATH' => 'DIANDA Halidou',
                'PC' => "M'BAMA N. L. Degrâce",
                'SVT' => 'KABORE/KABRE Aïchatou',
            ],
            '3e' => [
                'ANG' => 'KIEMA Philomène',
                'ECM' => 'GNEBGA Bissore Jérôme',
                'EPS' => 'OUEDRAOGO G. P. Hilaire',
                'FR' => 'SONG-NABA Belko Léon',
                'HG' => 'KOMBASSERE Salifou',
                'MATH' => 'KAMANA Payaki',
                'PC' => 'NANA Drissa',
                'SVT' => 'OUEDRAOGO Vincent',
            ],
            '2nde A' => [
                'ALL' => 'SAWADOGO Iliasse',
                'ANG' => 'TINTILA Yamdaogo',
                'ECM' => 'GNEBGA Bissore Jérôme',
                'EPS' => 'MEDA D. Mathurin',
                'FR' => 'SONG-NABA Belko Léon',
                'HG' => 'KEREGUE Sompéguea',
                'MATH' => 'KAMANA Payaki',
                'PHILO' => 'MORE Tolfanrson',
            ],
            '2nde C' => [
                'ANG' => 'DEMBELE/SAWADOGO Bibata',
                'ECM' => 'GNEBGA Bissore Jérôme',
                'EPS' => 'MEDA D. Mathurin',
                'FR' => 'SONG-NABA Belko Léon',
                'HG' => 'KEREGUE Sompéguea',
                'MATH' => 'DIANDA Halidou',
                'PC' => "M'BAMA N. L. Degrâce",
                'PHILO' => 'MORE Tolfanrson',
                'SVT' => 'BAZIE Pierre',
            ],
            '1ère A' => [
                'ALL' => 'SAWADOGO Iliasse',
                'ANG' => 'TINTILA Yamdaogo',
                'ECM' => 'GNEBGA Bissore Jérôme',
                'EPS' => 'NIKIEMA Zakaria',
                'FR' => 'SONG-NABA Belko Léon',
                'HG' => 'KOMBASSERE Salifou',
                'MATH' => 'KAMANA Payaki',
                'PHILO' => 'MORE Tolfanrson',
            ],
            '1ère D' => [
                'ANG' => 'DEMBELE/SAWADOGO Bibata',
                'ECM' => 'GNEBGA Bissore Jérôme',
                'EPS' => 'NIKIEMA Zakaria',
                'FR' => 'BADIEL N. Philippe',
                'HG' => 'KOMBASSERE Salifou',
                'MATH' => 'DIANDA Halidou',
                'PC' => 'NANA Drissa',
                'SVT' => 'BAZIE Pierre',
            ],
        ];
    }

    private function subjects(): array
    {
        return [
            'FR' => ['name' => 'Français'],
            'MATH' => ['name' => 'Mathématiques'],
            'ANG' => ['name' => 'Anglais'],
            'SVT' => ['name' => 'SVT'],
            'HG' => ['name' => 'Histoire-Géographie'],
            'EPS' => ['name' => 'EPS'],
            'ECM' => ['name' => 'Éducation civique et morale'],
            'PC' => ['name' => 'Physique-Chimie'],
            'ALL' => ['name' => 'Allemand'],
            'PHILO' => ['name' => 'Philosophie'],
            'TIC' => ['name' => 'Technologies de l’information et de la communication'],
        ];
    }
}
