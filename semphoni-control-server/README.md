# Semphoni Control Server

Laravel-based control server for managing **Systems**, **Clients**, **Commands**, and **Logs**, with a Livewire UI and optional realtime updates via Laravel Reverb.

## Tech stack

- **Backend**: Laravel 12, PHP 8.4
- **Auth**: Laravel Fortify (email verification, password resets, **2FA**)
- **UI**: Livewire 4 + Flux UI (Tailwind CSS v4)
- **Realtime**: Laravel Reverb + Laravel Echo
- **Authorization**: spatie/laravel-permission
- **Local dev**: DDEV (MariaDB 10.11, nginx-fpm)

## Local development (recommended: DDEV)

### Prerequisites

- Docker + DDEV installed

### First-time setup

```bash
ddev start

ddev composer install
ddev exec npm install

cp .env.example .env
ddev artisan key:generate

# Creates baseline data + 2 seeded users (see below)
ddev artisan migrate --seed
```

### Run the app

- App: open `https://semphoni-control-server.ddev.site`
- Vite dev server (HMR):

```bash
ddev exec npm run dev
```

- Reverb: started automatically in DDEV (see `.ddev/config.yaml`)

### Seeded users

The database seeder creates two users:

- **Admin**: `admin@example.com` / `password`
- **User**: `test@example.com` / `password`

Routes are protected by `auth`, `verified`, and `2fa` middleware, so you may be prompted to complete email verification / 2FA setup on first login.

## Realtime (Reverb)

This app is set up to use Reverb from both PHP (broadcasting) and the frontend (Echo).

- **Backend**: set `BROADCAST_CONNECTION=reverb` and configure `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`.
- **Frontend (Vite)**: set `VITE_REVERB_APP_KEY`, plus host/scheme/port if you’re not using the current hostname/port.

For DDEV, Reverb is exposed on:

- **HTTPS**: port `8080`
- **HTTP**: port `8081`

Example `.env` values (adjust as needed):

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=local
REVERB_APP_KEY=local
REVERB_APP_SECRET=local

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="semphoni-control-server.ddev.site"
VITE_REVERB_SCHEME="https"
VITE_REVERB_PORT="8080"
```

## Testing

Run the test suite (Pest):

```bash
ddev exec ./vendor/bin/pest
```

Or via Laravel’s test runner:

```bash
ddev artisan test --compact
```

## Linting / formatting

Format PHP code with Pint:

```bash
ddev exec composer lint
```

## Troubleshooting

- **Flux UI install fails**: CI configures Composer auth for Flux. If `composer install` asks for credentials, add them once:

```bash
composer config http-basic.composer.fluxui.dev "<your-username>" "<your-license-key>"
```

- **Assets not updating / Vite manifest error** (`Unable to locate file in Vite manifest`):
  - Run `ddev exec npm run dev` (for HMR) or `ddev exec npm run build` (for production build).

## Non-DDEV development

If you’re not using DDEV, you can use the repository scripts:

```bash
composer run setup
composer run dev
```

