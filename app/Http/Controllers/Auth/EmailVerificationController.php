<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;

class EmailVerificationController extends Controller
{
    /**
     * Verify a user's email from the signed link in their inbox.
     *
     * The URL signature (checked by the `signed` middleware) together with the
     * email hash authenticate the request, so no logged-in session is required —
     * the link works from any device, not just the one that registered. On
     * success the user is sent back into the SPA.
     */
    public function __invoke(string $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->away(
            rtrim((string) config('app.frontend_url'), '/').'/confirm-email?verified=1'
        );
    }
}
