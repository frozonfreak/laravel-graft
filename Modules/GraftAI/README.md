# GraftAI Package

**GraftAI turns your Laravel SaaS into a platform that learns from its users and builds new features for itself — automatically.**

Instead of guessing what data rules and automations your tenants need, you let them describe what they want in plain English. GraftAI converts that into a real, working data pipeline. Over time, it watches which pipelines your tenants actually use, spots the popular ones, and surfaces them as candidates to become built-in platform features — all without you writing a single migration or deploying new code.

---

## What does it actually do?

Imagine you run a farm management SaaS. Your tenants have different needs:

- *"Alert me when my crop yield drops more than 15% compared to last week"*
- *"Every morning, show me the top 5 fields with the highest moisture levels"*
- *"Notify me if any equipment hasn't logged activity in 3 days"*

Right now, building features like these means your team writes code for each request. With GraftAI:

1. **The tenant types their request in plain English** into the UI.
2. **The AI (Claude) converts it into a structured data pipeline** — a set of operations like "filter by field, group by date, compare to threshold."
3. **The tenant sees a plain-English summary** of what the pipeline will do, reviews the cost estimate, and confirms.
4. **The pipeline runs on a schedule** (e.g., every morning), processes live data from your database, and triggers actions like email/SMS/webhooks when conditions are met.
5. **GraftAI watches what gets used.** When enough different tenants independently build the same *shape* of pipeline, it flags it as a "promotion candidate."
6. **You review and approve** — one click promotes it into a first-class named capability in the platform, versioned and logged forever.

The platform gets smarter the more people use it. Features your users actually need bubble up automatically. You spend your engineering time on the things only you can build.

---

## How this helps your SaaS

| Problem | How GraftAI helps |
|---|---|
| Tenants request custom features faster than you can build them | They build their own rules in plain English — no dev needed |
| You don't know which features are worth investing in | Usage signals tell you exactly which pipelines are popular |
| New features are risky to ship without validation | Every pipeline runs in "sandbox" mode first — proven pipelines get promoted |
| Feature decisions are ad-hoc and undocumented | Every promotion goes through a governance review with an immutable audit log |
| Custom code accumulates and becomes unmaintainable | Promoted capabilities are versioned, rollback-able, and self-documenting |

---

## The big picture: a self-evolving platform

```
Tenant describes a rule in plain English
    ↓
AI generates a data pipeline (DSL config)
    ↓
Tenant reviews the plain-English summary → confirms
    ↓
Pipeline runs on schedule, processes real data, fires actions
    ↓
GraftAI records anonymized "signals" (what ran, how it performed)
    ↓
Daily: detects pipeline shapes used by ≥3 tenants with ≥90% success
    ↓
Governance review: you approve or reject the promotion
    ↓
Approved → becomes a named, versioned built-in capability
    ↓
Platform evolves. No migrations written by hand.
```

---

## Who is this for?

GraftAI is a good fit if:

- You run a **multi-tenant Laravel SaaS** where different tenants have different data rules and automation needs
- You want to let tenants configure their own logic **without giving them access to code**
- You want a **data-driven way to decide what features to build next**
- You want an **audit trail** of every automation rule and every platform change

It works best in domains with structured, queryable tenant data — farm management, logistics, fleet tracking, field services, IoT, or any operations-heavy SaaS.

---

## Architecture Overview

```
Tenant Prompt
    │
    ▼
AI Spec Generator ──────► Stage 1 Policy Engine
    │                           │
    │                           ▼
    │                     Semantic Summary
    │                      (User confirms)
    │
    ▼
FeatureConfig (lifecycle: sandbox)
    │
    ▼
DispatchScheduledFeatures (every 1 min)
    │
    ▼
ExecuteFeature (queued job)
    │  ├─ Stage 2 Policy (at execution time)
    │  ├─ DataSourceLoader
    │  ├─ PipelineExecutor
    │  ├─ ActionDispatcher
    │  └─ SignalEmitter
    │
    ▼
ExecutionSignal (anonymized)
    │
    ▼
DetectPatterns (daily 02:00)
    │
    ▼
PromotionCandidate ──► Governance Review
                            │
                       approve / reject / revert
                            │
                            ▼
                     PromotionPipeline.promote()
                            │
                      CapabilityRegistry (new entry)
                      EvolutionEvent (immutable log)
                      DSL minor version bump
```

