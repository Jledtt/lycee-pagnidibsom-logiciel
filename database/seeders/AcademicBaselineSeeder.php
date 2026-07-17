<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\AssessmentType;
use App\Models\FeeType;
use App\Models\Level;
use App\Models\SchoolSetting;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class AcademicBaselineSeeder extends Seeder
{
    public function run(): void
    {
        SchoolSetting::firstOrCreate(
            ['school_name' => 'Lycee Prive Pagnidibsom'],
            [
                'currency' => 'FCFA',
                'address' => 'Burkina Faso',
            ]
        );

        $academicYear = AcademicYear::firstOrCreate(
            ['name' => '2026-2027'],
            [
                'starts_at' => '2026-10-01',
                'ends_at' => '2027-07-31',
                'is_active' => true,
                'status' => 'active',
            ]
        );

        foreach ([
            ['name' => 'Trimestre 1', 'position' => 1],
            ['name' => 'Trimestre 2', 'position' => 2],
            ['name' => 'Trimestre 3', 'position' => 3],
        ] as $term) {
            $academicYear->terms()->firstOrCreate(
                ['position' => $term['position']],
                [
                    'name' => $term['name'],
                    'type' => 'trimestre',
                ]
            );
        }

        foreach ([
            ['name' => '6e', 'cycle' => 'Premier cycle', 'position' => 1],
            ['name' => '5e', 'cycle' => 'Premier cycle', 'position' => 2],
            ['name' => '4e', 'cycle' => 'Premier cycle', 'position' => 3],
            ['name' => '3e', 'cycle' => 'Premier cycle', 'position' => 4],
            ['name' => '2nde', 'cycle' => 'Second cycle', 'position' => 5],
            ['name' => '1re', 'cycle' => 'Second cycle', 'position' => 6],
            ['name' => 'Terminale', 'cycle' => 'Second cycle', 'position' => 7],
        ] as $level) {
            Level::firstOrCreate(['name' => $level['name']], $level);
        }

        foreach ([
            ['name' => 'Francais', 'code' => 'FR'],
            ['name' => 'Mathematiques', 'code' => 'MATH'],
            ['name' => 'Anglais', 'code' => 'ANG'],
            ['name' => 'Histoire-Geographie', 'code' => 'HG'],
            ['name' => 'SVT', 'code' => 'SVT'],
            ['name' => 'Physique-Chimie', 'code' => 'PC'],
            ['name' => 'Philosophie', 'code' => 'PHILO'],
            ['name' => 'EPS', 'code' => 'EPS'],
        ] as $subject) {
            Subject::firstOrCreate(['name' => $subject['name']], $subject);
        }

        foreach ([
            ['name' => 'Inscription', 'code' => 'INS'],
            ['name' => 'Reinscription', 'code' => 'REINS'],
            ['name' => 'Scolarite', 'code' => 'SCO'],
            ['name' => 'Frais d examen', 'code' => 'EXAM'],
            ['name' => 'Autres frais', 'code' => 'AUTRE'],
        ] as $feeType) {
            FeeType::firstOrCreate(['name' => $feeType['name']], $feeType);
        }

        foreach ([
            ['name' => 'Interrogation', 'weight' => 1],
            ['name' => 'Devoir', 'weight' => 1],
            ['name' => 'Composition', 'weight' => 2],
        ] as $type) {
            AssessmentType::firstOrCreate(['name' => $type['name']], $type);
        }
    }
}
