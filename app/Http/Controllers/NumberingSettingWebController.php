<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\NumberingSetting;
use App\Services\OfficialNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NumberingSettingWebController extends Controller
{
    public function index(OfficialNumberService $officialNumberService): View
    {
        $academicYear = $this->activeAcademicYear();
        $settings = NumberingSetting::query()
            ->orderByRaw("case type when 'student_matricule' then 1 when 'payment_receipt' then 2 when 'student_certificate' then 3 else 99 end")
            ->get();

        return view('settings.numbering', [
            'academicYear' => $academicYear,
            'previews' => $settings->mapWithKeys(fn (NumberingSetting $setting) => [
                $setting->id => $officialNumberService->preview($setting, $academicYear),
            ]),
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.id' => ['required', 'exists:numbering_settings,id'],
            'settings.*.prefix' => ['nullable', 'string', 'max:30'],
            'settings.*.format' => ['required', 'string', 'max:120'],
            'settings.*.padding' => ['required', 'integer', 'min:1', 'max:10'],
            'settings.*.next_number' => ['required', 'integer', 'min:1', 'max:9999999'],
            'settings.*.status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        foreach ($data['settings'] as $line) {
            if (! str_contains($line['format'], '{NUMBER}')) {
                return back()
                    ->withErrors(['settings' => 'Chaque format doit contenir la variable {NUMBER}.'])
                    ->withInput();
            }

            NumberingSetting::query()
                ->whereKey($line['id'])
                ->update([
                    'prefix' => $line['prefix'] ?? null,
                    'format' => $line['format'],
                    'padding' => $line['padding'],
                    'next_number' => $line['next_number'],
                    'status' => $line['status'],
                ]);
        }

        return redirect()
            ->route('settings.numbering.index')
            ->with('success', 'Paramètres de numérotation mis à jour.');
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }
}
