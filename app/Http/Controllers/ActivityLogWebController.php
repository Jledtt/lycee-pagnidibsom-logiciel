<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogWebController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['action', 'model', 'user_id', 'search']);

        $logs = ActivityLog::query()
            ->with('user')
            ->when($request->string('action')->toString(), fn ($query, string $action) => $query->where('action', $action))
            ->when($request->string('model')->toString(), fn ($query, string $model) => $query->where('auditable_type', $model))
            ->when($request->integer('user_id'), fn ($query, int $userId) => $query->where('user_id', $userId))
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('description', 'like', "%{$search}%")
                        ->orWhere('auditable_label', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('activity-logs.index', [
            'academicYear' => AcademicYear::query()->where('is_active', true)->first(),
            'actions' => ActivityLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
            'filters' => $filters,
            'logs' => $logs,
            'models' => ActivityLog::query()->select('auditable_type')->distinct()->orderBy('auditable_type')->pluck('auditable_type'),
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function show(ActivityLog $activityLog): View
    {
        $activityLog->load('user');

        return view('activity-logs.show', [
            'academicYear' => AcademicYear::query()->where('is_active', true)->first(),
            'log' => $activityLog,
        ]);
    }
}
