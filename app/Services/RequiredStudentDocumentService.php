<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Collection;

class RequiredStudentDocumentService
{
    public function requiredTypes(): array
    {
        return [
            'birth_certificate' => 'Acte de naissance',
            'photo' => 'Photo',
            'previous_report_card' => 'Ancien bulletin',
            'parent_authorization' => 'Autorisation parentale',
        ];
    }

    public function statusForStudent(Student $student): array
    {
        $receivedTypes = $student->documents
            ->where('status', 'received')
            ->pluck('document_type')
            ->unique()
            ->all();

        return collect($this->requiredTypes())
            ->map(fn (string $label, string $type) => [
                'type' => $type,
                'label' => $label,
                'is_received' => in_array($type, $receivedTypes, true),
            ])
            ->values()
            ->all();
    }

    public function missingForStudent(Student $student): array
    {
        return collect($this->statusForStudent($student))
            ->reject(fn (array $document) => $document['is_received'])
            ->values()
            ->all();
    }

    public function reportRows(Collection $enrollments): Collection
    {
        return $enrollments
            ->map(function ($enrollment) {
                $student = $enrollment->student;
                $missingDocuments = $student ? $this->missingForStudent($student) : [];

                return [
                    'enrollment' => $enrollment,
                    'student' => $student,
                    'class' => $enrollment->schoolClass,
                    'missing_documents' => $missingDocuments,
                    'missing_count' => count($missingDocuments),
                    'is_complete' => count($missingDocuments) === 0,
                ];
            })
            ->values();
    }

    public function summary(Collection $rows): array
    {
        return [
            'students' => $rows->count(),
            'complete' => $rows->where('is_complete', true)->count(),
            'incomplete' => $rows->where('is_complete', false)->count(),
            'missing_documents' => $rows->sum('missing_count'),
        ];
    }
}
