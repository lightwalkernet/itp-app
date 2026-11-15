<?php

namespace App\Api;

use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\AppUpdateRequired;
use Illuminate\Validation\ValidationException;

/**
 * Deleting an account on itp-server (platform.md §5, rooms contract). No bearer token: a fresh
 * email code is the identity check, and a deletion code can never sign anyone in.
 */
class AccountClient extends ApiClient
{
    /**
     * Ask for a deletion code. The API answers the same way whether or not the email has an
     * account, so this never reveals who does.
     *
     * @throws ValidationException|AppUpdateRequired|ApiUnavailable
     */
    public function sendDeletionCode(string $email): void
    {
        $this->send('POST', 'account/deletion-code', ['email' => $email], errorField: 'email');
    }

    /**
     * Spend the code and delete the account, with everything in it. A wrong or spent code is a
     * 422 on `code`.
     *
     * @throws ValidationException|AppUpdateRequired|ApiUnavailable
     */
    public function delete(string $email, string $code): void
    {
        $this->send('POST', 'account/delete', ['email' => $email, 'code' => $code], errorField: 'code');
    }
}
