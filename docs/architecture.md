# AI-Driven Evolving System — Production Design

> **Scope:** Full production-grade specification. Build this after the
> PoC has validated the core evolution loop end-to-end.
> See companion document: `ai-evolving-system-poc.md`
>
> **Framing:** This is not a tenant customization tool. It is a system
> that *learns from usage and evolves itself* — safely, incrementally,
> and with full governance. The tenant sandbox is the nursery. The
> evolution engine is the point.

---

## 1. Core Philosophy

### What This Is

A farm SaaS platform that **continuously evolves its own capabilities**
by observing how tenants use AI-generated configurations, detecting
emerging patterns, and promoting proven behavior into the core platform —
without manual feature development for every new need.

### The Fundamental Loop

```
Tenant need → AI Config → Sandbox Execution → Usage Signals
     ↑                                               │
     │                                               ▼
Core Platform ← Promotion ← Governance ← Pattern Detection
```

The system does not stay the same. It grows. Every successful sandbox
feature is a candidate to become a permanent system capability. The
platform you ship in month 1 is meaningfully different from the platform
in month 6 — not because developers wrote more code, but because the
system learned what tenants actually need.

### What Distinguishes This From a Tenant Extension System

| Tenant Extension System | AI-Driven Evolving System |
|---|---|
| Sandbox is the end state | Sandbox is a nursery |
| Features stay per-tenant | Patterns become platform capabilities |
| DSL is fixed | DSL grows as operators are promoted |
| AI generates configs | AI generates configs *and* informs evolution |
| Capabilities are predefined | Capabilities expand through governance |
| Platform is static | Platform is a living artifact |

---

## 2. System Architecture

### 2.1 Layers

```
┌───────────────────────────────────────────────────┐
│                  Core Platform                    │
│        (Auth, Billing, Base Models, API)          │
│                                                   │
│  ┌─────────────────────────────────────────────┐  │
│  │           Capability Registry               │  │
│  │   (versioned, append-only, evolved by       │  │
│  │    promoted patterns)                       │  │
│  └─────────────────────────────────────────────┘  │
└──────────────────────┬────────────────────────────┘
                       │  hard boundary
┌──────────────────────▼────────────────────────────┐
│              Evolution Engine                     │
│                                                   │
│   Pattern Detector → Candidate Queue →            │
│   Governance Review → Promotion Pipeline          │
└──────────────────────┬────────────────────────────┘
                       │
┌──────────────────────▼────────────────────────────┐
│            Tenant Sandbox Layer                   │
│                                                   │
│   AI Spec Generator → Policy Engine →             │
│   Execution Engine → Signal Emitter               │
└───────────────────────────────────────────────────┘
```

### 2.2 Components

| Component | Layer | Responsibility |
|---|---|---|
| **AI Spec Generator** | Sandbox | Prompt → DSL config |
| **Semantic Validator** | Sandbox | Re-reads config, confirms with user |
| **Policy Engine** | Sandbox | Two-stage validation (creation + runtime) |
| **Execution Engine** | Sandbox | Deterministic DSL execution via Celery |
| **Signal Emitter** | Sandbox | Emits structured usage signals after each execution |
| **Pattern Detector** | Evolution | Aggregates signals, identifies promotion candidates |
| **Candidate Queue** | Evolution | Holds patterns awaiting governance review |
| **Governance Engine** | Evolution | Approval workflow; auto or human depending on risk |
| **Promotion Pipeline** | Evolution | Moves approved patterns into Core |
| **Capability Registry** | Core | Versioned, append-only record of all system capabilities |
| **DSL Versioner** | Core | Manages operator versions; ensures backward compatibility |
| **Migration Engine** | Core | Updates existing tenant configs when DSL evolves |
| **Feature Registry** | Sandbox | Tracks all sandbox features and their lifecycle stage |
| **Snapshot & Rollback** | All | System-level and tenant-level restore |
| **Audit Log** | All | Immutable event log across all layers |

---

## 3. The Evolution Loop in Detail

### 3.1 Stage 1 — Signal Collection

Every sandbox execution emits a structured signal:

