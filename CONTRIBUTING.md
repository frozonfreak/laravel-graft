# Contributing to laravel-graft

Thanks for your interest in contributing! This document covers everything you need to get started.

---

## Ways to contribute

- **Bug reports** — open a GitHub issue using the Bug Report template
- **Feature requests** — open a GitHub issue using the Feature Request template
- **Pull requests** — fixes, improvements, tests, or docs
- **Documentation** — improving clarity or adding examples

---

## Getting started

### 1. Fork and clone

```bash
git clone https://github.com/YOUR_USERNAME/laravel-graft.git
cd laravel-graft
```

### 2. Install dependencies

```bash
composer install
npm install
```

### 3. Set up your environment

```bash
cp .env.example .env
php artisan key:generate
```

Add your Anthropic key if you're working on AI features:

```env
ANTHROPIC_API_KEY=sk-ant-...
```

### 4. Run migrations and seed

```bash
php artisan migrate
php artisan db:seed --class="Modules\GraftAI\Database\Seeders\GraftAIDatabaseSeeder"
```

### 5. Run tests

```bash
composer test
```

All tests must pass before submitting a PR.

---

## Pull request guidelines

- **One PR per concern** — keep changes focused
- **Write or update tests** for any new behaviour
- **Follow PSR-12** — run `vendor/bin/pint` to auto-fix style
- **Update the CHANGELOG** under `[Unreleased]` with a brief description
- Target the `main` branch

### Branch naming

| Type | Pattern | Example |
|---|---|---|
| Bug fix | `fix/short-description` | `fix/cost-model-overflow` |
| Feature | `feat/short-description` | `feat/webhook-action` |
| Docs | `docs/short-description` | `docs/dsl-examples` |
| Refactor | `refactor/short-description` | `refactor/policy-engine` |

---

## Code style

This project uses **Laravel Pint** (PSR-12 preset):

```bash
vendor/bin/pint
```

---

## Module structure

All GraftAI logic lives in `Modules/GraftAI/`. The root Laravel app is intentionally thin — it only provides the Laravel skeleton and registers the module.

When adding a new capability to the DSL:
1. Add the operator to `DslDefinition::OPERATORS` and set its weight in `OPERATOR_WEIGHTS`
2. Add its schema to `DslDefinition::schema()`
3. Add execution logic to `PipelineExecutor`
4. Add validation to `PolicyEngine::validateSchema()`
5. Add a `CapabilityRegistry` seeder entry if it should ship as a founding capability
6. Update the DSL Reference section in `Modules/GraftAI/README.md`

---

## Reporting bugs

Use the [Bug Report issue template](.github/ISSUE_TEMPLATE/bug_report.md). Include:
- PHP and Laravel version
- Steps to reproduce
- What you expected vs. what happened
- Any relevant error output or logs

---

## Code of conduct

Be respectful. Treat everyone as a collaborator, not an adversary. Discriminatory or harassing behaviour will not be tolerated.
