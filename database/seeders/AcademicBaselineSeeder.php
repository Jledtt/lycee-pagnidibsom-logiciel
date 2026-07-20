<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\FeeType;
use App\Models\Level;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\TermPeriod;
use Illuminate\Database\Seeder;

class AcademicBaselineSeeder extends Seeder
{
    public function run(): void
    {
        SchoolSetting::firstOrCreate(
            ['school_name' => 'Lycee Prive Pagnidibsom'],
            [
                'short_name' => 'LPP',
                'currency' => 'FCFA',
                'address' => '04 Ouagadougou 04 BP 8825',
                'phone' => '(+226) 72 81 61 59 / 78 42 62 06',
                'email' => 'infoslyceepagnidibsom@gmail.com',
                'logo_path' => 'images/logo-pagnidibsom.png',
                'motto' => '"Batir l\'excellence"',
                'country' => 'Burkina Faso',
                'national_motto' => 'La Patrie ou la Mort Nous Vaincrons',
                'city' => 'Ouagadougou',
                'postal_box' => '04 BP 8825',
                'principal_name' => 'Yamdaogo TINTILA',
                'principal_title' => 'Le Proviseur',
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
            $createdTerm = $academicYear->terms()->firstOrCreate(
                ['position' => $term['position']],
                [
                    'name' => $term['name'],
                    'type' => 'trimestre',
                ]
            );

            foreach ([
                ['position' => 1, 'name' => '1er devoir'],
                ['position' => 2, 'name' => '2e devoir'],
                ['position' => 3, 'name' => '3e devoir'],
            ] as $period) {
                $createdPeriod = TermPeriod::firstOrCreate(
                    [
                        'term_id' => $createdTerm->id,
                        'position' => $period['position'],
                    ],
                    [
                        'name' => $period['name'],
                        'status' => 'active',
                    ],
                );

                if ((int) $period['position'] === 1) {
                    Assessment::query()
                        ->where('term_id', $createdTerm->id)
                        ->whereNull('term_period_id')
                        ->update(['term_period_id' => $createdPeriod->id]);
                }
            }
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
            ['name' => 'Mathematiques appliquees', 'code' => 'MATH_APP'],
            ['name' => 'Anglais', 'code' => 'ANG'],
            ['name' => 'Histoire-Geographie', 'code' => 'HG'],
            ['name' => 'SVT', 'code' => 'SVT'],
            ['name' => 'Physique-Chimie', 'code' => 'PC'],
            ['name' => 'Sciences physiques', 'code' => 'SP'],
            ['name' => 'Philosophie', 'code' => 'PHILO'],
            ['name' => 'EPS', 'code' => 'EPS'],
            ['name' => 'Education civique et morale', 'code' => 'ECM'],
            ['name' => 'Technologie', 'code' => 'TECH'],
            ['name' => 'Allemand', 'code' => 'ALL'],
            ['name' => 'Espagnol', 'code' => 'ESP'],
            ['name' => 'Arabe', 'code' => 'ARB'],
            ['name' => 'TIC', 'code' => 'TIC'],
            ['name' => 'Art et culture', 'code' => 'ART'],
            ['name' => 'Musique et chant', 'code' => 'MUS'],
            ['name' => 'Theatre', 'code' => 'THEATRE'],
            ['name' => 'Art menager', 'code' => 'ART_MEN'],
            ['name' => 'Production', 'code' => 'PROD'],
            ['name' => 'Dessin technique', 'code' => 'DESS_TECH'],
        ] as $subject) {
            $model = Subject::query()
                ->where('code', $subject['code'])
                ->orWhere('name', $subject['name'])
                ->first();

            if ($model) {
                $model->forceFill([
                    'name' => $subject['name'],
                    'code' => $subject['code'],
                    'status' => 'active',
                ])->save();
            } else {
                Subject::query()->create([
                    'name' => $subject['name'],
                    'code' => $subject['code'],
                    'status' => 'active',
                ]);
            }
        }

        foreach ([
            ['name' => 'Inscription', 'code' => 'INS'],
            ['name' => 'Reinscription', 'code' => 'REINS'],
            ['name' => 'Scolarite', 'code' => 'SCO'],
            ['name' => 'Frais d examen', 'code' => 'EXAM'],
            ['name' => 'Autres frais', 'code' => 'AUTRE'],
        ] as $feeType) {
            $model = FeeType::query()
                ->where('code', $feeType['code'])
                ->orWhere('name', $feeType['name'])
                ->first();

            if ($model) {
                $model->forceFill([
                    'name' => $feeType['name'],
                    'code' => $feeType['code'],
                ])->save();
            } else {
                FeeType::query()->create($feeType);
            }
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
