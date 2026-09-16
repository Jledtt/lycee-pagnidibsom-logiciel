<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TeacherDeletionService
{
    public function delete(User $teacher, User $actor): void
    {
        if (! $teacher->hasRole('enseignant')) {
            abort(404);
        }

        if ($teacher->is($actor)) {
            throw ValidationException::withMessages([
                'teacher' => 'Tu ne peux pas supprimer ton propre compte.',
            ]);
        }

        $blockers = $this->blockers($teacher);

        if ($blockers !== []) {
            throw ValidationException::withMessages([
                'teacher' => 'Ce professeur ne peut pas être supprimé car son dossier contient : '.implode(', ', $blockers).'. Désactive plutôt son compte pour conserver l’historique.',
            ]);
        }

        $documentPaths = $teacher->teacherDocuments()->pluck('file_path')->filter()->all();

        DB::transaction(function () use ($actor, $teacher): void {
            app(AuditTrailService::class)->record('deleted', $teacher, [
                'name' => $teacher->name,
                'phone' => $teacher->phone,
                'deleted_by' => $actor->id,
            ]);

            $teacher->roles()->detach();
            $teacher->permissions()->detach();
            $teacher->delete();
        });

        foreach ($documentPaths as $path) {
            foreach (['documents', 'local'] as $disk) {
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }
            }
        }
    }

    private function blockers(User $teacher): array
    {
        $references = [
            'school_classes' => ['column' => 'main_teacher_id', 'label' => 'une classe comme professeur principal'],
            'class_subjects' => ['column' => 'teacher_id', 'label' => 'des affectations pédagogiques'],
            'assessments' => ['column' => 'teacher_id', 'label' => 'des évaluations ou notes'],
            'attendance_sessions' => ['column' => 'teacher_id', 'label' => 'des séances de présence'],
            'timetable_entries' => ['column' => 'teacher_id', 'label' => 'des cours dans un emploi du temps'],
            'teacher_work_sessions' => ['column' => 'teacher_id', 'label' => 'des émargements'],
            'teacher_fee_statements' => ['column' => 'teacher_id', 'label' => 'des états d’honoraires'],
        ];

        $blockers = [];

        foreach ($references as $table => $reference) {
            if (DB::table($table)->where($reference['column'], $teacher->id)->exists()) {
                $blockers[] = $reference['label'];
            }
        }

        return $blockers;
    }
}
