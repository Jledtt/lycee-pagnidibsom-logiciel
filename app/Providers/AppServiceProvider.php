<?php

namespace App\Providers;

use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\Grade;
use App\Models\MockExam;
use App\Models\MockExamCandidate;
use App\Models\MockExamScore;
use App\Models\MockExamSubject;
use App\Models\NumberingSetting;
use App\Models\Payment;
use App\Models\ReportCard;
use App\Models\RequiredStudentDocument;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\Subject;
use App\Models\TeacherDocument;
use App\Models\TeacherFeeStatement;
use App\Models\TeacherProfile;
use App\Models\TeacherWorkSession;
use App\Models\TermPeriod;
use App\Models\Timetable;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use App\Services\GuidedTourService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('reset-staff-password', fn (User $actor, User $target) => $actor->can('users.manage') && ! $actor->is($target));
        Gate::define('deactivate-staff-user', fn (User $actor, User $target) => $actor->can('users.manage') && ! $actor->is($target));

        foreach ($this->auditedModels() as $model) {
            $model::observe(ActivityLogObserver::class);
        }

        View::composer('*', function ($view) {
            $settings = null;

            try {
                if (Schema::hasTable('school_settings')) {
                    $settings = SchoolSetting::query()->first();
                }
            } catch (\Throwable) {
                $settings = null;
            }

            $view->with('schoolSettings', $settings);
        });

        View::composer('layouts.app', function ($view): void {
            $user = request()->user();

            $view->with(
                'guidedTours',
                $user ? app(GuidedTourService::class)->visibleFor($user) : collect(),
            );
        });
    }

    private function auditedModels(): array
    {
        return [
            Assessment::class,
            AttendanceRecord::class,
            ClassSubject::class,
            Enrollment::class,
            FeeSchedule::class,
            Grade::class,
            MockExam::class,
            MockExamCandidate::class,
            MockExamScore::class,
            MockExamSubject::class,
            NumberingSetting::class,
            Payment::class,
            RequiredStudentDocument::class,
            ReportCard::class,
            SchoolClass::class,
            Student::class,
            StudentDocument::class,
            Subject::class,
            Timetable::class,
            TimetableEntry::class,
            TermPeriod::class,
            TeacherDocument::class,
            TeacherFeeStatement::class,
            TeacherProfile::class,
            TeacherWorkSession::class,
            User::class,
        ];
    }
}
