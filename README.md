# laravel-graft

<p>
  <a href="https://github.com/frozonfreak/laravel-graft/actions/workflows/ci.yml"><img src="https://github.com/frozonfreak/laravel-graft/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="License"></a>
  <img src="https://img.shields.io/badge/PHP-8.3%2B-blue" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/Laravel-13%2B-red" alt="Laravel 13+">
</p>

A Laravel SaaS skeleton with the **GraftAI** package — an AI-driven, self-evolving automation engine. Tenants describe what they want in plain English, the AI converts it into a live data pipeline, and the platform learns from usage to evolve its own capabilities over time.

> This repo is the full Laravel demo application. The GraftAI package lives in [`Modules/GraftAI/`](Modules/GraftAI/README.md) and is loaded via a Composer path repository.

---

## What it does

- Tenants type a natural language prompt ("alert me when yield drops 15%") → AI (Claude) generates a structured data pipeline
- The pipeline runs on a cron schedule, queries live tenant data, and fires actions (email, SMS, webhook, push)
- Anonymized execution signals are collected over time
- A daily job detects pipeline shapes used by ≥3 tenants with ≥90% success rate → surfaces them as promotion candidates
- Governance dashboard: review, approve, and promote a candidate into a named, versioned platform capability
- The platform literally evolves itself — no new migrations, no new deployments

**Read the full package docs:** [Modules/GraftAI/README.md](Modules/GraftAI/README.md)

---

## Requirements

- PHP 8.3+
- Laravel 13+
- SQLite / MySQL / PostgreSQL
- An [Anthropic API key](https://console.anthropic.com/)
- Node.js 18+ (for Vite assets)

---

## Quick start

### 1. Clone and install

```bash
git clone https://github.com/frozonfreak/laravel-graft.git
cd laravel-graft

composer install
cp .env.example .env
php artisan key:generate
```

`composer install` resolves `frozonfreak/graftai` from the local path repository at `Modules/GraftAI/` and junctions it into `vendor/`. No separate clone or symlink step needed.

### 2. Configure

Add your Anthropic API key to `.env`:

```env
ANTHROPIC_API_KEY=sk-ant-...
```

### 3. Migrate and seed

```bash
php artisan migrate

# Optional: load demo tenants, features, signals, and promotion candidates
php artisan db:seed --class="GraftAI\Database\Seeders\GraftAIDatabaseSeeder"
```

### 4. Build assets and start

```bash
npm install && npm run build

composer dev   # starts HTTP server, queue worker, and Vite dev server
```

Visit:
- `http://localhost:8000` — Tenant dashboard
- `http://localhost:8000/governance` — Governance dashboard

---

## Installing the package into your own Laravel app

When `frozonfreak/graftai` is published to Packagist, installation is:

```bash
composer require frozonfreak/graftai
php artisan vendor:publish --tag=graftai-migrations
php artisan migrate
```

Until then, use the path repository approach:

**1. Copy the package into your project:**

```bash
cp -r path/to/laravel-graft/Modules/GraftAI packages/graftai
```

**2. Add a path repository to your `composer.json`:**

```json
"repositories": [
    {
        "type": "path",
        "url": "packages/graftai",
        "options": { "symlink": true }
    }
]
```

**3. Require the package:**

```bash
composer require frozonfreak/graftai
```

**4. Publish and migrate:**

```bash
php artisan vendor:publish --tag=graftai-config
php artisan vendor:publish --tag=graftai-migrations
php artisan migrate
```

**5. Add your Anthropic key to `config/services.php`:**

```php
'anthropic' => [
    'key' => env('ANTHROPIC_API_KEY'),
],
```

### Publish tags

| Tag | What it publishes |
|---|---|
| `graftai-config` | `config/graftai.php` — model, limits, promotion thresholds, queue, route settings |
| `graftai-migrations` | 10 database migrations |
| `graftai-seeders` | Demo seeders to `database/seeders/GraftAI/` |
| `graftai-views` | Blade views to `resources/views/vendor/graftai/` |

---

## Running tests

```bash
composer test
```

---

## Project structure

```
laravel-graft/
├── Modules/
│   └── GraftAI/          # frozonfreak/graftai package (path repository)
│       ├── src/           # All PHP source (namespace: GraftAI\)
│       ├── config/        # graftai.php
│       ├── database/      # Migrations and seeders
│       ├── resources/     # Blade views
│       └── routes/        # web.php and api.php
├── app/                   # Standard Laravel app layer
├── database/              # Root migrations and seeders
├── docs/                  # Design docs and architecture notes
└── routes/                # Root route stubs (package routes self-register)
```

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

---

## Security

See [SECURITY.md](SECURITY.md) for how to report vulnerabilities.

---

## License

MIT — see [LICENSE](LICENSE).