---

## Module Structure

```
Modules/GraftAI/
├── app/
│   ├── Console/Commands/
│   │   ├── DetectPatterns.php          # evolution:detect-patterns
│   │   └── DispatchScheduledFeatures.php  # features:dispatch-scheduled
│   ├── Dsl/
│   │   ├── CostModel.php               # Score = Σ(weight × rows × window_multiplier)
│   │   ├── DslDefinition.php           # Canonical operator list, constants, schemas
│   │   ├── PipelineExecutor.php        # Deterministic execution over Laravel Collections
│   │   ├── PipelineSignature.php       # SHA256 shape hash (strips field values)
│   │   └── PolicyEngine.php            # Two-stage validation (creation + execution)
│   ├── Http/Controllers/
│   │   ├── Api/
│   │   │   ├── FeatureController.php   # AI → Confirm → Save flow
│   │   │   ├── GovernanceController.php
│   │   │   ├── SignalController.php
│   │   │   └── SnapshotController.php
│   │   ├── GovernanceDemoController.php # Governance dashboard (web UI)
│   │   └── TenantDemoController.php    # Tenant feature UI (web)
│   ├── Jobs/
│   │   └── ExecuteFeature.php          # Queued pipeline executor
│   ├── Models/
│   │   ├── AuditEvent.php              # Immutable — no updates/deletes
│   │   ├── CapabilityRegistry.php      # Append-only
│   │   ├── EvolutionEvent.php          # Immutable
│   │   ├── ExecutionSignal.php
│   │   ├── FeatureConfig.php
│   │   ├── FeatureExecution.php
│   │   ├── FeatureSnapshot.php
│   │   ├── PromotionCandidate.php
│   │   ├── Tenant.php
│   │   └── TenantBudget.php
│   ├── Providers/
│   │   ├── GraftAIServiceProvider.php  # Main provider — commands, schedules, bindings
│   │   ├── EventServiceProvider.php
│   │   └── RouteServiceProvider.php
│   └── Services/
│       ├── ActionDispatcher.php        # sms / email / push / in_app / webhook
│       ├── AiSpecGenerator.php         # NL → DSL via Claude API
│       ├── DataSourceLoader.php        # Tenant-scoped data loading
│       ├── PromotionPipeline.php       # Promote + rollback DSL capabilities
│       ├── SemanticValidator.php       # DSL → human-readable summary
│       └── SignalEmitter.php           # Anonymized execution signals
├── config/
│   └── config.php
├── database/
│   ├── migrations/                     # 10 migration files (timestamped 2026_03_30)
│   └── seeders/
│       ├── CapabilityRegistrySeeder.php  # DSL 1.0 founding capabilities
│       ├── DemoSeeder.php              # 5 tenants, features, signals, 2 candidates
│       └── GraftAIDatabaseSeeder.php   # Module entrypoint seeder
├── resources/
│   └── views/
│       ├── governance/index.blade.php  # Governance dashboard
│       ├── layouts/app.blade.php       # Shared layout
│       └── tenant/index.blade.php      # Tenant AI rules UI
├── routes/
│   ├── api.php                         # 17 API routes (prefix: /api)
│   └── web.php                         # 3 web routes
├── composer.json
├── module.json
└── README.md
```

---

## DSL Reference

The pipeline language GraftAI uses under the hood. The AI generates these — you don't write them manually — but understanding them helps when reviewing promoted capabilities.

### Operators

