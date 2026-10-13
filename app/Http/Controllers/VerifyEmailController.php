<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\VerifyEmailResponse;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmailController extends Controller
{
    /**
     * Replaces Fortify's verification link handler, which answers a bare 403 when the link has
     * expired or is opened while logged in as a different account. Both now redirect with a
     * message instead; only a tampered hash is still refused.
     */
    public function __invoke(Request $request, string $id, string $hash): Response
    {
        /** @var User $user */
        $user = $request->user();
        $isLinkOwner = hash_equals((string) $user->getKey(), $id);

        if (! $request->hasValidSignature()) {
            if (! $isLinkOwner) {
                return $this->logOutToLogin($request, __('That verification link has expired. Log in to the account it was sent to and request a new one.'));
            }

            return $user->hasVerifiedEmail()
                ? app(VerifyEmailResponse::class)->toResponse($request)
                : to_route('verification.notice')->with('status', 'verification-link-expired');
        }

        if (! $isLinkOwner) {
            $owner = User::find($id);

            abort_if($owner === null, 403);

            return $this->logOutToLogin(
                $request,
                __('That verification link is for :email. Log in as that account to finish verifying.', ['email' => $owner->email]),
                intended: $request->fullUrl(),
            );
        }

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return app(VerifyEmailResponse::class)->toResponse($request);
    }

    /**
     * Log the current account out and send them to the login page with a message, optionally
     * returning to the verification link once they log in as the right account.
     */
    private function logOutToLogin(Request $request, string $message, ?string $intended = null): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($intended !== null) {
            redirect()->setIntendedUrl($intended);
        }

        return to_route('login')->with('status', $message);
    }
}
