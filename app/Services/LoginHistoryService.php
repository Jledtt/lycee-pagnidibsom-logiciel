<?php

namespace App\Services;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LoginHistoryService
{
    public function record(Request $request, string $status, ?User $user = null): LoginHistory
    {
        return LoginHistory::query()->create([
            'user_id' => $user?->id,
            'username' => $user?->username ?? $request->string('username')->toString(),
            'status' => $status,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'created_at' => now(),
        ]);
    }
}
