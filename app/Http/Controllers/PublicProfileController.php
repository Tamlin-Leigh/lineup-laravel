<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The read-only profile other users see from Discover. It leaves out contact details (email,
 * phone, manager contact): reaching someone goes through "Contact now" instead.
 */
class PublicProfileController extends Controller
{
    public function __invoke(User $user): View
    {
        Gate::authorize('view', $user);

        $user->load(['venueProfile', 'bandProfile.links', 'organiserProfile.links']);

        return view('profiles.show', [
            'user' => $user,
            'freeDays' => $user->availabilities()->upcoming()->limit(12)->pluck('date'),
            'events' => $user->events()->published()->upcoming()->limit(5)->get(),
        ]);
    }
}