| Operator     | Weight | What it does                                      |
|--------------|--------|---------------------------------------------------|
| `filter`     | 1      | Keep only rows matching a condition (eq, gt, in…) |
| `sort`       | 1      | Order results and optionally limit to top N       |
| `group_by`   | 2      | Group rows by a field (supports date truncation)  |
| `compare`    | 2      | Check if a value crosses a threshold (terminal)   |
| `aggregate`  | 3      | sum / avg / min / max / count / median            |
| `moving_avg` | 5      | N-day or N-week sliding window average            |

### Cost Tiers

Each pipeline gets a cost score before it runs. Higher scores mean more compute.

| Score  | Tier     | Effect                                          |
|--------|----------|-------------------------------------------------|
| 0–20   | low      | Auto-approved                                   |
| 21–60  | medium   | Auto-approved, logged                           |
| 61–150 | high     | Tenant must explicitly acknowledge the cost     |
| 151+   | rejected | Pipeline is rejected before it can be saved     |

### Promotion Thresholds

A pipeline shape becomes a promotion candidate when all four conditions are met:

- **≥ 3** distinct opted-in tenants using the same shape
- **≥ 200** weighted execution score (Σ min(per_feature_count, 500))
- **≥ 5** distinct features with that shape
- **≥ 90%** success rate

---

## Installation

### 1. Requirements

- PHP 8.3+
- Laravel 13+
- A database supported by Laravel (SQLite, MySQL, PostgreSQL)
- Anthropic API key (for AI spec generation)

### 2. Install the package

```bash
composer require nwidart/laravel-modules
```

If prompted about `wikimedia/composer-merge-plugin`:

```bash
composer config allow-plugins.wikimedia/composer-merge-plugin true
composer require nwidart/laravel-modules
```

### 3. Publish vendor assets

```bash
php artisan vendor:publish --provider="Nwidart\Modules\LaravelModulesServiceProvider"
```

### 4. Add merge-plugin to `composer.json`

In the `extra` section of your root `composer.json`:

```json
"extra": {
    "laravel": {
        "dont-discover": []
    },
    "merge-plugin": {
        "include": [
            "Modules/*/composer.json"
        ]
    }
}
```

Then regenerate autoloads:

```bash
composer dump-autoload
```

### 5. Place the module

Copy or clone the `GraftAI` directory into your project's `Modules/` folder:

```
your-laravel-app/
└── Modules/
    └── GraftAI/
```

The module is enabled by default. Verify with:

```bash
php artisan module:list
```

### 6. Configure environment

Add to your `.env`:

```env
ANTHROPIC_API_KEY=sk-ant-...
```

The module reads this via `config('services.anthropic.key')`. Ensure `config/services.php` has:

```php
'anthropic' => [
    'key' => env('ANTHROPIC_API_KEY'),
],
```

### 7. Run migrations

```bash
php artisan migrate
```

### 8. Seed demo data (optional)

Seeds 5 demo tenants, feature configs, execution history, signals, and 2 promotion candidates:

```bash
php artisan db:seed --class="GraftAI\Database\Seeders\GraftAIDatabaseSeeder"
```

Or for a fresh install:

```bash
php artisan migrate:fresh --seed
# The root DatabaseSeeder must call GraftAIDatabaseSeeder, or seed directly:
php artisan db:seed --class="GraftAI\Database\Seeders\GraftAIDatabaseSeeder"
```

### 9. Start the scheduler and queue worker

```bash
# Scheduler (must run every minute via cron or artisan schedule:work)
php artisan schedule:work

# Queue worker for pipeline execution jobs
php artisan queue:listen --tries=1
```

For production, add this to your system crontab:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Web UI

| URL           | Description                                             |
|---------------|---------------------------------------------------------|
| `/`           | Tenant dashboard — AI rule builder + feature list       |
| `/governance` | Governance dashboard — candidates, evolution log, capabilities |

### Tenant Dashboard