```json
{
  "signal_id": "sig_001",
  "feature_id": "feat_123",
  "tenant_id": "T1",
  "pipeline_signature": {
    "ops": ["filter", "moving_avg", "compare"],
    "data_source": "crop_prices",
    "window": "7d",
    "compare_type": "percent_drop"
  },
  "execution_outcome": "success",
  "action_triggered": true,
  "execution_ms": 412,
  "rows_scanned": 365,
  "user_feedback": "useful",
  "emitted_at": "2026-03-28T08:00:00Z"
}
```

Signals are anonymized before leaving the sandbox layer. The pattern
detector never sees `tenant_id` — only `pipeline_signature` and outcome metrics.

`pipeline_signature` is a canonical hash of the config shape (not content).
Two tenants requesting "7-day moving average price drop alert on any crop"
produce the same signature regardless of which crop they filtered on.

### 3.2 Stage 2 — Pattern Detection

The Pattern Detector runs on a scheduled job (daily). It queries the
signal store for recurring signatures across opted-in tenants.

**Signal counting — capped per feature, not per tenant**

Execution signals are counted per `feature_id`, not per `tenant_id`.
A single feature that runs 500 times ranks higher than 10 features that
run 5 times each — this directly measures which specific automation has
the strongest real-world need, and is therefore the better candidate to
become a core capability.

Per-feature cap: **500 executions** counted toward the aggregate.
Beyond that the feature is still tracked, but doesn't inflate the score
further — this prevents a single high-frequency scheduled job from
crowding out genuinely popular patterns used by many tenants.

This also gives you a natural ranking of promotion candidates by demand:

```
SELECT pipeline_signature,
       COUNT(DISTINCT feature_id)         AS distinct_features,
       SUM(LEAST(feature_exec_count, 500)) AS weighted_exec_score,
       AVG(success_rate)                  AS avg_success_rate
FROM   execution_signal_summary
WHERE  opted_in = true
GROUP  BY pipeline_signature
ORDER  BY weighted_exec_score DESC;
```

**Promotion triggers:**

| Signal | Threshold | Notes |
|---|---|---|
| Distinct opted-in tenants using same pipeline shape | ≥ 3 | Ensures the pattern is cross-tenant, not one tenant's edge case |
| Weighted execution score (sum of per-feature capped counts) | ≥ 200 | High score = high real-world demand |
| Distinct features with this pipeline shape | ≥ 5 | Separate tenants created it independently — strong signal |
| Success rate of this pattern | ≥ 90% | Pattern must be reliable, not just popular |
| User feedback score | ≥ 4.0 / 5.0 (where collected) | Optional; breaks ties between candidates |

A pattern meeting all thresholds becomes a **Promotion Candidate**.
The candidate queue is ordered by `weighted_exec_score DESC` so reviewers
always see the highest-demand patterns first.

### 3.3 Stage 3 — Governance Review

Every candidate enters the Candidate Queue with a computed risk tier:

| Candidate type | Risk | Review |
|---|---|---|
| New shortcut for existing operators | Low | Auto-approved |
| New operator composed of existing primitives | Medium | Dev review (48h SLA) |
| New data source requirement | High | Dev review + schema work |
| New external integration | High | Dev review + security audit |
| Change to existing operator behavior | Critical | Dev review + migration plan |

Dev review is done in an internal dashboard, not a manual process. The
reviewer sees: the pattern signature, example tenant configs (anonymized),
success metrics, and the proposed core implementation.

### 3.4 Stage 4 — Promotion

Approved candidates are promoted via the Promotion Pipeline:

```
1. Write new operator / shortcut / capability to DSL spec
2. Increment DSL minor version (e.g., 1.0 → 1.1)
3. Register new capability in Capability Registry
4. Run Migration Engine: update sandbox configs using old pattern
   to reference new core implementation
5. Notify opted-in tenants: "A feature you've been using is now
   a built-in capability."
6. Mark sandbox features that were promoted as status=promoted
7. Original sandbox configs remain executable (no forced migration)
```

### 3.5 Stage 5 — System Memory

The system maintains a **Evolution Log** — a versioned record of every
capability the platform has ever had, when it was added, what pattern
triggered it, and which tenants contributed to it.

```json
{
  "evolution_event_id": "evol_001",
  "type": "operator_promoted",
  "operator_name": "price_drop_alert",
  "promoted_from_pattern": "sig_hash_abc123",
  "dsl_version_before": "1.0",
  "dsl_version_after": "1.1",
  "contributing_tenant_count": 5,
  "promoted_at": "2026-04-15T00:00:00Z",
  "promoted_by": "dev_review_12"
}
```

