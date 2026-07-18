<?php

namespace App\Services;

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

                $schoolClass = SchoolClass::query()->updateOrCreate(
                    [
                        'academic_year_id' => $academicYear->id,
                        'name' => $className,
                    ],
                    [
                        'level_id' => $level->id,
                        'code' => $plan['code'],
                        'capacity' => $plan['capacity'],
                        'status' => 'active',
                    ],
                );

                foreach ($plan['subjects'] as $subjectCode) {
                    $subject = $this->subject($subjectCode);

                    ClassSubject::query()->updateOrCreate(
                        [
                            'school_class_id' => $schoolClass->id,
                            'subject_id' => $subject->id,
                        ],
                        [
                            'coefficient' => 1,
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
                'subjects' => ['FR', 'MATH', 'ANG', 'SVT', 'HG', 'EPS', 'ECM'],
            ],
            '5e' => [
                'level' => '5e',
                'cycle' => 'Premier cycle',
                'position' => 2,
                'code' => '5E',
                'capacity' => 60,
                'subjects' => ['FR', 'MATH', 'ANG', 'SVT', 'HG', 'EPS', 'ECM'],
            ],
            '4e' => [
                'level' => '4e',
                'cycle' => 'Premier cycle',
                'position' => 3,
                'code' => '4E',
                'capacity' => 60,
                'subjects' => ['FR', 'MATH', 'PC', 'ANG', 'SVT', 'HG', 'EPS', 'ECM'],
            ],
            '3e' => [
                'level' => '3e',
                'cycle' => 'Premier cycle',
                'position' => 4,
                'code' => '3E',
                'capacity' => 60,
                'subjects' => ['FR', 'MATH', 'PC', 'ANG', 'SVT', 'HG', 'EPS', 'ECM'],
            ],
            '2nde A' => [
                'level' => '2nde',
                'cycle' => 'Second cycle',
                'position' => 5,
                'code' => '2NDA',
                'capacity' => 60,
                'subjects' => ['FR', 'MATH', 'ANG', 'ALL', 'PHILO', 'HG', 'EPS', 'ECM'],
            ],
            '2nde C' => [
                'level' => '2nde',
                'cycle' => 'Second cycle',
                'position' => 5,
                'code' => '2NDC',
                'capacity' => 60,
                'subjects' => ['FR', 'MATH', 'ANG', 'SVT', 'PC', 'PHILO', 'HG', 'EPS', 'ECM'],
            ],
        ];
    }

    private function subject(string $code): Subject
    {
        $data = $this->subjects()[$code];

        $subject = Subject::query()->firstOrCreate(
            ['name' => $data['name']],
            [
                'code' => $code,
                'status' => 'active',
            ],
        );

        if ($subject->code !== $code || $subject->status !== 'active') {
            $subject->forceFill([
                'code' => $subject->code ?: $code,
                'status' => 'active',
            ])->save();
        }

        return $subject;
    }

    private function subjects(): array
    {
        return [
            'FR' => ['name' => 'Francais'],
            'MATH' => ['name' => 'Mathematiques'],
            'ANG' => ['name' => 'Anglais'],
            'SVT' => ['name' => 'SVT'],
            'HG' => ['name' => 'Histoire-Geographie'],
            'EPS' => ['name' => 'EPS'],
            'ECM' => ['name' => 'Education civique et morale'],
            'PC' => ['name' => 'Physique-Chimie'],
            'ALL' => ['name' => 'Allemand'],
            'PHILO' => ['name' => 'Philosophie'],
        ];
    }
}
