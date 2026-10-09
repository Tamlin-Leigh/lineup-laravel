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
| `events`             | owner, venue (optional BandLink venue), title, description, category, location, `starts_at`, price, `status` |
| `availabilities`     | user_id, `date` (unique per user): whole days an account is free for a gig                         |
| `contact_requests`   | sender, recipient, message, dates asked about: log of "Contact now" emails (for rate limiting)   |

## Product goal

Close the communication gap between venues, bands and organisers: each publishes the days they are free, the
others filter by role and date to find a match, and "Contact now" starts the conversation by email. Events
(phase 5) remain each account's own diary of confirmed gigs.

## Phases

- [x] **0. Scaffold**: Laravel 13, Livewire starter kit (class components), Pest, Larastan (level 7), Pint, CI, Sail (MySQL, Redis, Mailpit), domain enums
- [x] **1. Registration**: multi-step Livewire sign-up with a branch per role and per-step validation; `RegisterUser` action; feature tests per role
- [x] **2. Profiles**: profile editor per role, avatar upload, link repeater, `UserPolicy`
- [x] **3. Auth pages**: log in, password reset, verification and 2FA screens on the BandLink split layout; `/` redirects like the prototype
- [x] **4. Dashboards**: dashboard per role, `role` middleware, admin overview, BandLink app shell (band event stats are wired up in phase 5)
- [x] **5. Events & calendar**: CRUD, month calendar view, `upcoming()` / `past()` scopes, `EventPolicy`; open to venues, bands and organisers (the prototype limited it to bands); app timezone defaults to Africa/Johannesburg
- [x] **6. Availability**: venues, bands and organisers mark whole days they are free on the calendar ("mark all weekends" = Fri-Sun, "clear month"); today up to two years ahead; shown with their events and on every dashboard
- [ ] **7. Discover**: filter every role by available dates (plus location / genre); read-only public profiles; calendar overlay of who is free each day
- [ ] **8. Contact now**: modal with a short message sends the recipient an email with the sender's profile and dates, Reply-To the sender (recipient's address stays private); rate limited and logged
- [ ] **9. Polish**: README screenshots, demo data with availability, deployment