This is how the system knows what it has learned. It is also the basis
for un-evolution (rollback at system level — see section 11).

---

## 4. DSL — Versioned and Evolvable

### 4.1 DSL Versioning Strategy

The DSL is a living specification. It uses semantic versioning:

- **Minor bump (1.0 → 1.1):** New operator or shortcut added. Fully backward compatible.
- **Major bump (1.x → 2.0):** Operator behavior changed or removed. Requires migration.

Every feature config carries `dsl_version`. The execution engine resolves
the correct operator implementations by version. A config written against
DSL 1.0 executes identically after DSL 1.2 is released.

```json
{
  "dsl_version": "1.1",
  "feature_version": 3
}
```

### 4.2 Core Operator Set (DSL 1.0)

These are the founding operators. All sandbox features in v1 compose from these.

#### `filter`
| Field | Type | Required | Notes |
|---|---|---|---|
| `field` | string | ✅ | Must be in capability allowlist |
| `op_type` | enum | ✅ | `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `in`, `not_in` |
| `value` | scalar \| array | ✅ | Always parameterized, never interpolated |

Edge cases: `in`/`not_in` with empty array → rejected. Max array length: 50.

---

#### `group_by`
| Field | Type | Required | Notes |
|---|---|---|---|
| `field` | string | ✅ | |
| `truncate` | enum | ❌ | `day`, `week`, `month` — time fields only |

Must precede `aggregate` or `moving_avg`. Cannot be terminal operator.

---

#### `aggregate`
| Field | Type | Required | Notes |
|---|---|---|---|
| `metric` | string | ✅ | Numeric field in allowlist |
| `function` | enum | ✅ | `sum`, `avg`, `min`, `max`, `count`, `median` |
| `alias` | string | ❌ | Output column name |

---

#### `moving_avg`
| Field | Type | Required | Notes |
|---|---|---|---|
| `metric` | string | ✅ | Numeric field or prior alias |
| `window` | string | ✅ | `Nd` or `Nw`. Max: `90d` |
| `min_periods` | int | ❌ | Default: 1. Null emitted if below threshold |

Sparse data: fewer points than `min_periods` → null for that row, not error.
`group_by` → `moving_avg` computes per group independently (confirmed to user in semantic validation).

---

#### `compare`
| Field | Type | Required | Notes |
|---|---|---|---|
| `type` | enum | ✅ | `percent_drop`, `percent_rise`, `absolute_drop`, `absolute_rise`, `threshold_cross` |
| `threshold` | float | ✅ | |
| `baseline` | enum | ❌ | `previous_window` (default), `rolling_mean`, `fixed_value` |
| `fixed_value` | float | ❌ | Required if `baseline: fixed_value` |

Must be terminal operator. Null or zero baseline → null result, no alert.

---

#### `sort`
| Field | Type | Required | Notes |
|---|---|---|---|
| `field` | string | ✅ | |
| `direction` | enum | ✅ | `asc`, `desc` |
| `limit` | int | ❌ | Max output rows. Hard cap: 1000 |

---

### 4.3 Pipeline Rules

1. Operators execute in array order.
2. `compare` must be terminal if present.
3. `group_by` must precede `aggregate` or `moving_avg`.
4. Max pipeline length: 8 operators.
5. Minimum: 1 operator.
6. All field names validated against capability allowlist at Stage 1.

### 4.4 Failure Semantics

| Failure | Behavior |
|---|---|
| Prior step emits null | Pass null through; continue |
| Data source returns 0 rows | Empty result; no action triggered |
| Invalid field at runtime | Fail-fast; log failure record |
| Execution time exceeded | Hard kill; status → `degraded` |
| 3 consecutive failures | Auto-suspend; tenant notified |
| Re-enable after suspend | Requires user acknowledgment |

---

## 5. Feature Config Schema

```json
{
  "feature_id": "feat_123",
  "tenant_id": "T1",
  "dsl_version": "1.0",
  "feature_version": 3,
  "lifecycle_stage": "sandbox",
  "type": "alert",
  "data_source": "crop_prices",
  "pipeline": [
    { "op": "filter", "field": "crop", "op_type": "eq", "value": "clove" },
    { "op": "group_by", "field": "date", "truncate": "day" },
    { "op": "moving_avg", "metric": "modal_price", "window": "7d", "min_periods": 3 },
    { "op": "compare", "type": "percent_drop", "threshold": 10, "baseline": "previous_window" }
  ],
  "action": {
    "type": "notification",
    "channel": "sms",
    "recipients": "tenant_owner"
  },
  "schedule": {
    "type": "cron",
    "expression": "0 8 * * *",
    "timezone": "Asia/Kolkata"
  },
  "status": "active",
  "trust_tier": 2,
  "cost_estimate": {
    "score": 42,
    "tier": "medium",
    "estimated_rows": 365,
    "computed_at": "2026-03-28T00:00:00Z"
  },
  "evolution": {
    "contributes_to_pattern_detection": true,
    "pipeline_signature": "sha256:abc123...",
    "promoted_to_core": false
  },
  "created_by": "ai",
  "created_at": "2026-03-28T00:00:00Z",
  "last_executed_at": "2026-03-28T08:00:00Z"
}
```

---

## 6. Capability Registry

The registry is the living record of everything the system can do.
It is **append-only** and **versioned**. Nothing is ever deleted from it —
deprecated capabilities are marked `status: deprecated` and remain
executable for existing configs.

```json
{
  "capability_id": "cap_001",
  "name": "crop_prices",
  "ops": ["read", "aggregate"],
  "fields": [
    "crop", "date", "market",
    "modal_price", "min_price", "max_price", "arrivals_qty"
  ],
  "introduced_in_dsl": "1.0",
  "introduced_by": "core",
  "status": "active"
}
```

```json
{
  "capability_id": "cap_007",
  "name": "price_drop_alert",
  "ops": ["shortcut"],
  "introduced_in_dsl": "1.1",
  "introduced_by": "promotion:evol_001",
  "description": "Promoted from sandbox pattern: 7d moving avg + percent_drop compare on crop_prices",
  "status": "active"
}
```

When a pattern is promoted, a new capability record is written.
The DSL minor version increments. Existing sandbox features that used
the underlying operators continue working unchanged.

---

## 7. Tenant Participation Model

### 7.1 Opt-In to Evolution

Participating in the evolution loop requires explicit opt-in. Default is off.

```json
{
  "tenant_id": "T1",
  "evolution_settings": {
    "contribute_to_pattern_detection": false,
    "receive_promoted_feature_notifications": true,
    "auto_migrate_promoted_configs": false
  }
}
```

- `contribute_to_pattern_detection`: Anonymized pipeline signatures included in the pattern detector's dataset.
- `receive_promoted_feature_notifications`: Notified when a pattern they used gets promoted to core.
- `auto_migrate_promoted_configs`: If a sandbox feature is promoted to core, automatically update their config to reference the core implementation. Default off — tenant reviews migration manually.

### 7.2 What Is Never Shared

Even for opted-in tenants:
- Tenant data is never shared.
- Config field *values* (e.g., the crop name, the threshold number) are never shared.
- Only the pipeline *shape* (operator sequence + data source category) contributes to pattern detection.

---

## 8. Two-Stage Policy Engine

### Stage 1 — Creation Time

Runs synchronously before a feature is saved.

1. DSL schema valid for declared `dsl_version`
2. `data_source` in tenant's granted capabilities
3. All `field` references in capability allowlist
4. Pipeline ordering rules satisfied
5. All enums are known values for this `dsl_version`
6. `schedule.expression` is valid cron (if present)
7. Cost score computed and within tenant budget
8. Trust tier assigned; Tier 3 blocks save pending approval
9. `pipeline_signature` computed and stored

### Stage 2 — Execution Time

Runs inside Celery worker before and during execution.

1. Tenant active (not suspended, not in arrears)
2. Feature status still `active`
3. `tenant_id` injected from execution context — never from config
4. Row count pre-check; abort if estimate exceeds limit
5. Execution time watchdog
6. Result size cap at 1000 rows
7. Action channel re-validated against tenant's current allowlist
8. Signal emitted to evolution pipeline on completion

---

## 9. AI → Confirm → Save Flow

```
User submits natural language prompt
          │
          ▼
  AI Spec Generator
  (constrained system prompt, DSL-only output)
          │
          ▼
  DSL Syntactic Parser
  (validates JSON, enums, field names against current DSL version)
          │
     ┌────┴────┐
   invalid    valid
     │          │
   error      Semantic Validator
              (re-reads config; produces plain-language summary)
                    │
                    ▼
             User Confirmation
             "Every day at 8am: filter clove prices →
              compute 7-day moving average →
              alert via SMS if price drops >10%
              vs prior window."
                    │
             ┌──────┴──────┐
           reject         confirm
             │               │
          discard       Stage 1 Policy Engine
                              │
                       ┌──────┴──────┐
                      fail          pass
                       │               │
                  show error      Trust Tier
                                    │
                         ┌──────────┴──────────┐
                       Tier 1/2            Tier 3
                         │                    │
                    Save + Schedule      Pending approval
                    Emit to evolution    Evolution paused
                    pipeline             until approved
