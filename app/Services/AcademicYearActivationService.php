<?php

namespace App\Services;

use App\Models\AcademicYear;
use Closure;
use Illuminate\Support\Facades\DB;

class AcademicYearActivationService
{
    public function activate(int $academicYearId, ?Closure $afterActivation = null): AcademicYear
    {
        return DB::transaction(function () use ($academicYearId, $afterActivation): AcademicYear {
            $years = AcademicYear::query()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $targetYear = $years->firstWhere('id', $academicYearId);

            if ($targetYear === null) {
                abort(404);
            }

            AcademicYear::query()
                ->whereKeyNot($targetYear->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'status' => 'planned',
                ]);

            $targetYear->update([
                'is_active' => true,
                'status' => 'active',
            ]);

            $afterActivation?->__invoke($targetYear);

            return $targetYear;
        });
    }
}
