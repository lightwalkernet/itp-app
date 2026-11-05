<?php

namespace App\Livewire\Concerns;

use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\AppUpdateRequired;
use App\Api\Exceptions\SignedOut;
use App\Auth\AppSession;
use Closure;

/**
 * Handles the API outcomes every screen treats the same way. Validation errors from the API are
 * left to Livewire, which shows them on the matching fields.
 */
trait CallsApi
{
    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $call
     * @param  string  $errorField  where an unreachable API is reported
     * @return TResult|null null when the call didn't succeed
     */
    protected function callApi(Closure $call, string $errorField = 'form'): mixed
    {
        try {
            return $call();
        } catch (AppUpdateRequired) {
            $this->redirectRoute('update-required');
        } catch (SignedOut) {
            app(AppSession::class)->endLocally();
            $this->redirectRoute('sign-in');
        } catch (ApiUnavailable $exception) {
            $this->addError($errorField, $exception->getMessage());
        }

        return null;
    }
}