```

### AI Spec Generator System Prompt (Security-Hardened)

```python
SYSTEM_PROMPT = """
You are a configuration generator for a farm analytics platform.
Convert the user's request into a JSON pipeline config.

Rules:
- Return ONLY valid JSON. No explanation. No markdown fences.
- Use only operators from this list: {operator_list_for_dsl_version}
- Use only these data sources: {tenant_capability_list}
- Use only these fields per data source: {field_allowlist_json}
- filter.value must be a literal string or number only.
  Never a formula, expression, or field reference.
- If the request cannot be expressed with available operators, return:
  {"error": "unsupported_request", "reason": "<brief reason>"}
- If the request would require cross-tenant data, return:
  {"error": "scope_violation", "reason": "cross-tenant access not permitted"}
"""
```

AI output is treated as **untrusted input** until it passes the syntactic
parser and Stage 1 policy engine.

---

## 10. Cost Model

### Formula

```
cost_score = Σ(operator_weight × row_estimate × window_multiplier)

row_estimate    = COUNT(*) with tenant_id + filter predicates applied
window_multiplier = window_days / 7  (moving_avg only; 1.0 otherwise)
```

### Operator Weights

| Operator | Weight |
|---|---|
| `filter` | 1 |
| `sort` | 1 |
| `group_by` | 2 |
| `aggregate` | 3 |
| `moving_avg` | 5 |
| `compare` | 2 |

### Thresholds

| Score | Tier | Action |
|---|---|---|
| 0–20 | low | Auto-approve |
| 21–60 | medium | Auto-approve + log |
| 61–150 | high | User cost acknowledgment required |
| 151+ | rejected | Block; suggest simplification |

### Tenant Budget

- `monthly_execution_budget`: default 5,000 score-points/month
- Tracked per tenant in `TenantBudget` model
- 80% consumed → warning; 100% → halt until next billing cycle
- Cost estimate recomputed when data volume grows >2× original estimate

---

## 11. Rollback — Two Levels

### 11.1 Tenant-Level Rollback

| Level | Action | Trigger |
|---|---|---|
| Feature rollback | Revert one feature to prior `feature_version` | Config regression |
| Tenant safe mode | Disable all sandbox features for tenant | Error storm, runaway budget |
| Snapshot restore | Restore all tenant features to prior snapshot | Major misconfiguration |

### 11.2 System-Level Rollback (Un-Evolution)

When a promoted operator or capability causes regressions at the platform level:

```
1. Mark capability status = deprecated in Capability Registry
2. Decrement DSL minor version (or issue patch: 1.1 → 1.1.1)
3. Migration Engine: rewrite configs referencing deprecated capability
   back to the underlying operator pipeline they came from
