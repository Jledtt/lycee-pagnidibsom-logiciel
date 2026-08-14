<?php

namespace App\Services;

use App\Models\AcademicTrack;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

class PagnidibsomClassSubjectSetupService
{
    public function apply(?AcademicYear $academicYear = null): array
    {
        $academicYear ??= AcademicYear::query()->where('is_active', true)->firstOrFail();

        return DB::transaction(function () use ($academicYear) {
            $createdOrUpdated = [];

            foreach ($this->classPlan() as $className => $plan) {
                $level = Level::query()->firstOrCreate(
                    ['name' => $plan['level']],
                    [
                        'cycle' => $plan['cycle'],
                        'position' => $plan['position'],
                    ],
                );

                $classAttributes = [
                    'level_id' => $level->id,
                    'code' => $plan['code'],
                    'capacity' => $plan['capacity'],
                    'status' => 'active',
                ];

                if (isset($plan['track'])) {
                    $classAttributes['academic_track_id'] = $this->academicTrack($plan['track'])->id;
                }

                $schoolClass = SchoolClass::query()->updateOrCreate(
                    [
                        'academic_year_id' => $academicYear->id,
                        'name' => $className,
                    ],
                    $classAttributes,
                );

                foreach ($plan['subjects'] as $subjectCode => $coefficient) {
                    $subject = $this->subject($subjectCode);

                    ClassSubject::query()->updateOrCreate(
                        [
                            'school_class_id' => $schoolClass->id,
                            'subject_id' => $subject->id,
                        ],
                        [
                            'coefficient' => $coefficient,
                            'is_active' => true,
                        ],
                    );
                }

                $createdOrUpdated[] = [
                    'class' => $schoolClass->name,
                    'subjects' => count($plan['subjects']),
                ];
            }

            return [
                'academic_year' => $academicYear->name,
                'classes' => $createdOrUpdated,
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
                    'TIC' => 2,
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
                    'TIC' => 2,
                ],
            ],
            '4e' => [
                'level' => '4e',
                'cycle' => 'Premier cycle',
                'position' => 3,
                'code' => '4E',
                'capacity' => 60,
                'subjects' => [
                    'ALL' => 2,
                    'ANG' => 2,
                    'ECM' => 2,
                    'EPS' => 2,
                    'FR' => 3,
                    'HG' => 2,
                    'MATH' => 3,
                    'PC' => 2,
                    'SVT' => 2,
                    'TIC' => 2,
                ],
            ],
            '3e' => [
                'level' => '3e',
                'cycle' => 'Premier cycle',
                'position' => 4,
                'code' => '3E',
                'capacity' => 60,
                'subjects' => [
                    'ALL' => 2,
                    'ANG' => 2,
                    'ECM' => 2,
                    'EPS' => 2,
                    'FR' => 3,
                    'HG' => 2,
                    'MATH' => 3,
                    'PC' => 2,
                    'SVT' => 2,
                    'TIC' => 2,
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
                    'PC' => 2,
                    'PHILO' => 2,
                    'SVT' => 2,
                    'TIC' => 2,
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
                    'TIC' => 2,
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
