# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] — 2026-03-30

### Added

- **GraftAI module** (`Modules/GraftAI`) — full nwidart/laravel-modules structure
- **AI Spec Generator** — natural language → DSL pipeline config via Claude (Haiku)
- **DSL Engine** — 6 operators: `filter`, `sort`, `group_by`, `aggregate`, `moving_avg`, `compare`
- **Two-stage Policy Engine** — Stage 1 (creation-time cost + schema validation) and Stage 2 (execution-time budget + concurrency checks)
- **Pipeline Executor** — deterministic execution over Laravel Collections
- **Cost Model** — `Score = Σ(weight × rows × window_multiplier)` with four tiers (low / medium / high / rejected)
- **Semantic Validator** — converts DSL config into a plain-English human-readable summary
- **Signal Emitter** — anonymized execution signal collection (shape hash, success/failure, cost)
- **Pattern Detector** (`evolution:detect-patterns`) — daily job that surfaces promotion candidates from signals
- **Promotion Pipeline** — promote a candidate into a versioned `CapabilityRegistry` entry; rollback support
- **Governance API + Dashboard** — approve / reject / revert / promote candidates; view evolution log
- **Tenant Dashboard** — tenant-facing UI: prompt → confirm → feature list with sparkbars
- **10 database migrations** — tenants, budgets, capabilities, features, executions, signals, candidates, events, snapshots, audit log
- **Demo seeder** — 5 tenants, feature configs, execution history, signals, 2 promotion candidates
- **Artisan commands**: `features:dispatch-scheduled` (every minute), `evolution:detect-patterns` (daily 02:00)
- **17 API routes** — features, snapshots, signals, governance
- **Immutable audit trail** — `AuditEvent` and `EvolutionEvent` throw on update/delete
- Initial open-source release: LICENSE, CHANGELOG, CONTRIBUTING, SECURITY, CI workflow