4. Log un-evolution event in Evolution Log
5. Notify affected tenants
6. Candidate record in Governance Engine marked: promoted_then_reverted
   (informs future promotion decisions for similar patterns)
```

System-level rollback is irreversible in the Evolution Log — it is
recorded, not erased. The system learns from regressions too.

### 11.3 Snapshot Model

```json
{
  "snapshot_id": "snap_456",
  "tenant_id": "T1",
  "snapshot_type": "tenant",
  "label": "pre-harvest-season-2026",
  "features": [],
  "dsl_version_at_snapshot": "1.1",
  "created_at": "2026-03-28T00:00:00Z",
  "created_by": "user_42"
}
```

```json
{
  "snapshot_id": "snap_sys_001",
  "snapshot_type": "system",
  "label": "pre-dsl-1.1-promotion",
  "capability_registry_state": [],
  "dsl_version": "1.0",
  "created_at": "2026-04-15T00:00:00Z",
  "created_by": "system"
}
```

System snapshots are created automatically before every DSL promotion.

---

## 12. Django Models

```python
class FeatureConfig(models.Model):
    feature_id       = models.UUIDField(primary_key=True, default=uuid.uuid4)
    tenant           = models.ForeignKey(Tenant, on_delete=models.CASCADE)
    dsl_version      = models.CharField(max_length=10, default="1.0")
    feature_version  = models.PositiveIntegerField(default=1)
    lifecycle_stage  = models.CharField(max_length=20, default="sandbox")
    # choices: sandbox | candidate | promoted | deprecated
    type             = models.CharField(max_length=50)
    data_source      = models.CharField(max_length=100)
    pipeline         = models.JSONField()
    action           = models.JSONField()
    schedule         = models.JSONField(null=True)
    status           = models.CharField(max_length=20, default="active")
    # choices: active | suspended | degraded | archived | pending_approval | promoted
    trust_tier       = models.PositiveSmallIntegerField()
    cost_estimate    = models.JSONField()
    pipeline_signature = models.CharField(max_length=64)  # sha256 of canonical shape
    contributes_to_evolution = models.BooleanField(default=False)
    promoted_to_core = models.BooleanField(default=False)
    created_by       = models.CharField(max_length=10)  # 'ai' | 'user' | 'system'
    created_at       = models.DateTimeField(auto_now_add=True)
    last_executed_at = models.DateTimeField(null=True)


