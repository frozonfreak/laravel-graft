# Security Policy

## Supported versions

| Version | Supported |
|---------|-----------|
| 1.x     | Yes       |

---

## Reporting a vulnerability

**Do not open a public GitHub issue for security vulnerabilities.**

Please report security issues by emailing the maintainer directly. Include:

- A description of the vulnerability
- Steps to reproduce it
- The potential impact
- Any suggested fix (optional)

You will receive a response within 72 hours. If the issue is confirmed, a patch will be released as quickly as possible, and you will be credited in the release notes (unless you prefer to remain anonymous).

---

## Security model

The GraftAI module has the following built-in security invariants:

| Concern | Mechanism |
|---|---|
| AI output trust | All AI-generated configs are treated as untrusted input and re-validated by the PolicyEngine before saving |
| Field injection | Only allowlisted fields per capability can be referenced in a pipeline |
| Tenant isolation | `tenant_id` is sourced from execution context only — never from user-supplied config |
| Cost control | Stage 1 cost scoring rejects pipelines with score ≥ 151; Stage 2 halts on budget exhaustion |
| Concurrent execution cap | Max 5 concurrent pipeline executions per tenant (enforced at Stage 2) |
| Immutable audit trail | `AuditEvent` and `EvolutionEvent` models throw exceptions on any update or delete |
| Capability append-only | `CapabilityRegistry` throws on delete — capabilities can only be deprecated, never removed |

---

## Anthropic API key

Your `ANTHROPIC_API_KEY` is used server-side only. It is never exposed to tenants or returned in any API response. Ensure it is set only in `.env` and never committed to source control — the `.gitignore` excludes `.env` by default.
