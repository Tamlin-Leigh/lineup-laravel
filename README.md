# LineUp

A booking platform connecting **artists**, **venues** and **event organisers**, built with Laravel 13 and Livewire.

> Work in progress. See [PLAN.md](PLAN.md) for the architecture and roadmap.

## Stack

- **Laravel 13** on PHP 8.4, with Fortify auth (email verification, 2FA, password confirmation)
- **Livewire 4** + Flux UI + Tailwind
- **MySQL 8.4**, **Redis** (cache and queues) and **Mailpit**, all via Laravel Sail / Docker
- **Pest** tests, **Larastan** level 7, **Pint**, and GitHub Actions CI

## Getting started (Docker)

Requires Docker Desktop. The host ports are set in `.env` (`APP_PORT=8080`, `FORWARD_DB_PORT=3307`, `FORWARD_PHPMYADMIN_PORT=8081`).

```bash
cp .env.example .env
composer install
docker compose up -d                                     # app, queue, scheduler, reverb, mysql, redis, mailpit, phpmyadmin
docker compose exec -u sail laravel.test php artisan key:generate
docker compose exec -u sail laravel.test php artisan migrate --seed
npm install && npm run dev
```

- App: http://localhost:8080
- Mailpit (caught emails): http://localhost:8025
- Reverb (real-time chat WebSockets): `ws://localhost:8085`, used by the browser automatically
- phpMyAdmin: http://localhost:8081 (logs in as the `sail` user; only reachable from this machine)
- MySQL from a GUI client: `127.0.0.1:3307`, user `sail`, password `password`, database `lineup`

The seeder creates one demo account per role, all with the password `password`:
`venue@example.com`, `artist@example.com`, `organiser@example.com` and `admin@example.com`.
The demo artist and demo venue each have a booking request waiting on their Bookings page.

Booking emails and in-app notices are sent by the `queue` container and unanswered requests are expired by the
`scheduler` container (`php artisan bookings:expire`, every 15 minutes). The queue worker keeps the code it
started with, so after changing a notification or action run `docker compose restart queue`.

## Everyday commands

From **Git Bash / WSL** you can use the Sail wrapper, `./vendor/bin/sail <cmd>`.
From **PowerShell** use `docker compose exec -u sail laravel.test <cmd>`. Both run inside the app container as the
`sail` user, the same user the web server runs as. Leave out `-u sail` and the command runs as `root`, and any
files it writes (compiled views, logs, cache) can no longer be updated by the app.

| Task                         | Sail                                    | PowerShell / plain Docker                                         |
|------------------------------|-----------------------------------------|-------------------------------------------------------------------|
| Start / stop containers      | `sail up -d` / `sail stop`              | `docker compose up -d` / `docker compose stop`                    |
| Run migrations               | `sail artisan migrate`                  | `docker compose exec -u sail laravel.test php artisan migrate`            |
| Roll back the last batch     | `sail artisan migrate:rollback`         | `docker compose exec -u sail laravel.test php artisan migrate:rollback`   |
| Rebuild DB from scratch      | `sail artisan migrate:fresh --seed`     | `docker compose exec -u sail laravel.test php artisan migrate:fresh --seed` |
| Migration status             | `sail artisan migrate:status`           | `docker compose exec -u sail laravel.test php artisan migrate:status`     |
| New migration                | `sail artisan make:migration create_x_table` | `docker compose exec -u sail laravel.test php artisan make:migration create_x_table` |
| Tinker                       | `sail tinker`                           | `docker compose exec -u sail laravel.test php artisan tinker`             |
| Update PHP (security patch)  | `sail build --no-cache` then `sail up -d --force-recreate` | `docker compose build --no-cache` then `docker compose up -d --force-recreate` |
| Queue worker logs / restart  | `sail logs queue` / `sail restart queue` | `docker compose logs queue` / `docker compose restart queue`      |
| MySQL shell                  | `sail mysql`                            | `docker compose exec mysql mysql -u sail -ppassword lineup`     |
| Tests                        | `sail test`                             | `docker compose exec -u sail laravel.test php artisan test`               |

Tests run against in-memory SQLite (see `phpunit.xml`), so `composer test` also works without Docker
and covers Pint, Larastan and Pest in one go.

## Troubleshooting

**`touch(): Utime failed: Operation not permitted`** (or other permission errors under `storage/`): a command
was run in the container as `root`, so it left root-owned files the app can't update. Hand them back to `sail`
and clear the compiled views:

```bash
docker compose exec laravel.test chown -R sail:sail storage bootstrap/cache
docker compose exec -u sail laravel.test php artisan view:clear
```

**`SQLSTATE[HY000] [2002] ... Host: mysql`**: `php artisan` was run on Windows instead of in the container (or the
app was opened on `php artisan serve`'s port 8000). Use the container commands above and http://localhost:8080.

**Pages are slow on Windows (a second or more per click)**: Docker reads the project from the Windows drive
(`C:\...`) very slowly. `docker/php/opcache.ini` turns on PHP's code cache for the dev server and only re-checks
files every 2 seconds, which helps with quick bursts of clicks, but the first click after a pause still takes
1.5–2 seconds. The fix is to keep the project inside WSL2, as the Laravel Sail docs recommend; see
[Running from WSL (Windows)](#running-from-wsl-windows).

## Running from WSL (Windows)

On Windows, keep the project in the WSL2 Linux filesystem rather than on `C:\`: requests drop from ~0.3–2s
to well under 0.1s. You need a WSL distro (e.g. Ubuntu, `wsl -l -v` lists them) and, in Docker Desktop,
**Settings → Resources → WSL integration** switched on for it. Then, in an Ubuntu terminal:

```bash
# 1. Stop the copy on the Windows drive (its database and Redis data stay in Docker volumes).
cd "/mnt/c/Users/<you>/<path to>/lineup-laravel" && docker compose down

# 2. Clone into your Linux home and bring your .env across.
cd ~ && git clone https://github.com/Tamlin-Leigh/lineup-laravel.git && cd lineup-laravel
cp "/mnt/c/Users/<you>/<path to>/lineup-laravel/.env" .
echo "PHP_OPCACHE_REVALIDATE_FREQ=0" >> .env   # file checks are cheap here: edits show up on the next request

# 3. Install PHP packages without needing PHP in WSL, then start everything.
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
    laravelsail/php84-composer:latest composer install --ignore-platform-reqs
docker compose up -d

# 4. Bring across uploaded profile photos (not in git) and link them into public/.
cp -r "/mnt/c/Users/<you>/<path to>/lineup-laravel/storage/app/public/." storage/app/public/
docker compose exec -u sail laravel.test php artisan storage:link

# 5. Front end (needs Node in WSL, e.g. via nvm), then open the folder in VS Code.
npm install && npm run dev
code .
```

`compose.yaml` pins the project name to `lineup-laravel`, so Docker Compose reuses the same `sail-mysql` and `sail-redis`
volumes: your data, accounts and seeded demo data carry over without re-migrating. `code .` opens VS Code
through its **WSL** extension (it offers to install it); from then on, use the terminal inside VS Code, which
runs in Ubuntu. Once everything works, the copy on the Windows drive can be deleted.

**Chat messages only appear after a refresh**: the browser isn't connected to Reverb. Check the `reverb`
container is running (`docker compose ps reverb`, start it with `docker compose up -d reverb`) and that port 8085
is free. After changing any `VITE_REVERB_*` value in `.env`, restart the Vite dev server (`npm run dev`), since
Vite only reads `.env` when it starts.