class ExecutionSignal(models.Model):
    """Anonymized signal emitted after each execution. No tenant_id."""
    signal_id          = models.UUIDField(primary_key=True, default=uuid.uuid4)
    feature_id         = models.UUIDField()  # not a FK — signal layer is decoupled from sandbox
    pipeline_signature = models.CharField(max_length=64)
    dsl_version        = models.CharField(max_length=10)
    data_source        = models.CharField(max_length=100)
    execution_outcome  = models.CharField(max_length=20)  # success | failure | timeout
    action_triggered   = models.BooleanField(null=True)
    execution_ms       = models.PositiveIntegerField(null=True)
    rows_scanned       = models.PositiveIntegerField(null=True)
    user_feedback      = models.CharField(max_length=20, null=True)  # useful | not_useful
    emitted_at         = models.DateTimeField(auto_now_add=True)


class PromotionCandidate(models.Model):
    candidate_id        = models.UUIDField(primary_key=True, default=uuid.uuid4)
    pipeline_signature  = models.CharField(max_length=64, unique=True)
    distinct_tenants       = models.PositiveIntegerField()
    distinct_features      = models.PositiveIntegerField()  # features independently created with this shape
    weighted_exec_score    = models.PositiveIntegerField()  # Σ min(feature_exec_count, 500) across features
    success_rate           = models.FloatField()
    avg_feedback_score  = models.FloatField(null=True)
    risk_tier           = models.CharField(max_length=20)  # low | medium | high | critical
    status              = models.CharField(max_length=30, default="pending")
    # choices: pending | approved | rejected | promoted | promoted_then_reverted
    reviewed_by         = models.CharField(max_length=100, null=True)
    reviewed_at         = models.DateTimeField(null=True)
    promoted_at         = models.DateTimeField(null=True)
    dsl_version_after   = models.CharField(max_length=10, null=True)
    detected_at         = models.DateTimeField(auto_now_add=True)


class CapabilityRegistry(models.Model):
    capability_id     = models.UUIDField(primary_key=True, default=uuid.uuid4)
    name              = models.CharField(max_length=100, unique=True)
    ops               = models.JSONField()
    fields            = models.JSONField()
    introduced_in_dsl = models.CharField(max_length=10)
    introduced_by     = models.CharField(max_length=100)  # 'core' | 'promotion:evol_id'
    status            = models.CharField(max_length=20, default="active")
    # choices: active | deprecated
    deprecated_in_dsl = models.CharField(max_length=10, null=True)
    # Append-only: no delete endpoint

    class Meta:
        ordering = ["introduced_in_dsl"]


