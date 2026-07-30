<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 300;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function credentials(): array
    {
        $data = $this->validated();

        return [
            'username' => $data['username'],
            'password' => $data['password'],
        ];
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = max(1, RateLimiter::availableIn($this->throttleKey()));
        $minutes = (int) ceil($seconds / 60);

        throw ValidationException::withMessages([
            'username' => "Trop de tentatives de connexion. Réessaie dans {$minutes} minute(s).",
        ]);
    }

    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
    }

    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    public function throttleKey(): string
    {
        $username = Str::transliterate(Str::lower(trim((string) $this->input('username'))));

        return 'login:'.$username.'|'.$this->ip();
    }
}
