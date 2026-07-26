<?php

namespace App\Services;

use App\Models\CommunicationMessage;
use Illuminate\Support\Carbon;

class CommunicationQuotaService
{
    public function usage(): array
    {
        $dailyLimit = max(0, (int) config('communication.quota.daily', 100));
        $monthlyLimit = max(0, (int) config('communication.quota.monthly', 3000));
        $reserve = max(0, (int) config('communication.quota.daily_reserve', 5));
        $dailyUsable = $dailyLimit > 0 ? max(0, $dailyLimit - $reserve) : 0;

        $dailyUsed = CommunicationMessage::query()
            ->where('status', 'sent')
            ->whereDate('sent_at', today())
            ->count();
        $monthlyUsed = CommunicationMessage::query()
            ->where('status', 'sent')
            ->whereBetween('sent_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        return [
            'daily_limit' => $dailyLimit,
            'daily_usable' => $dailyUsable,
            'daily_used' => $dailyUsed,
            'daily_remaining' => $dailyLimit === 0 ? null : max(0, $dailyUsable - $dailyUsed),
            'monthly_limit' => $monthlyLimit,
            'monthly_used' => $monthlyUsed,
            'monthly_remaining' => $monthlyLimit === 0 ? null : max(0, $monthlyLimit - $monthlyUsed),
            'reserve' => $reserve,
        ];
    }

    public function availability(): array
    {
        $usage = $this->usage();

        if ($usage['monthly_limit'] > 0 && $usage['monthly_remaining'] <= 0) {
            return [
                'available' => false,
                'reason' => 'Quota mensuel Resend atteint.',
                'retry_at' => now()->addMonthNoOverflow()->startOfMonth()->addMinutes(5),
            ];
        }

        if ($usage['daily_limit'] > 0 && $usage['daily_remaining'] <= 0) {
            return [
                'available' => false,
                'reason' => 'Quota quotidien Resend atteint ou réserve quotidienne protégée.',
                'retry_at' => now()->addDay()->startOfDay()->addMinutes(5),
            ];
        }

        return [
            'available' => true,
            'reason' => null,
            'retry_at' => null,
        ];
    }

    public function retryDelay(Carbon $retryAt): int
    {
        return max(60, now()->diffInSeconds($retryAt, false));
    }
}
