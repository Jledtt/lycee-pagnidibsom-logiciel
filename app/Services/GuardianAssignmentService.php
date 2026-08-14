<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class GuardianAssignmentService
{
    public function __construct(
        private readonly AuditTrailService $auditTrail,
    ) {}

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

            $this->attach(
                $guardian,
                $student,
                $relationship,
                $relationship === 'father',
                true,
                false,
            );

            return $guardian;
        });
    }

    public function attach(
        Guardian $guardian,
        Student $student,
        string $relationship,
        bool $isPrimary,
        bool $canReceiveSms,
        bool $canPickupChild,
    ): void {
        DB::transaction(function () use ($guardian, $student, $relationship, $isPrimary, $canReceiveSms, $canPickupChild): void {
            Guardian::query()->lockForUpdate()->findOrFail($guardian->id);
            Student::withTrashed()->lockForUpdate()->findOrFail($student->id);

            $studentLinks = DB::table('guardian_student')
                ->where('student_id', $student->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $oldLink = $studentLinks->firstWhere('guardian_id', $guardian->id);
            $replacedLinks = collect();

            if (in_array($relationship, ['father', 'mother', 'tutor'], true)) {
                $replacedLinks = $studentLinks
                    ->where('relationship', $relationship)
                    ->where('guardian_id', '!=', $guardian->id);

                if ($replacedLinks->isNotEmpty()) {
                    DB::table('guardian_student')
                        ->whereIn('id', $replacedLinks->pluck('id'))
                        ->delete();
                }
            }

            $replacedGuardianIds = $replacedLinks->pluck('guardian_id');

            $hasOtherPrimary = $studentLinks
                ->where('guardian_id', '!=', $guardian->id)
                ->whereNotIn('guardian_id', $replacedGuardianIds)
                ->contains(fn (object $link): bool => (bool) $link->is_primary);
            $makePrimary = $isPrimary || ! $hasOtherPrimary;

            if ($makePrimary) {
                $demotedLinks = $studentLinks
                    ->where('guardian_id', '!=', $guardian->id)
                    ->where('is_primary', true)
                    ->whereNotIn('guardian_id', $replacedGuardianIds);

                DB::table('guardian_student')
                    ->where('student_id', $student->id)
                    ->where('guardian_id', '!=', $guardian->id)
                    ->update(['is_primary' => false, 'updated_at' => now()]);

                Guardian::query()
                    ->whereIn('id', $demotedLinks->pluck('guardian_id'))
                    ->get()
                    ->each(function (Guardian $demotedGuardian) use ($demotedLinks, $guardian, $student): void {
                        $oldValues = $this->linkValues(
                            $demotedLinks->firstWhere('guardian_id', $demotedGuardian->id),
                        );

                        $this->auditTrail->record(
                            'guardian.primary_demoted',
                            $demotedGuardian,
                            $oldValues,
                            array_merge($oldValues, [
                                'is_primary' => false,
                                'new_primary_guardian_id' => $guardian->id,
                            ]),
                            "{$demotedGuardian->full_name} n’est plus le contact principal de {$student->full_name}.",
                        );
                    });
            }

            $pivot = DB::table('guardian_student')
                ->where('guardian_id', $guardian->id)
                ->where('student_id', $student->id);
            $values = [
                'relationship' => $relationship,
                'is_primary' => $makePrimary,
                'can_receive_sms' => $canReceiveSms,
                'can_pickup_child' => $canPickupChild,
                'updated_at' => now(),
            ];

            if ($pivot->exists()) {
                $pivot->update($values);
            } else {
                DB::table('guardian_student')->insert($values + [
                    'guardian_id' => $guardian->id,
                    'student_id' => $student->id,
                    'created_at' => now(),
                ]);
            }

            Guardian::query()
                ->whereIn('id', $replacedGuardianIds)
                ->get()
                ->each(function (Guardian $replacedGuardian) use ($guardian, $replacedLinks, $student): void {
                    $oldValues = $this->linkValues(
                        $replacedLinks->firstWhere('guardian_id', $replacedGuardian->id),
                    );

                    $this->auditTrail->record(
                        'guardian.student_detached',
                        $replacedGuardian,
                        $oldValues,
                        [
                            'student_id' => $student->id,
                            'replaced_by_guardian_id' => $guardian->id,
                        ],
                        "Remplacement du responsable {$replacedGuardian->full_name} pour {$student->full_name}.",
                    );
                });

            $newValues = [
                'student_id' => $student->id,
                'relationship' => $relationship,
                'is_primary' => $makePrimary,
                'can_receive_sms' => $canReceiveSms,
                'can_pickup_child' => $canPickupChild,
            ];
            $this->auditTrail->record(
                $oldLink ? 'guardian.student_relationship_updated' : 'guardian.student_attached',
                $guardian,
                $this->linkValues($oldLink),
                $newValues,
                ($oldLink ? 'Mise à jour' : 'Ajout')." de {$guardian->full_name} comme responsable de {$student->full_name}.",
            );
        });
    }

    public function detach(Guardian $guardian, Student $student): void
    {
        DB::transaction(function () use ($guardian, $student): void {
            $links = DB::table('guardian_student')
                ->where('student_id', $student->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $removed = $links->firstWhere('guardian_id', $guardian->id);

            if (! $removed) {
                return;
            }

            DB::table('guardian_student')
                ->where('student_id', $student->id)
                ->where('guardian_id', $guardian->id)
                ->delete();

            $this->auditTrail->record(
                'guardian.student_detached',
                $guardian,
                $this->linkValues($removed),
                ['student_id' => $student->id],
                "Retrait de {$guardian->full_name} du dossier de {$student->full_name}.",
            );

            if (! $removed->is_primary) {
                return;
            }

            $replacement = $links
                ->where('guardian_id', '!=', $guardian->id)
                ->sortBy(fn (object $link): int => match ($link->relationship) {
                    'father' => 1,
                    'mother' => 2,
                    'tutor' => 3,
                    default => 4,
                })
                ->first();

            if ($replacement) {
                DB::table('guardian_student')
                    ->where('id', $replacement->id)
                    ->update(['is_primary' => true, 'updated_at' => now()]);

                $replacementGuardian = Guardian::query()->find($replacement->guardian_id);

                if ($replacementGuardian) {
                    $oldValues = $this->linkValues($replacement);
                    $this->auditTrail->record(
                        'guardian.primary_promoted',
                        $replacementGuardian,
                        $oldValues,
                        array_merge($oldValues, ['is_primary' => true]),
                        "{$replacementGuardian->full_name} devient le contact principal de {$student->full_name}.",
                    );
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function linkValues(?object $link): array
    {
        if (! $link) {
            return [];
        }

        return [
            'student_id' => (int) $link->student_id,
            'relationship' => (string) $link->relationship,
            'is_primary' => (bool) $link->is_primary,
            'can_receive_sms' => (bool) $link->can_receive_sms,
            'can_pickup_child' => (bool) $link->can_pickup_child,
        ];
    }
}
