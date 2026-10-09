# BandLink

A booking platform connecting **bands**, **venues** and **event organisers**, built with Laravel 13 and Livewire.

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
docker compose up -d                                     # app, mysql, redis, mailpit, phpmyadmin
docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate --seed
npm install && npm run dev
```

- App: http://localhost:8080
- Mailpit (caught emails): http://localhost:8025
- phpMyAdmin: http://localhost:8081 (logs in as the `sail` user; only reachable from this machine)
- MySQL from a GUI client: `127.0.0.1:3307`, user `sail`, password `password`, database `bandlink`

The seeder creates one demo account per role, all with the password `password`:
`venue@example.com`, `band@example.com`, `organiser@example.com` and `admin@example.com`.

## Everyday commands

From **Git Bash / WSL** you can use the Sail wrapper, `./vendor/bin/sail <cmd>`.
From **PowerShell** use `docker compose exec laravel.test <cmd>`. Both run inside the app container.

| Task                         | Sail                                    | PowerShell / plain Docker                                         |
|------------------------------|-----------------------------------------|-------------------------------------------------------------------|
| Start / stop containers      | `sail up -d` / `sail stop`              | `docker compose up -d` / `docker compose stop`                    |
| Run migrations               | `sail artisan migrate`                  | `docker compose exec laravel.test php artisan migrate`            |
| Roll back the last batch     | `sail artisan migrate:rollback`         | `docker compose exec laravel.test php artisan migrate:rollback`   |
| Rebuild DB from scratch      | `sail artisan migrate:fresh --seed`     | `docker compose exec laravel.test php artisan migrate:fresh --seed` |
| Migration status             | `sail artisan migrate:status`           | `docker compose exec laravel.test php artisan migrate:status`     |
| New migration                | `sail artisan make:migration create_x_table` | `docker compose exec laravel.test php artisan make:migration create_x_table` |
| Tinker                       | `sail tinker`                           | `docker compose exec laravel.test php artisan tinker`             |
| Queue worker                 | `sail artisan queue:work`               | `docker compose exec laravel.test php artisan queue:work`         |
| MySQL shell                  | `sail mysql`                            | `docker compose exec mysql mysql -u sail -ppassword bandlink`     |
| Tests                        | `sail test`                             | `docker compose exec laravel.test php artisan test`               |

Tests run against in-memory SQLite (see `phpunit.xml`), so `composer test` also works without Docker
and covers Pint, Larastan and Pest in one go.
