# GraftAI Module

An AI-Driven Evolving System for farm SaaS platforms — built as a **nwidart/laravel-modules** module.

The system observes tenant usage patterns, detects recurring pipeline shapes, and promotes proven sandbox behaviors into first-class platform operators via a governance review cycle. The platform **evolves itself** without human-written migrations.

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

### Operators

| Operator     | Weight | Purpose                                   |
|--------------|--------|-------------------------------------------|
| `filter`     | 1      | Row-level predicate (eq, neq, gt, in, …)  |
| `sort`       | 1      | Order + limit result set                  |
| `group_by`   | 2      | Group rows (with optional date truncation)|
| `compare`    | 2      | Threshold trigger (terminal operator)     |
| `aggregate`  | 3      | sum / avg / min / max / count / median    |
| `moving_avg` | 5      | N-day / N-week sliding window average     |

### Cost Tiers

| Score  | Tier     | Effect                                      |
|--------|----------|---------------------------------------------|
| 0–20   | low      | Auto-approved                               |
| 21–60  | medium   | Auto-approved, logged                       |
| 61–150 | high     | Requires explicit cost acknowledgment       |
| 151+   | rejected | Pipeline rejected at Stage 1                |

### Promotion Thresholds

A pipeline shape becomes a `PromotionCandidate` when it meets all four thresholds:

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
php artisan db:seed --class="Modules\GraftAI\Database\Seeders\GraftAIDatabaseSeeder"
```

Or for a fresh install:

```bash
php artisan migrate:fresh --seed
# The root DatabaseSeeder must call GraftAIDatabaseSeeder, or seed directly:
php artisan db:seed --class="Modules\GraftAI\Database\Seeders\GraftAIDatabaseSeeder"
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
