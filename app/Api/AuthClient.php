<?php

namespace App\Api;

use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\AppUpdateRequired;
use App\Api\Exceptions\SignedOut;
use Illuminate\Validation\ValidationException;

/**
 * The email OTP endpoints on itp-server (platform.md §5). One client for both builds.
 */
class AuthClient extends ApiClient
{
    /**
     * @throws ValidationException|AppUpdateRequired|ApiUnavailable
     */
    public function sendCode(string $email): void
    {
        $this->send('POST', 'auth/send-code', ['email' => $email], errorField: 'email');
    }

    /**
     * @throws ValidationException|AppUpdateRequired|ApiUnavailable
     */
    public function verifyCode(string $email, string $code, string $deviceName): SignIn
    {
        $data = $this->send('POST', 'auth/verify-code', [
            'email' => $email,
            'code' => $code,
            'device_name' => $deviceName,
        ], errorField: 'code');

        return new SignIn($data['user'], $data['token'], (bool) $data['needs_profile']);
    }

    /**
     * @return array<string, mixed> the updated user row
     *
     * @throws ValidationException|AppUpdateRequired|SignedOut|ApiUnavailable
     */
    public function completeProfile(string $firstName): array
    {
        return $this->send('POST', 'auth/complete-profile', ['first_name' => $firstName], authenticated: true, errorField: 'first_name')['user'];
    }

    /**
     * Revoke this device's token. Never throws: signing out locally must not depend on the API.
     */
    public function logout(): void
    {
        if ($this->tokens->get() === null) {
            return;
        }

        try {
            $this->send('POST', 'auth/logout', authenticated: true);
        } catch (ValidationException|AppUpdateRequired|SignedOut|ApiUnavailable) {
            //
        }
    }
}
