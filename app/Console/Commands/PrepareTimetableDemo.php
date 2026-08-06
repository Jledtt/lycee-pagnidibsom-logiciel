<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\TeacherAvailability;
use App\Models\TeacherAvailabilitySchedule;
use App\Models\TeacherProfile;
use App\Models\Timetable;
use App\Models\TimetablePeriod;
use App\Models\User;
use App\Services\TimetableGenerationService;
use App\Services\TimetableTemplateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PrepareTimetableDemo extends Command
{
    protected $signature = 'lpp:prepare-timetable-demo
        {--class=3E : Identifiant, code ou nom de la classe}
        {--apply : Appliquer la proposition sous forme de brouillon}';

    protected $description = 'Préparer des professeurs et un emploi du temps automatique de démonstration';

    private const WEEKLY_HOURS = [
        'francais' => 5,
        'mathematiques' => 5,
        'anglais' => 3,
        'histoire geographie' => 3,
        'svt' => 2,
        'physique chimie' => 3,
        'eps' => 2,
        'education civique et morale' => 1,
        'allemand' => 3,
        'technologies de l information et de la communication' => 2,
    ];

    public function handle(
        TimetableTemplateService $templates,
        TimetableGenerationService $generation,
    ): int {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();
        if (! $academicYear) {
            $this->error('Aucune année scolaire active.');

            return self::FAILURE;
        }

        $schoolClass = $this->schoolClass($academicYear);
        if (! $schoolClass) {
            $this->error('Aucune classe ne correspond au filtre « '.$this->option('class').' ».');

            return self::FAILURE;
        }

        if (Timetable::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('school_class_id', $schoolClass->id)
            ->where('status', 'active')
            ->exists()) {
            $this->error('La classe '.$schoolClass->name.' possède déjà un emploi du temps actif. Aucune donnée n’a été modifiée.');

            return self::FAILURE;
        }

        $actor = User::query()
            ->where('status', 'active')
            ->get()
            ->first(fn (User $user): bool => $user->can('timetables.manage'));
        if (! $actor) {
            $this->error('Aucun utilisateur actif ne peut gérer les emplois du temps.');

            return self::FAILURE;
        }

        $assignments = ClassSubject::query()
            ->with(['subject', 'teacher'])
            ->where('school_class_id', $schoolClass->id)
            ->where('is_active', true)
            ->get();
        if ($assignments->isEmpty()) {
            $this->error('Aucune matière active n’est rattachée à la classe '.$schoolClass->name.'.');

            return self::FAILURE;
        }

        $prefix = 'demo.edt.'.$schoolClass->id.'.';
        $foreignTeacher = $assignments->first(fn ($assignment): bool => $assignment->teacher
            && ! str_starts_with((string) $assignment->teacher->username, $prefix));
        if ($foreignTeacher) {
            $this->error('La matière '.$foreignTeacher->subject?->name.' possède déjà un professeur qui n’est pas un compte de démonstration.');

            return self::FAILURE;
        }

        $templates->ensurePeriods($academicYear);
        $periods = TimetablePeriod::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('is_active', true)
            ->where('is_break', false)
            ->orderBy('sort_order')
            ->get();
        if ($periods->isEmpty()) {
            $this->error('Aucun créneau de cours n’est configuré.');

            return self::FAILURE;
        }

        $teachers = DB::transaction(function () use ($academicYear, $actor, $assignments, $periods, $prefix, $templates): array {
            $teachers = [];
            $days = array_keys($templates->days());

            foreach ($assignments->values() as $index => $assignment) {
                $subject = $assignment->subject;
                $subjectKey = $this->subjectKey((string) $subject?->name);
                $username = $prefix.$subject?->id;
                $teacher = User::query()->firstOrNew(['username' => $username]);
                $teacher->fill([
                    'name' => 'Professeur démo - '.$subject?->name,
                    'email' => str_replace('.', '-', $username).'@example.invalid',
                    'status' => 'active',
                ]);
                if (! $teacher->exists) {
                    $teacher->password = Hash::make(Str::random(64));
                }
                $teacher->save();
                $teacher->syncRoles(['enseignant']);

                TeacherProfile::query()->updateOrCreate(
                    ['user_id' => $teacher->id],
                    [
                        'employee_number' => 'DEMO-EDT-'.$assignment->id,
                        'specialty' => $subject?->name,
                        'notes' => 'Compte de démonstration créé pour tester la planification automatique.',
                    ],
                );

                $assignment->update([
                    'teacher_id' => $teacher->id,
                    'weekly_hours' => self::WEEKLY_HOURS[$subjectKey] ?? 2,
                ]);

                $schedule = TeacherAvailabilitySchedule::query()->updateOrCreate(
                    [
                        'academic_year_id' => $academicYear->id,
                        'teacher_id' => $teacher->id,
                    ],
                    [
                        'status' => TeacherAvailabilitySchedule::STATUS_VALIDATED,
                        'notes' => 'Disponibilités de démonstration pour la classe testée.',
                        'source' => 'demo',
                        'submitted_at' => now(),
                        'validated_at' => now(),
                        'updated_by' => $actor->id,
                    ],
                );
                $schedule->availabilities()->delete();

                foreach ($days as $dayIndex => $day) {
                    foreach ($periods as $periodIndex => $period) {
                        $preferredDay = $dayIndex === ($index % count($days));
                        $isSaturdayAfternoon = $day === 'saturday' && $periodIndex >= 5;
                        $schedule->availabilities()->create([
                            'timetable_period_id' => $period->id,
                            'day_of_week' => $day,
                            'status' => $isSaturdayAfternoon
                                ? TeacherAvailability::STATUS_UNAVAILABLE
                                : ($preferredDay ? TeacherAvailability::STATUS_PREFERRED : TeacherAvailability::STATUS_AVAILABLE),
                        ]);
                    }
                }

                $teachers[] = [$subject?->name, $teacher->name, $assignment->weekly_hours.' h'];
            }

            return $teachers;
        });

        $this->table(['Matière', 'Professeur', 'Volume hebdomadaire'], $teachers);

        $run = $generation->generate($academicYear, $actor, [$schoolClass->id]);
        if (! $run->canBeApplied()) {
            $this->error('La proposition n’a pas pu être générée.');
            foreach ($run->diagnostics['blockers'] ?? [] as $blocker) {
                $this->line('- '.$blocker);
            }

            return self::FAILURE;
        }

        $this->info('Proposition n° '.$run->id.' générée pour la classe '.$schoolClass->name.'.');
        if (! $this->option('apply')) {
            $this->warn('La proposition n’a pas été appliquée. Relance la commande avec --apply pour créer le brouillon.');

            return self::SUCCESS;
        }

        $generation->apply($run, $actor);
        $timetable = Timetable::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('school_class_id', $schoolClass->id)
            ->firstOrFail();
        $timetable->update([
            'title' => 'Exemple automatique - '.$schoolClass->name,
            'notes' => 'Brouillon de démonstration généré à partir des disponibilités validées. À contrôler avant publication.',
        ]);

        $this->info('Le brouillon « '.$timetable->title.' » est prêt à être contrôlé.');

        return self::SUCCESS;
    }

    private function schoolClass(AcademicYear $academicYear): ?SchoolClass
    {
        $filter = trim((string) $this->option('class'));

        return SchoolClass::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('status', 'active')
            ->where(function ($query) use ($filter): void {
                $query->whereKey(is_numeric($filter) ? (int) $filter : 0)
                    ->orWhere('code', $filter)
                    ->orWhere('name', $filter);
            })
            ->first();
    }

    private function subjectKey(string $subject): string
    {
        return Str::of($subject)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }
}
