# BandLink: Laravel rebuild plan

BandLink began as a React + Firebase prototype. This repo rebuilds it on Laravel, moving the logic that
lived in Firestore security rules and client-side services into a proper server-side domain.

## Mapping from the Firebase prototype

| Prototype (React + Firebase)                          | Laravel                                                                 |
|-------------------------------------------------------|-------------------------------------------------------------------------|
| One flat `users` Firestore doc per account            | `users` + `venue_profiles` / `band_profiles` / `organiser_profiles`     |
| `role` / `userType` / option strings                  | Backed enums in `app/Enums` with `$casts`                               |
| `{platform, url}[]` arrays on the user doc            | Polymorphic `links` table (`kind`, `platform`, `url`)                   |
| `firestore.rules` (own-doc only, no role/email edits) | Policies + validation that never accepts `role` / `email` on update     |
| `storage.rules` (owner only, images < 5 MB)           | `image\|max:5120` validation, `public` disk, old photo removed on swap  |
| Manual "delete Auth user if profile write fails"      | `RegisterUser` action wrapped in `DB::transaction()`                    |
| `src/data/dummy*.ts`                                   | Factories + `DemoSeeder` with log-in-able demo accounts                 |

## Schema

| Table                | Notes                                                                                              |
|----------------------|----------------------------------------------------------------------------------------------------|
| `users`              | name, email, password, phone, `role`, `profile_photo_path`                                         |
| `venue_profiles`     | user_id, manager name/phone/email, capacity, location, min door charge, sound engineer (+ name), equipment, `event_days` (json) |
| `band_profiles`      | user_id, manager name/phone/email, band name, member count, member names, genre, bio              |
| `organiser_profiles` | user_id, org name, contact number/email                                                            |
| `links`              | morphs `linkable`, `kind`, `platform`, `url`                                                       |
| `events`             | owner, venue, title, description, category, `starts_at`, price, `status`                           |
| `bookings`           | band ↔ venue/event, `status`, start/end                                                            |

## Phases

- [x] **0. Scaffold**: Laravel 13, Livewire starter kit (class components), Pest, Larastan (level 7), Pint, CI, Sail (MySQL, Redis, Mailpit), domain enums
- [ ] **1. Registration**: multi-step Livewire sign-up with a branch per role and per-step validation; `RegisterUser` action; feature tests per role
- [ ] **2. Profiles**: profile editor per role, avatar upload, link repeater, `UserPolicy`
- [ ] **3. Dashboards**: dashboard per role behind role middleware
- [ ] **4. Events & calendar**: CRUD, month calendar view, `upcoming()` / `past()` scopes, `EventPolicy`
- [ ] **5. Booking workflow**: band requests a slot, venue accepts or declines; status transitions guarded by `BookingStatus::canTransitionTo()`; domain events, queued mail + database notifications
- [ ] **6. Polish**: README screenshots, demo credentials, deployment
