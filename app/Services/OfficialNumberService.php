<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\NumberingSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OfficialNumberService
{
    public const STUDENT_MATRICULE = 'student_matricule';
    public const PAYMENT_RECEIPT = 'payment_receipt';
    public const STUDENT_CERTIFICATE = 'student_certificate';

    public function generate(string $type, callable $exists, ?AcademicYear $academicYear = null): string
    {
        if (! $this->settingsTableExists()) {
            return $this->fallbackNumber($type, $exists, $academicYear);
        }

        return DB::transaction(function () use ($type, $exists, $academicYear) {
            $setting = NumberingSetting::query()
                ->where('type', $type)
                ->lockForUpdate()
                ->first();

            if (! $setting || $setting->status !== 'active') {
                return $this->fallbackNumber($type, $exists, $academicYear);
            }

            $nextNumber = max((int) $setting->next_number, 1);

            do {
                $number = $this->format($setting, $nextNumber, $academicYear);
                $nextNumber++;
            } while ($exists($number));

            $setting->update(['next_number' => $nextNumber]);

            return $number;
        });
    }

    public function preview(NumberingSetting $setting, ?AcademicYear $academicYear = null): string
    {
        return $this->format($setting, max((int) $setting->next_number, 1), $academicYear);
    }

    private function format(NumberingSetting $setting, int $number, ?AcademicYear $academicYear): string
    {
        $date = now();
        $year = $academicYear?->starts_at?->format('Y') ?? $date->format('Y');
        $shortYear = substr($year, -2);
        $sequence = str_pad((string) $number, max((int) $setting->padding, 1), '0', STR_PAD_LEFT);

        return strtr($setting->format, [
            '{PREFIX}' => (string) $setting->prefix,
            '{YEAR}' => $year,
            '{YY}' => $shortYear,
            '{DATE}' => $date->format('Ymd'),
            '{MONTH}' => $date->format('m'),
            '{DAY}' => $date->format('d'),
            '{NUMBER}' => $sequence,
        ]);
    }

    private function fallbackNumber(string $type, callable $exists, ?AcademicYear $academicYear): string
    {
        $prefix = match ($type) {
            self::PAYMENT_RECEIPT => 'REC-' . now()->format('Ymd') . '-',
            self::STUDENT_CERTIFICATE => 'CERT-' . ($academicYear?->starts_at?->format('Y') ?? now()->format('Y')) . '-',
            default => 'LPP-' . ($academicYear?->starts_at?->format('Y') ?? now()->format('Y')) . '-',
        };

        $nextNumber = 1;

        do {
            $number = $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while ($exists($number));

        return $number;
    }

    private function settingsTableExists(): bool
    {
        try {
            return Schema::hasTable('numbering_settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