class EvolutionEvent(models.Model):
    """Immutable log of every system evolution event."""
    evolution_event_id       = models.UUIDField(primary_key=True, default=uuid.uuid4)
    type                     = models.CharField(max_length=50)
    # choices: operator_promoted | capability_added | capability_deprecated
    #          dsl_version_bumped | system_rollback
    operator_name            = models.CharField(max_length=100, null=True)
    promoted_from_signature  = models.CharField(max_length=64, null=True)
    dsl_version_before       = models.CharField(max_length=10)
    dsl_version_after        = models.CharField(max_length=10)
    contributing_tenant_count = models.PositiveIntegerField(null=True)
    promoted_by              = models.CharField(max_length=100)
    notes                    = models.TextField(blank=True)
    promoted_at              = models.DateTimeField(auto_now_add=True)


class FeatureExecution(models.Model):
    execution_id   = models.UUIDField(primary_key=True, default=uuid.uuid4)
    feature        = models.ForeignKey(FeatureConfig, on_delete=models.CASCADE)
    tenant         = models.ForeignKey(Tenant, on_delete=models.CASCADE)
    status         = models.CharField(max_length=20)
    started_at     = models.DateTimeField()
    completed_at   = models.DateTimeField(null=True)
    execution_ms   = models.PositiveIntegerField(null=True)
    rows_scanned   = models.PositiveIntegerField(null=True)
    cost_actual    = models.FloatField(null=True)
    error_detail   = models.JSONField(null=True)
    signal_emitted = models.BooleanField(default=False)


class FeatureSnapshot(models.Model):
    snapshot_id      = models.UUIDField(primary_key=True, default=uuid.uuid4)
    tenant           = models.ForeignKey(Tenant, null=True, on_delete=models.CASCADE)
    snapshot_type    = models.CharField(max_length=20)  # tenant | system
    label            = models.CharField(max_length=255, blank=True)
    features         = models.JSONField()
    dsl_version_at_snapshot = models.CharField(max_length=10)
    created_at       = models.DateTimeField(auto_now_add=True)
    created_by       = models.CharField(max_length=100)


class AuditEvent(models.Model):
    event_id     = models.UUIDField(primary_key=True, default=uuid.uuid4)
    tenant       = models.ForeignKey(Tenant, null=True, on_delete=models.SET_NULL)
    feature      = models.ForeignKey(FeatureConfig, null=True, on_delete=models.SET_NULL)
    event_type   = models.CharField(max_length=100)
    actor        = models.CharField(max_length=100)
    detail       = models.JSONField(default=dict)
    created_at   = models.DateTimeField(auto_now_add=True)
    # No update/delete endpoints. DB-level append-only enforcement.
```

---

## 13. Security

### 13.1 DSL Field Injection Prevention

```python
# ❌ Never
query = f"SELECT * FROM crop_prices WHERE {filter.field} = '{filter.value}'"

# ✅ Always
assert filter.field in CAPABILITY_FIELD_ALLOWLIST[data_source]  # Stage 1
queryset = CropPrice.objects.filter(**{filter.field: filter.value})
```

### 13.2 Tenant ID From Execution Context, Never Config

```python
# ❌ Never
queryset = CropPrice.objects.filter(tenant_id=feature_config["tenant_id"])

# ✅ Always
queryset = CropPrice.objects.filter(tenant_id=execution_context.tenant_id)
```

### 13.3 Signal Anonymization Before Pattern Detector

```python
def emit_signal(feature: FeatureConfig, execution: FeatureExecution):
    if not feature.contributes_to_evolution:
        return
    ExecutionSignal.objects.create(
        pipeline_signature=feature.pipeline_signature,
        # tenant_id is deliberately omitted
        dsl_version=feature.dsl_version,
        data_source=feature.data_source,
        execution_outcome=execution.status,
        execution_ms=execution.execution_ms,
        rows_scanned=execution.rows_scanned,
    )
```

### 13.4 Prompt Injection Mitigation

- System prompt lists only the tenant's granted operators and fields.
- AI output is parsed as untrusted input by the syntactic parser.
- Semantic Validator is an independent re-read — if AI generated a dangerous config, the user sees what it actually does before confirming.
- Stage 1 is the hard gate. Even a perfectly crafted injection that bypasses the semantic validator will fail at field allowlist validation.

### 13.5 Snapshot Tenant Scoping

```python
def get_snapshot(snapshot_id, tenant_id):
    return FeatureSnapshot.objects.get(
        snapshot_id=snapshot_id,
        tenant=tenant_id  # 404 if snapshot belongs to another tenant
    )
