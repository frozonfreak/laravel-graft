# laravel-graft

<p>
  <a href="https://github.com/frozonfreak/laravel-graft/actions/workflows/ci.yml"><img src="https://github.com/frozonfreak/laravel-graft/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="License"></a>
  <img src="https://img.shields.io/badge/PHP-8.3%2B-blue" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/Laravel-13%2B-red" alt="Laravel 13+">
</p>

A Laravel SaaS skeleton with the **GraftAI** module — an AI-driven, self-evolving automation engine. Tenants describe what they want in plain English, the AI converts it into a live data pipeline, and the platform learns from usage to evolve its own capabilities over time.

> This repo is the full Laravel application. The GraftAI engine lives in [`Modules/GraftAI/`](Modules/GraftAI/README.md).

---

## What it does

- Tenants type a natural language prompt ("alert me when yield drops 15%") → AI (Claude) generates a structured data pipeline
- The pipeline runs on a cron schedule, queries live tenant data, and fires actions (email, SMS, webhook, push)
- Anonymized execution signals are collected over time
- A daily job detects pipeline shapes used by ≥3 tenants with ≥90% success rate → surfaces them as promotion candidates
- Governance dashboard: review, approve, and promote a candidate into a named, versioned platform capability
- The platform literally evolves itself — no new migrations, no new deployments

**Read the full module docs:** [Modules/GraftAI/README.md](Modules/GraftAI/README.md)

---

## Requirements

- PHP 8.3+
- Laravel 13+
- SQLite / MySQL / PostgreSQL
- An [Anthropic API key](https://console.anthropic.com/)
- Node.js 18+ (for Vite assets)

---

## Quick start

```bash
git clone https://github.com/frozonfreak/laravel-graft.git
cd laravel-graft

composer install
cp .env.example .env
php artisan key:generate

# Add your Anthropic key to .env:
# ANTHROPIC_API_KEY=sk-ant-...

php artisan migrate
php artisan db:seed --class="Modules\GraftAI\Database\Seeders\GraftAIDatabaseSeeder"

npm install && npm run build
```

Then start all processes in one command:

```bash
composer dev
```

This starts the HTTP server, queue worker, and Vite dev server concurrently.

Visit:
- `http://localhost:8000` — Tenant dashboard
- `http://localhost:8000/governance` — Governance dashboard

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
│   └── GraftAI/          # The self-evolving AI engine (nwidart/laravel-modules)
├── app/                  # Standard Laravel app layer
├── database/             # Root migrations and seeders
├── docs/                 # Design docs and architecture notes
└── routes/               # Root route stubs (module routes self-register)
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
