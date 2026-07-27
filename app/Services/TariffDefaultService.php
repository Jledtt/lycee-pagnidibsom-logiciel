<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\FeeSchedule;
use App\Models\FeeType;
use App\Models\SchoolClass;
use Illuminate\Support\Str;

class TariffDefaultService
{
    public function applyToActiveAcademicYear(): array
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $feeTypes = $this->ensureDefaultFeeTypes();
        $classes = SchoolClass::query()
            ->with('level')
            ->where('academic_year_id', $academicYear->id)
            ->where('status', 'active')
            ->get();
        $lines = 0;

        foreach ($classes as $schoolClass) {
            $lines += $this->applyToClass($academicYear, $schoolClass, $feeTypes);
        }

        return [
            'academic_year' => $academicYear->name,
            'classes' => $classes->count(),
            'lines' => $lines,
        ];
    }

    public function applyToClass(AcademicYear $academicYear, SchoolClass $schoolClass, ?array $feeTypes = null): int
    {
        $feeTypes ??= $this->ensureDefaultFeeTypes();
        $lines = 0;

        foreach ($this->defaultLinesForClass($schoolClass->loadMissing('level'), $feeTypes) as $line) {
            FeeSchedule::updateOrCreate(
                [
                    'academic_year_id' => $academicYear->id,
                    'school_class_id' => $schoolClass->id,
                    'fee_type_id' => $line['fee_type_id'],
                    'period' => $line['period'],
                ],
                [
                    'amount' => $line['amount'],
                    'due_date' => $line['due_date'] ?? null,
                ],
            );

            $lines++;
        }

        return $lines;
    }

    public function ensureDefaultFeeTypes(): array
    {
        $items = [
            'inscription' => ['Inscription / Réinscription', 'INS_REINS'],
            'novembre' => ['Scolarité novembre', 'SCO_NOV'],
            'fevrier' => ['Scolarité février', 'SCO_FEV'],
            'conseil' => ["Conseil de l'école", 'CONSEIL'],
            'cis' => ["Carte d'identité scolaire", 'CIS'],
            'frais_inscription' => ["Frais d'inscription et réinscription", 'FRAIS_INS'],
            'tenue_scolaire' => ['Tenue scolaire', 'TENUE_SCO'],
            'bibliotheque' => ['Bibliothèque', 'BIB'],
            'tenue_sport' => ['Tenue de sport', 'SPORT'],
            'blouse' => ['Blouse', 'BLOUSE'],
            'kodo' => ['Kodo dunda', 'KODO'],
            'tshirt' => ['T-shirt', 'TSHIRT'],
            'tranche_1' => ['1ère tranche', 'TRANCHE_1'],
            'tranche_2' => ['2ème tranche', 'TRANCHE_2'],
            'tranche_3' => ['3ème tranche', 'TRANCHE_3'],
        ];

        return collect($items)
            ->mapWithKeys(function (array $item, string $key) {
                $feeType = FeeType::updateOrCreate(
                    ['code' => $item[1]],
                    [
                        'name' => $item[0],
                        'is_required' => true,
                        'status' => 'active',
                    ],
                );

                return [$key => $feeType->id];
            })
            ->all();
    }

    private function defaultLinesForClass(SchoolClass $schoolClass, array $feeTypes): array
    {
        $name = Str::lower($schoolClass->name.' '.($schoolClass->level?->name ?? ''));

        if (str_contains($name, 'bep1') || str_contains($name, 'génie civil') || str_contains($name, 'genie civil') || str_contains($name, 'électrotechnique') || str_contains($name, 'electrotechnique')) {
            return $this->secondaryLines($feeTypes, 120000, 40000, 40000);
        }

        if (str_contains($name, 'cp1')) {
            return [
                ['fee_type_id' => $feeTypes['tranche_1'], 'period' => '1ère tranche', 'amount' => 50000],
                ['fee_type_id' => $feeTypes['tranche_2'], 'period' => '2ème tranche', 'amount' => 20000],
                ['fee_type_id' => $feeTypes['tranche_3'], 'period' => '3ème tranche', 'amount' => 20000],
            ];
        }

        if (str_contains($name, '3') || str_contains($name, '2nde') || str_contains($name, '1re') || str_contains($name, 'tle') || str_contains($name, 'terminale')) {
            return $this->secondaryLines($feeTypes, 60000, 25000, 25000);
        }

        return $this->secondaryLines($feeTypes, 50000, 25000, 25000);
    }

    private function secondaryLines(array $feeTypes, int $inscription, int $novembre, int $fevrier): array
    {
        return [
            ['fee_type_id' => $feeTypes['inscription'], 'period' => 'Inscription', 'amount' => $inscription],
            ['fee_type_id' => $feeTypes['novembre'], 'period' => 'Novembre 2026', 'amount' => $novembre, 'due_date' => '2026-11-30'],
            ['fee_type_id' => $feeTypes['fevrier'], 'period' => 'Février 2027', 'amount' => $fevrier, 'due_date' => '2027-02-28'],
            ['fee_type_id' => $feeTypes['conseil'], 'period' => 'Frais annexes', 'amount' => 1000],
            ['fee_type_id' => $feeTypes['cis'], 'period' => 'Frais annexes', 'amount' => 1000],
            ['fee_type_id' => $feeTypes['frais_inscription'], 'period' => 'Frais annexes', 'amount' => 2500],
            ['fee_type_id' => $feeTypes['tenue_scolaire'], 'period' => 'Frais annexes', 'amount' => 5500],
            ['fee_type_id' => $feeTypes['bibliotheque'], 'period' => 'Frais annexes', 'amount' => 5000],
            ['fee_type_id' => $feeTypes['tenue_sport'], 'period' => 'Frais annexes', 'amount' => 8000],
            ['fee_type_id' => $feeTypes['blouse'], 'period' => 'Frais annexes', 'amount' => 8000],
            ['fee_type_id' => $feeTypes['kodo'], 'period' => 'Frais annexes', 'amount' => 3500],
            ['fee_type_id' => $feeTypes['tshirt'], 'period' => 'Frais annexes', 'amount' => 3000],
        ];
    }
}
