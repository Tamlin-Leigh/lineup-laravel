<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the dashboard for the signed-in user's role. Admins have their own overview.
     */
    public function __invoke(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->role === UserRole::Admin) {
            return to_route('admin.dashboard');
        }

        $user->load(match ($user->role) {
            UserRole::Venue => ['venueProfile'],
            UserRole::Band => ['bandProfile'],
            UserRole::Organiser => ['organiserProfile.links'],
        });

        $published = fn () => $user->events()->published();

        return view('dashboard', [
            'user' => $user,
            'upcomingEvents' => $published()->upcoming()->limit(3)->get(),
            'upcomingFreeDays' => $user->availabilities()->upcoming()->limit(6)->pluck('date'),
            'freeDayCount' => $user->availabilities()->upcoming()->count(),
            'pendingBookings' => $user->receivedBookingRequests()->awaitingReply()->count(),
            'bookedBeforeYearEnd' => $published()->whereBetween('starts_at', [now(), now()->endOfYear()])->count(),
            'completedEvents' => $published()->past()->count(),
            'pastCategoryCounts' => $published()->past()->get(['category'])
                ->countBy(fn (Event $event) => $event->category->label())
                ->sortDesc(),
        ]);
    }
}
