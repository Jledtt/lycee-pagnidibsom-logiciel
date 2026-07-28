<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\FeeSchedule;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Services\TariffDefaultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TariffWebController extends Controller
{
    public function index(): View
    {
        $academicYear = $this->activeAcademicYear();

        $classes = SchoolClass::query()
            ->with('level')
            ->withCount(['enrollments' => fn ($query) => $query->where('status', 'active')])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(function (SchoolClass $schoolClass) use ($academicYear) {
                $schoolClass->tariff_total = FeeSchedule::query()
                    ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
                    ->where('school_class_id', $schoolClass->id)
                    ->sum('amount');
                $schoolClass->tariff_lines_count = FeeSchedule::query()
                    ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
                    ->where('school_class_id', $schoolClass->id)
                    ->count();

                return $schoolClass;
            });

        $totalExpected = $classes->sum(fn (SchoolClass $schoolClass) => $schoolClass->tariff_total * $schoolClass->enrollments_count);

        return view('tariffs.index', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'totalExpected' => $totalExpected,
        ]);
    }

    public function edit(SchoolClass $schoolClass): View
    {
        $academicYear = $this->requireActiveAcademicYear();

        $schedules = FeeSchedule::query()
            ->with('feeType')
            ->where('academic_year_id', $academicYear->id)
            ->where('school_class_id', $schoolClass->id)
            ->orderBy('period')
            ->orderBy('id')
            ->get();

        return view('tariffs.edit', [
            'academicYear' => $academicYear,
            'schoolClass' => $schoolClass->load('level'),
            'schedules' => $schedules,
            'feeTypes' => FeeType::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();

        $data = $request->validate([
            'lines' => ['nullable', 'array'],
            'lines.*.id' => ['nullable', 'exists:fee_schedules,id'],
            'lines.*.fee_type_id' => ['nullable', 'exists:fee_types,id'],
            'lines.*.period' => ['nullable', 'string', 'max:255'],
            'lines.*.amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.due_date' => ['nullable', 'date'],
            'lines.*.delete' => ['nullable', 'boolean'],
            'new_fee_type_name' => ['nullable', 'string', 'max:255'],
        ]);

        if (filled($data['new_fee_type_name'] ?? null)) {
            FeeType::firstOrCreate(
                ['name' => $data['new_fee_type_name']],
                [
                    'code' => Str::upper(Str::slug($data['new_fee_type_name'], '_')),
                    'is_required' => true,
                    'status' => 'active',
                ],
            );
        }

        foreach ($data['lines'] ?? [] as $line) {
            if (! empty($line['id']) && ! empty($line['delete'])) {
                FeeSchedule::query()
                    ->where('id', $line['id'])
                    ->where('academic_year_id', $academicYear->id)
                    ->where('school_class_id', $schoolClass->id)
                    ->delete();

                continue;
            }

            if (blank($line['fee_type_id'] ?? null) || blank($line['amount'] ?? null)) {
                continue;
            }

            $payload = [
                'academic_year_id' => $academicYear->id,
                'school_class_id' => $schoolClass->id,
                'fee_type_id' => $line['fee_type_id'],
                'period' => $line['period'] ?? null,
                'amount' => $line['amount'],
                'due_date' => $line['due_date'] ?? null,
            ];

            if (! empty($line['id'])) {
                FeeSchedule::query()
                    ->where('id', $line['id'])
                    ->where('academic_year_id', $academicYear->id)
                    ->where('school_class_id', $schoolClass->id)
                    ->update($payload);
            } else {
                FeeSchedule::updateOrCreate(
                    [
                        'academic_year_id' => $academicYear->id,
                        'school_class_id' => $schoolClass->id,
                        'fee_type_id' => $line['fee_type_id'],
                        'period' => $line['period'] ?? null,
                    ],
                    $payload,
                );
            }
        }

        return redirect()
            ->route('tariffs.edit', $schoolClass)
            ->with('success', 'Tarifs mis à jour.');
    }

    public function applyDefaults(TariffDefaultService $tariffDefaults): RedirectResponse
    {
        $result = $tariffDefaults->applyToActiveAcademicYear();

        return redirect()
            ->route('tariffs.index')
            ->with('success', $result['lines'].' ligne(s) de tarifs initialisées depuis l’affiche.');
    }

    public function applyClassDefaults(SchoolClass $schoolClass, TariffDefaultService $tariffDefaults): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();
        $lines = $tariffDefaults->applyToClass($academicYear, $schoolClass);

        return redirect()
            ->route('tariffs.edit', $schoolClass)
            ->with('success', $lines.' ligne(s) de tarifs officiels appliquées.');
    }

    private function applyDefaultsLegacy(): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();
        $feeTypes = $this->ensureDefaultFeeTypes();
        $created = 0;

        SchoolClass::query()
            ->with('level')
            ->where('academic_year_id', $academicYear->id)
            ->where('status', 'active')
            ->get()
            ->each(function (SchoolClass $schoolClass) use ($academicYear, $feeTypes, &$created) {
                foreach ($this->defaultLinesForClass($schoolClass, $feeTypes) as $line) {
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
                    $created++;
                }
            });

        return redirect()
            ->route('tariffs.index')
            ->with('success', $created.' ligne(s) de tarifs initialisées depuis l’affiche.');
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    private function requireActiveAcademicYear(): AcademicYear
    {
        $academicYear = $this->activeAcademicYear();

        abort_if(! $academicYear, 422, 'Aucune année scolaire active.');

        return $academicYear;
    }

    private function ensureDefaultFeeTypes(): array
    {
        $items = [
            'inscription' => ['Inscription / Réinscription', 'INS_REINS'],
            'novembre' => ['Scolarité novembre', 'SCO_NOV'],
            'fevrier' => ['Scolarité février', 'SCO_FEV'],
            'conseil' => ['Conseil de l ecole', 'CONSEIL'],
            'cis' => ['Carte d identite scolaire', 'CIS'],
            'frais_inscription' => ['Frais d’inscription et réinscription', 'FRAIS_INS'],
            'tenue_scolaire' => ['Tenue scolaire', 'TENUE_SCO'],
            'bibliotheque' => ['Bibliotheque', 'BIB'],
            'tenue_sport' => ['Tenue de sport', 'SPORT'],
            'blouse' => ['Blouse', 'BLOUSE'],
            'kodo' => ['Kodo dunda', 'KODO'],
            'tshirt' => ['T-shirt', 'TSHIRT'],
            'tranche_1' => ['1ere tranche', 'TRANCHE_1'],
            'tranche_2' => ['2eme tranche', 'TRANCHE_2'],
            'tranche_3' => ['3eme tranche', 'TRANCHE_3'],
        ];

        return collect($items)
            ->mapWithKeys(function (array $item, string $key) {
                $feeType = FeeType::firstOrCreate(
                    ['name' => $item[0]],
                    [
                        'code' => $item[1],
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

        if (str_contains($name, 'bep1') || str_contains($name, 'genie civil') || str_contains($name, 'electrotechnique')) {
            return $this->secondaryLines($feeTypes, 120000, 40000, 40000);
        }

        if (str_contains($name, 'cp1')) {
            return [
                ['fee_type_id' => $feeTypes['tranche_1'], 'period' => '1ere tranche', 'amount' => 50000],
                ['fee_type_id' => $feeTypes['tranche_2'], 'period' => '2eme tranche', 'amount' => 20000],
                ['fee_type_id' => $feeTypes['tranche_3'], 'period' => '3eme tranche', 'amount' => 20000],
            ];
        }

        if (str_contains($name, '3') || str_contains($name, '2nde') || str_contains($name, '1re')) {
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
