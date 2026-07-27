<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\MockExam;
use App\Models\MockExamCandidate;
use App\Models\MockExamSubject;
use App\Models\SchoolClass;
use App\Models\Term;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MockExamService
{
    public function createExam(AcademicYear $academicYear, array $data): MockExam
    {
        $classIds = collect($data['school_class_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $validClassCount = SchoolClass::query()
            ->whereIn('id', $classIds)
            ->where('academic_year_id', $academicYear->id)
            ->count();

        if ($validClassCount !== $classIds->count()) {
            throw ValidationException::withMessages([
                'school_class_ids' => 'Toutes les classes doivent appartenir à cette année scolaire.',
            ]);
        }

        if (! empty($data['term_id']) && ! Term::query()
            ->whereKey($data['term_id'])
            ->where('academic_year_id', $academicYear->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'term_id' => 'Le trimestre choisi n’appartient pas à cette année scolaire.',
            ]);
        }

        return DB::transaction(function () use ($academicYear, $data) {
            $exam = MockExam::query()->create([
                'academic_year_id' => $academicYear->id,
                'term_id' => $data['term_id'] ?? null,
                'name' => $data['name'],
                'exam_type' => $data['exam_type'],
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
                'status' => 'preparation',
                'result_status' => 'preparation',
                'notes' => $data['notes'] ?? null,
            ]);

            $exam->classes()->sync($data['school_class_ids']);
            $this->syncCandidates($exam);
            $this->syncDefaultSubjects($exam);

            return $exam;
        });
    }

    public function syncCandidates(MockExam $exam): int
    {
        $classIds = $exam->classes()->pluck('school_classes.id')->all();

        $enrollments = Enrollment::query()
            ->with('student')
            ->where('academic_year_id', $exam->academic_year_id)
            ->whereIn('school_class_id', $classIds)
            ->where('enrollments.status', 'active')
            ->whereHas('student', fn ($query) => $query->where('students.status', 'active'))
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->select('enrollments.*')
            ->get();

        foreach ($enrollments as $enrollment) {
            MockExamCandidate::query()->firstOrCreate([
                'mock_exam_id' => $exam->id,
                'student_id' => $enrollment->student_id,
            ], [
                'school_class_id' => $enrollment->school_class_id,
                'status' => 'active',
            ]);
        }

        return $enrollments->count();
    }

    public function syncDefaultSubjects(MockExam $exam): int
    {
        $subjects = ClassSubject::query()
            ->whereIn('school_class_id', $exam->classes()->pluck('school_classes.id'))
            ->where('is_active', true)
            ->orderBy('subject_id')
            ->get()
            ->unique('subject_id')
            ->values();

        foreach ($subjects as $index => $classSubject) {
            MockExamSubject::query()->firstOrCreate([
                'mock_exam_id' => $exam->id,
                'subject_id' => $classSubject->subject_id,
                'exam_part' => 'written',
            ], [
                'max_score' => 20,
                'coefficient' => $classSubject->coefficient ?: 1,
                'position' => $index + 1,
            ]);
        }

        return $subjects->count();
    }

    public function generateAnonymousCodes(MockExam $exam, string $prefix = 'X'): int
    {
        $candidates = $this->orderedCandidates($exam);

        foreach ($candidates as $index => $candidate) {
            $candidate->update([
                'anonymous_code' => $prefix.($index + 1),
            ]);
        }

        return $candidates->count();
    }

    public function distributeRooms(MockExam $exam, int $roomCount): int
    {
        $roomCount = max(1, $roomCount);
        $candidates = $this->orderedCandidates($exam);

        foreach ($candidates as $index => $candidate) {
            $candidate->update([
                'room_name' => 'Salle '.(($index % $roomCount) + 1),
            ]);
        }

        return $candidates->count();
    }

    public function eligibleClasses(AcademicYear $academicYear): Collection
    {
        return SchoolClass::query()
            ->with('level')
            ->where('academic_year_id', $academicYear->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    private function orderedCandidates(MockExam $exam): Collection
    {
        return $exam->candidates()
            ->with(['student', 'schoolClass'])
            ->join('students', 'students.id', '=', 'mock_exam_candidates.student_id')
            ->orderBy('mock_exam_candidates.school_class_id')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->select('mock_exam_candidates.*')
            ->get();
    }
}
