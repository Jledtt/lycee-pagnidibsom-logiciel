<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\User;

class AttendanceSessionService
{
    public function __construct(
        private readonly CommunicationService $communicationService,
    ) {
    }

    public function firstOrCreateSession(AcademicYear $academicYear, int $schoolClassId, string $date, User $creator): AttendanceSession
    {
        return AttendanceSession::query()->firstOrCreate(
            [
                'academic_year_id' => $academicYear->id,
                'school_class_id' => $schoolClassId,
                'session_date' => $date,
            ],
            [
                'created_by' => $creator->id,
            ],
        );
    }

    public function updateRecords(AttendanceSession $session, array $records, User $user): void
    {
        $notifications = collect();

        foreach ($records as $row) {
            $status = $row['status'];
            $isExcused = $status === 'excused';

            $record = AttendanceRecord::query()->firstOrNew([
                'attendance_session_id' => $session->id,
                'student_id' => $row['student_id'],
            ]);
            $previousStatus = $record->exists ? $record->status : null;

            $record->fill([
                'status' => $status,
                'minutes_late' => $status === 'late' ? ($row['minutes_late'] ?? null) : null,
                'reason' => $row['reason'] ?? null,
                'justified_at' => $isExcused ? now() : null,
                'justified_by' => $isExcused ? $user->id : null,
            ])->save();

            if ($previousStatus !== $status && in_array($status, ['absent', 'late'], true)) {
                $notifications->push($record);
            }
        }

        $notifications->each(
            fn (AttendanceRecord $record) => $this->communicationService->queueAttendance($record, $user),
        );
    }

    public function clearRecord(AttendanceRecord $record): void
    {
        $record->forceFill([
            'status' => 'present',
            'minutes_late' => null,
            'reason' => null,
            'justified_at' => null,
            'justified_by' => null,
        ])->save();
    }
}
