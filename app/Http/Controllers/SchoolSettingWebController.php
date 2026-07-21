<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SchoolSettingWebController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', [
            'academicYear' => $this->activeAcademicYear(),
            'academicYears' => AcademicYear::query()->orderByDesc('starts_at')->get(),
            'settings' => $this->settings(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = $this->settings();

        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:10'],
            'motto' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'national_motto' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_box' => ['nullable', 'string', 'max:255'],
            'principal_name' => ['nullable', 'string', 'max:255'],
            'principal_title' => ['nullable', 'string', 'max:255'],
            'accountant_name' => ['nullable', 'string', 'max:255'],
            'active_academic_year_id' => ['required', 'exists:academic_years,id'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $filename = 'school-logo-' . Str::random(8) . '.' . $logo->getClientOriginalExtension();
            $logo->move(public_path('images'), $filename);
            $data['logo_path'] = 'images/' . $filename;
        }

        DB::transaction(function () use ($settings, $data) {
            AcademicYear::query()->update(['is_active' => false]);
            AcademicYear::query()->whereKey($data['active_academic_year_id'])->update(['is_active' => true]);

            unset($data['active_academic_year_id'], $data['logo']);

            $settings->update($data);
        });

        return redirect()
            ->route('settings.edit')
            ->with('success', 'Parametres de l ecole mis a jour.');
    }

    private function settings(): SchoolSetting
    {
        return SchoolSetting::query()->firstOrCreate(
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
                'accountant_name' => 'Le Comptable',
            ],
        );
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }
}
