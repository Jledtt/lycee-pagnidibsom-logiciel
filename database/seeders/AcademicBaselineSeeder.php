<?php

namespace Database\Seeders;

use App\Models\AcademicTrack;
use App\Models\AcademicYear;
use App\Models\AssessmentType;
use App\Models\FeeType;
use App\Models\Level;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\Term;
use App\Services\TermPeriodService;
use Illuminate\Database\Seeder;

class AcademicBaselineSeeder extends Seeder
{
    public function run(): void
    {
        SchoolSetting::firstOrCreate(
            ['school_name' => 'Lycée Privé Pagnidibsom'],
            [
                'short_name' => 'LPP',
                'currency' => 'FCFA',
                'address' => '04 Ouagadougou 04 BP 8825',
                'phone' => '(+226) 72 81 61 59 / 78 42 62 06',
                'email' => 'infoslyceepagnidibsom@gmail.com',
                'logo_path' => 'images/logo-pagnidibsom.png',
                'motto' => '"Bâtir l\'excellence"',
                'country' => 'Burkina Faso',
                'national_motto' => 'La Patrie ou la Mort Nous Vaincrons',
                'city' => 'Ouagadougou',
                'postal_box' => '04 BP 8825',
                'principal_name' => 'Yamdaogo TINTILA',
                'principal_title' => 'Le Proviseur',
                'accountant_name' => 'Le Comptable',
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
            $createdTerm = Term::query()->firstOrCreate(
                [
                    'academic_year_id' => $academicYear->id,
                    'position' => $term['position'],
                ],
                [
                    'name' => $term['name'],
                    'type' => 'trimestre',
                ]
            );

            app(TermPeriodService::class)->ensureDefaults($createdTerm);
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
            ['name' => 'Série A', 'code' => 'A', 'kind' => 'serie'],
            ['name' => 'Série C', 'code' => 'C', 'kind' => 'serie'],
        ] as $track) {
            AcademicTrack::query()->updateOrCreate(
                ['code' => $track['code']],
                $track + ['status' => 'active'],
            );
        }

        foreach ([
            ['name' => 'Français', 'code' => 'FR'],
            ['name' => 'Mathématiques', 'code' => 'MATH'],
            ['name' => 'Mathématiques appliquées', 'code' => 'MATH_APP'],
            ['name' => 'Anglais', 'code' => 'ANG'],
            ['name' => 'Histoire-Géographie', 'code' => 'HG'],
            ['name' => 'SVT', 'code' => 'SVT'],
            ['name' => 'Physique-Chimie', 'code' => 'PC'],
            ['name' => 'Sciences physiques', 'code' => 'SP'],
            ['name' => 'Philosophie', 'code' => 'PHILO'],
            ['name' => 'EPS', 'code' => 'EPS'],
            ['name' => 'Éducation civique et morale', 'code' => 'ECM'],
            ['name' => 'Technologie', 'code' => 'TECH'],
            ['name' => 'Allemand', 'code' => 'ALL'],
            ['name' => 'Espagnol', 'code' => 'ESP'],
            ['name' => 'Arabe', 'code' => 'ARB'],
            ['name' => 'TIC', 'code' => 'TIC'],
            ['name' => 'Art et culture', 'code' => 'ART'],
            ['name' => 'Musique et chant', 'code' => 'MUS'],
            ['name' => 'Théâtre', 'code' => 'THEATRE'],
            ['name' => 'Art ménager', 'code' => 'ART_MEN'],
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
            ['name' => 'Réinscription', 'code' => 'REINS'],
            ['name' => 'Scolarité', 'code' => 'SCO'],
            ['name' => 'Frais d\'examen', 'code' => 'EXAM'],
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
            ['name' => 'Interrogation', 'weight' => 0, 'status' => 'inactive'],
            ['name' => 'Devoir', 'weight' => 40, 'status' => 'active'],
            ['name' => 'Composition', 'weight' => 60, 'status' => 'active'],
        ] as $type) {
            AssessmentType::query()->updateOrCreate(
                ['name' => $type['name']],
                [
                    'weight' => $type['weight'],
                    'status' => $type['status'],
                ],
            );
        }
    }
}
