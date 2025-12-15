<?php

namespace App\Services\Auth;

use App\Enums\TokenAbilityEnum;
use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

readonly class AuthService
{
    public function generateToken(User $user, ?string $userAgent = null, ?string $ipAddress = null): NewAccessToken
    {
        $user->tokens()->delete();

        return $user->createToken(
            name: $userAgent ?? $ipAddress ?? 'user_token',
            abilities: $this->determineAbilities(),
            expiresAt: now()->addMinutes(config('sanctum.expiration'))
        );
    }

    public function determineAbilities(): array
    {
        return [TokenAbilityEnum::FULL_ACCESS->value];
    }
}