- Select a tenant from the left sidebar
- Type a natural language prompt → AI generates DSL config
- Review semantic summary + cost tier → confirm → save
- View feature list with execution sparkbars and expandable details
- Archive features

### Governance Dashboard

- Review promotion candidates with score breakdown and example pipelines
- Approve / Reject / Revert to pending
- Promote approved candidates (creates new `CapabilityRegistry` entry + bumps DSL version)
- Roll back a promoted candidate (deprecates capability, reverts promoted features to sandbox)
- View immutable Evolution Log

---

## API Reference

All API routes are under the `/api` prefix. Tenant-facing routes require an `X-Tenant-ID` header.

### Feature Lifecycle

| Method   | Endpoint                     | Description                            |
|----------|------------------------------|----------------------------------------|
| POST     | `/api/features/generate`     | Generate DSL config from NL prompt     |
| POST     | `/api/features`              | Save confirmed feature                 |
| GET      | `/api/features`              | List tenant features                   |
| GET      | `/api/features/{id}`         | Get single feature                     |
| DELETE   | `/api/features/{id}`         | Archive feature                        |
| POST     | `/api/features/{id}/rollback`| Rollback to snapshot version           |

### Snapshots

| Method | Endpoint              | Description              |
|--------|-----------------------|--------------------------|
| POST   | `/api/snapshots`      | Create tenant snapshot   |
| GET    | `/api/snapshots`      | List tenant snapshots    |
| GET    | `/api/snapshots/{id}` | Get snapshot             |

### Signals

| Method | Endpoint                       | Description               |
|--------|--------------------------------|---------------------------|
| POST   | `/api/signals/{id}/feedback`   | Submit useful/not_useful  |

### Governance

| Method | Endpoint                                    | Description                          |
|--------|---------------------------------------------|--------------------------------------|
| GET    | `/api/governance/candidates`                | List candidates (filter by `?status=`) |
| POST   | `/api/governance/candidates/{id}/approve`   | Approve                              |
| POST   | `/api/governance/candidates/{id}/reject`    | Reject                               |
| POST   | `/api/governance/candidates/{id}/revert`    | Revert approved/rejected → pending   |
| POST   | `/api/governance/candidates/{id}/promote`   | Promote (requires `operator_name`)   |
| POST   | `/api/governance/candidates/{id}/rollback`  | Roll back promoted candidate         |
| GET    | `/api/governance/capabilities`              | List capability registry             |
| POST   | `/api/governance/capabilities/{id}/rollback`| Deprecate a capability               |
| GET    | `/api/governance/evolution-log`             | Paginated evolution history          |

---

## Artisan Commands

| Command                         | Schedule        | Description                                    |
|---------------------------------|-----------------|------------------------------------------------|
| `features:dispatch-scheduled`   | Every minute    | Dispatches `ExecuteFeature` jobs for due crons |
| `evolution:detect-patterns`     | Daily at 02:00  | Detects promotion candidates from signals      |

Both are registered via `GraftAIServiceProvider::configureSchedules()` — no `routes/console.php` entries needed.

---

## Security Model

| Concern                  | Mechanism                                                     |
|--------------------------|---------------------------------------------------------------|
| AI output trust          | Treated as untrusted input; re-validated by PolicyEngine      |
| Field injection          | Only allowlisted fields per capability can be referenced      |
| Tenant isolation         | `tenant_id` from execution context; never from user config    |
| Cost control             | Stage 1 cost scoring; Stage 2 budget halt check               |
| Concurrent execution cap | Max 5 concurrent executions per tenant (Stage 2)              |
| Immutable audit trail    | `AuditEvent` + `EvolutionEvent` throw on update/delete        |
| Capability append-only   | `CapabilityRegistry` throws on delete                         |

---

## Disabling / Enabling the Module

```bash
php artisan module:disable GraftAI
php artisan module:enable GraftAI
```

Status is stored in `modules_statuses.json` at the project root.
