<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Platform overview: how many accounts of each role, and who signed up most recently.
     */
    public function __invoke(): View
    {
        $counts = User::query()
            ->toBase()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return view('admin.dashboard', [
            'roleCounts' => collect(UserRole::registrable())
                ->mapWithKeys(fn (UserRole $role) => [$role->value => (int) ($counts[$role->value] ?? 0)]),
            'recentUsers' => User::query()
                ->whereIn('role', UserRole::registrable())
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }
}
