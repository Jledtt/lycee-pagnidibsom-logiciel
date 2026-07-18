<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginHistoryWebController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->only(['status', 'user_id', 'search']);

        $histories = LoginHistory::query()
            ->with('user')
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->integer('user_id'), fn ($query, int $userId) => $query->where('user_id', $userId))
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('username', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('login-histories.index', [
            'academicYear' => AcademicYear::query()->where('is_active', true)->first(),
            'filters' => $filters,
            'histories' => $histories,
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }
}