```

### 13.6 Concurrency and Rate Limits

| Limit | Value |
|---|---|
| Max concurrent executions/tenant | 5 |
| Max scheduled features/tenant | 20 |
| Max scheduled executions/feature/day | 4 |
| Celery task soft time limit | 25s |
| Celery task hard time limit | 30s |
| Max rows scanned | 100,000 |
| Max output rows | 1,000 |

### 13.7 Celery Task Idempotency

```python
task_id = f"{feature_id}:{execution_date}:{feature_version}"
execute_feature.apply_async(args=[feature_id, context], task_id=task_id)
```

### 13.8 Audit Log Immutability

`AuditEvent` has no update or delete views. Enforce at DB level:

```sql
REVOKE UPDATE, DELETE ON audit_auditEvent FROM app_user;
```

---

## 14. Observability

### Per-Feature Metrics
- `execution_duration_ms` — histogram
- `rows_scanned` — counter
- `cost_score_actual` — gauge
- `error_rate` — rolling 24h
- `action_triggered_rate` — how often alerts actually fire

### Evolution Metrics
- `patterns_detected_this_week` — gauge
- `candidates_in_queue` — gauge
- `promotions_this_quarter` — counter
- `reverted_promotions` — counter (critical signal)
- `avg_sandbox_to_promotion_days` — histogram

### Alerting

| Signal | Threshold | Action |
|---|---|---|
| Feature error rate | >30% over 1h | Flag as `degraded`, notify tenant |
| Execution P95 | >20s | Notify dev team |
| Tenant budget | >80% consumed | Warn tenant |
| Tenant budget | 100% consumed | Halt execution |
| Consecutive failures | 3 | Auto-suspend feature |
| Reverted promotions | >0 | Immediate dev alert — system regression |

---

## 15. Execution Limits Reference

| Limit | Value | Enforcement |
|---|---|---|
| Max pipeline operators | 8 | Stage 1 |
| Max moving_avg window | 90d | Stage 1 |
| Max execution time (soft) | 25s | Celery |
| Max execution time (hard) | 30s | Celery |
| Max rows scanned | 100,000 | Stage 2 pre-check |
| Max output rows | 1,000 | Post-execution cap |
| Max concurrent/tenant | 5 | Task dispatch |
| Max scheduled/tenant | 20 | Stage 1 |
| Max schedule/day/feature | 4 | Stage 1 |
| Monthly budget default | 5,000 score-pts | Stage 2 |
| Consecutive failure limit | 3 → suspend | Execution engine |

---

## 16. UX Exposure

Expose as:
- **"Smart Automations"**
- **"AI Rules"**

When a pattern is promoted to core, notify opted-in tenants:
> *"A workflow you've been running is now a built-in platform feature.
>  Your existing automation continues working. You can migrate to the
>  built-in version at any time."*

Never use: "Experimental", "Beta", "Sandbox" in user-facing copy.

User confirmation before save is **mandatory**. No feature is
activated without the user reading and confirming the semantic summary.

---

## 17. Safety — Non-Negotiable

### Never Allow
- Schema mutations from sandbox layer
- Auth or permission changes from any non-core path
- Cross-tenant data access — patterns detect shapes only
- Core logic modification from AI output
- Raw SQL or code in any DSL field
- Skipping user confirmation
- Silent evolution — every DSL change is logged and versioned

### Always Enforce
- Capability validation at Stage 1
- Tenant ID from execution context, not config
- Signal anonymization before pattern detector
- System snapshot before every DSL promotion
- Immutable audit log for all events
- Idempotent Celery task IDs
- Rollback path at both tenant and system level

---

## 18. Summary

This system is:

> A platform that learns from its tenants and evolves its own capabilities —
> safely, incrementally, and always under governance.

The sandbox is where ideas are tested.
The evolution engine is how they become permanent.
The governance layer is what keeps the system safe as it grows.

The platform you ship on day 1 is a seed. By month 6, it is a materially
different system — shaped by real usage, not roadmap assumptions.