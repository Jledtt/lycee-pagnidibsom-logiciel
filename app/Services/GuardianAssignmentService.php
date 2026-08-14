<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class GuardianAssignmentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function syncRelationship(
        Student $student,
        array $data,
        string $prefix,
        string $relationship,
    ): ?Guardian {
        return DB::transaction(function () use ($student, $data, $prefix, $relationship): ?Guardian {
            $phone = trim((string) ($data[$prefix.'_phone_primary'] ?? ''));
            $lastName = trim((string) ($data[$prefix.'_last_name'] ?? ''));

            if ($phone === '' || $lastName === '') {
                return null;
            }

            DB::table('guardian_student')
                ->where('student_id', $student->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $guardian = Guardian::query()
                ->where('phone_primary', $phone)
                ->lockForUpdate()
                ->first();

            if (! $guardian) {
                $guardian = Guardian::query()->create([
                    'first_name' => trim((string) ($data[$prefix.'_first_name'] ?? '')),
                    'last_name' => $lastName,
                    'phone_primary' => $phone,
                    'email' => $data[$prefix.'_email'] ?? null,
                    'profession' => $data[$prefix.'_profession'] ?? null,
                    'service' => $data[$prefix.'_service'] ?? null,
                    'status' => 'active',
                ]);
            } else {
                $guardian->update([
                    'first_name' => trim((string) ($data[$prefix.'_first_name'] ?? $guardian->first_name)),
                    'last_name' => $lastName,
                    'email' => $data[$prefix.'_email'] ?? $guardian->email,
                    'profession' => $data[$prefix.'_profession'] ?? $guardian->profession,
                    'service' => $data[$prefix.'_service'] ?? $guardian->service,
                ]);
            }

            DB::table('guardian_student')
                ->where('student_id', $student->id)
                ->where('relationship', $relationship)
                ->where('guardian_id', '!=', $guardian->id)
                ->delete();

            $makePrimary = $relationship === 'father'
                || ! DB::table('guardian_student')
                    ->where('student_id', $student->id)
                    ->where('is_primary', true)
                    ->where('guardian_id', '!=', $guardian->id)
                    ->exists();

            if ($makePrimary) {
                DB::table('guardian_student')
                    ->where('student_id', $student->id)
                    ->where('guardian_id', '!=', $guardian->id)
                    ->update(['is_primary' => false, 'updated_at' => now()]);
            }

            $pivot = DB::table('guardian_student')
                ->where('student_id', $student->id)
                ->where('guardian_id', $guardian->id);

            if ($pivot->exists()) {
                $pivot->update([
                    'relationship' => $relationship,
                    'is_primary' => $makePrimary,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('guardian_student')->insert([
                    'guardian_id' => $guardian->id,
                    'student_id' => $student->id,
                    'relationship' => $relationship,
                    'is_primary' => $makePrimary,
                    'can_receive_sms' => true,
                    'can_pickup_child' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $guardian;
        });
    }
}
