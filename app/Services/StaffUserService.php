<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class StaffUserService
{
    public function create(array $data): User
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'status' => $data['status'],
        ]);

        $user->syncRoles([$data['role']]);

        return $user;
    }

    public function update(User $user, array $data, User $actor): User
    {
        if ($user->is($actor) && $data['status'] !== 'active') {
            throw ValidationException::withMessages([
                'status' => 'Tu ne peux pas desactiver ton propre compte.',
            ]);
        }

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->update($payload);
        $user->syncRoles([$data['role']]);

        return $user;
    }

    public function deactivate(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages([
                'user' => 'Tu ne peux pas desactiver ton propre compte.',
            ]);
        }

        $user->update(['status' => 'inactive']);
    }
}
