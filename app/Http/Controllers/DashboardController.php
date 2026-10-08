<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
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

        return view('dashboard', ['user' => $user]);
    }
}
