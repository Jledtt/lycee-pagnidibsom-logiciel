<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CommunicationRecipientService
{
    public function guardiansForStudent(Student $student): Collection
    {
        $student->loadMissing('guardians');

        return $this->normalize($student->guardians
            ->where('status', 'active')
            ->map(fn (Guardian $guardian) => $this->guardianRecipient($guardian)));
    }

    public function guardians(?AcademicYear $academicYear, ?int $schoolClassId = null): Collection
    {
        if (! $academicYear) {
            return collect();
        }

        $guardians = Guardian::query()
            ->where('status', 'active')
            ->whereHas('students', function ($studentQuery) use ($academicYear, $schoolClassId) {
                $studentQuery
                    ->where('students.status', 'active')
                    ->whereHas('enrollments', function ($enrollmentQuery) use ($academicYear, $schoolClassId) {
                        $enrollmentQuery
                            ->where('academic_year_id', $academicYear->id)
                            ->where('status', 'active')
                            ->when($schoolClassId, fn ($query) => $query->where('school_class_id', $schoolClassId));
                    });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Guardian $guardian) => $this->guardianRecipient($guardian));

        return $this->normalize($guardians);
    }

    public function staff(?string $roleName = null): Collection
    {
        $users = User::query()
            ->where('status', 'active')
            ->when($roleName, fn ($query) => $query->role($roleName))
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'type' => 'user',
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);

        return $this->normalize($users);
    }

    public function isDeliverable(?string $email): bool
    {
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $domain = Str::lower((string) Str::afterLast($email, '@'));
        $blocked = collect(config('communication.blocked_recipient_domains', []))
            ->map(fn (string $value) => Str::lower($value));

        return ! $blocked->contains(fn (string $value) => $domain === $value || Str::endsWith($domain, '.'.$value));
    }

    private function guardianRecipient(Guardian $guardian): array
    {
        return [
            'type' => 'guardian',
            'id' => $guardian->id,
            'name' => $guardian->full_name,
            'email' => $guardian->email,
        ];
    }

    private function normalize(Collection $recipients): Collection
    {
        return $recipients
            ->filter(fn (array $recipient) => $this->isDeliverable($recipient['email'] ?? null))
            ->map(function (array $recipient) {
                $recipient['email'] = Str::lower(trim($recipient['email']));
                $recipient['name'] = trim($recipient['name']) ?: 'Madame, Monsieur';

                return $recipient;
            })
            ->unique('email')
            ->values();
    }
}
