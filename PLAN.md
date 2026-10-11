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
| `band_profiles`      | user_id, manager name/phone/email, band name, member count, member names, genre, bio, booking fee, open to door splits |
| `organiser_profiles` | user_id, org name, contact number/email                                                            |
| `links`              | morphs `linkable`, `kind`, `platform`, `url`                                                       |
| `events`             | owner, venue (set by the owner's own venue or an accepted venue booking), title, description, location, `starts_at`, price, `band_slots`, `status` |
| `availabilities`     | user_id, `date` (unique per user): whole days an account is free for a gig                         |
| `booking_requests`   | event_id, recipient_id (a band or venue; unique together), `status`, optional message, `expires_at` (5 days or the event start), `responded_at` |
| `conversations`      | booking_request_id (unique), owner_id, recipient_id, each side's read/alerted times, last_message_at |
| `messages`           | conversation_id, user_id (null for BandLink notes), body                                    |
| `notifications`      | Laravel database notifications: booking updates shown in-app as well as emailed             |

## Product goal

Close the communication gap between venues, bands and organisers: each publishes the days they are free, the
others filter by role and date to find a match. Any of them can then create an event and book the bands (and, for
bands and organisers, the venue) free on its date (phase 10); once a booking is accepted, the two can chat in the
app (phase 11). Events
(phase 5) remain each account's own diary of gigs.

## Phases

- [x] **0. Scaffold**: Laravel 13, Livewire starter kit (class components), Pest, Larastan (level 7), Pint, CI, Sail (MySQL, Redis, Mailpit), domain enums
- [x] **1. Registration**: multi-step Livewire sign-up with a branch per role and per-step validation; `RegisterUser` action; feature tests per role
- [x] **2. Profiles**: profile editor per role, avatar upload, link repeater, `UserPolicy`
- [x] **3. Auth pages**: log in, password reset, verification and 2FA screens on the BandLink split layout; `/` redirects like the prototype
- [x] **4. Dashboards**: dashboard per role, `role` middleware, admin overview, BandLink app shell (band event stats are wired up in phase 5)
- [x] **5. Events & calendar**: CRUD, month calendar view, `upcoming()` / `past()` scopes, `EventPolicy`; open to venues, bands and organisers (the prototype limited it to bands); app timezone defaults to Africa/Johannesburg
- [x] **6. Availability**: venues, bands and organisers mark whole days they are free on the calendar ("mark all weekends" = Fri-Sun, "clear month"); today up to two years ahead; shown with their events and on every dashboard
- [x] **7. phpMyAdmin**: Sail service on http://localhost:8081 for browsing the local database (bound to 127.0.0.1, auto-login as `sail`)
- [x] **8. Profile UI**: the same tabbed profile for every role (the prototype only had tabs for bands); brand icons for music and social links
- [x] **9. Discover**: filter venues, bands and organisers by who is free on a weekend (Fri-Sun), a day or a date range, plus a name / genre / location search; read-only public profiles that leave out contact details
- [x] **10. Booking requests**: booking happens in one place, the event form, for every role. A new event is a step-by-step wizard (When → Where → Line-up → Details; venues skip Where) because the date decides who is free and the venue sets the location; editing stays one page. venues book bands for events at their own venue; bands and organisers book bands plus one venue (asked one at a time; the event takes the venue's location once it accepts). A band hosting its own event is on the line-up automatically, alongside the bands that accept. Hosting or accepting an event takes that day off your free days (and it can't be marked free again), and booked events show on your calendar. Only accounts free on the event's date are offered. Each gets an email plus a request on their Bookings page (Received / Sent tabs) and has 5 days (or until the event, if sooner) to accept or decline, after which requests expire on a schedule; accepting removes that free day, cancelling the event tells everyone asked; rate limited; queue and scheduler Sail services. Discover stays read-only. Replaces the planned "Contact now" email
- [x] **10.1 Booking fixes**: events say how many bands will play (the host band takes a slot) and only that many can be asked; declined or expired requests free their slot for a new band (never the ones that declined), with "Book another band" links in the decline email and on Bookings; event categories removed (date tiles and band slot counts instead); the calendar key now shows Hosting / Playing / Request waiting / Free; drafts are pending events that can only be published once every band slot is filled by a band that accepted and no venue is still deciding (the owner is emailed when the last confirmation completes it); bands can list a booking fee and whether they're open to door / ticket splits (provisional until market research), shown on their profile and when hosts pick bands
- [x] **11. Chat**: a private 1-to-1 chat opens for each accepted booking (event owner and that band or venue), starting with a note from BandLink; real-time over Laravel Reverb (new `reverb` Sail container on port 8085) with a live unread badge; Messages page with chat list and thread, "Message" buttons on confirmed bookings; read-only once the booking or event is cancelled or the event has happened; unread messages emailed once after 15 minutes, bundled; rate limited, plain text only
- [ ] **12. Polish**: calendar overlay of who is free each day, README screenshots, deployment
