<?php

namespace App\Queries;

use App\Enums\Region;
use App\Enums\UserRole;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Venues, artists and organisers who are free on at least one day in a date range: the core of
 * "who can I work with on that weekend?". Results carry the free days that fall in the range
 * (`availabilities`) and how many there are (`free_days_count`), most matching days first.
 */
class AvailableAccounts
{
    /**
     * @param  list<UserRole>  $roles  Which roles to include; empty means every registrable role.
     * @return Builder<User>
     */
    public function query(User $viewer, CarbonInterface $from, CarbonInterface $to, array $roles = [], string $search = '', ?Region $region = null): Builder
    {
        // array_intersect() compares as strings, which enum cases can't be; filter strictly instead.
        $roles = array_values(array_filter(
            $roles ?: UserRole::registrable(),
            fn (UserRole $role) => in_array($role, UserRole::registrable(), true),
        ));
        $inRange = fn (Builder|HasMany $query) => $query
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString());

        return User::query()
            ->whereIn('role', $roles)
            ->whereKeyNot($viewer->getKey())
            ->whereNotNull('email_verified_at')
            ->whereHas('availabilities', $inRange)
            ->withCount(['availabilities as free_days_count' => $inRange])
            ->with([
                'availabilities' => fn (HasMany $query) => $inRange($query)->orderBy('date'),
                'venueProfile',
                'artistProfile',
                'organiserProfile',
            ])
            ->when(trim($search) !== '', fn (Builder $query) => $this->search($query, trim($search)))
            ->when($region, fn (Builder $query, Region $region) => $query->where(fn (Builder $query) => $query
                ->whereRelation('artistProfile', 'region', $region)
                ->orWhereRelation('venueProfile', 'region', $region)))
            ->orderByDesc('free_days_count')
            ->orderBy('name');
    }

    /**
     * Match the account name, artist name or genre, organisation name, or venue location.
     *
     * @param  Builder<User>  $query
     */
    private function search(Builder $query, string $term): void
    {
        $like = '%'.addcslashes($term, '\\%_').'%';

        $query->where(fn (Builder $query) => $query
            ->where('name', 'like', $like)
            ->orWhereHas('artistProfile', fn (Builder $artist) => $artist->where('artist_name', 'like', $like)->orWhere('genre', 'like', $like))
            ->orWhereHas('venueProfile', fn (Builder $venue) => $venue->where('location', 'like', $like))
            ->orWhereHas('organiserProfile', fn (Builder $organiser) => $organiser->where('org_name', 'like', $like)));
    }
}
