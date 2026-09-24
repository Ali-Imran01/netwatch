# NetWatch — Entity Relationship Diagram (v1 draft)

Relationships only — full column lists are introduced as each cluster is implemented (see [`DESIGN.md`](../DESIGN.md) § Roadmap). Only `users` and `audit_logs` exist as migrations this week; the rest is the planning draft for Weeks 2–6.

```mermaid
erDiagram
    USERS ||--o{ AUDIT_LOGS : performs

    SITES ||--o{ VLANS : has
    SITES ||--o{ DEVICES : has
    SITES ||--o{ CIRCUITS : has
    SITES ||--o{ MAINTENANCE_WINDOWS : schedules
    VLANS ||--o{ SUBNETS : has
    SUBNETS ||--o{ IP_ADDRESSES : contains
    DEVICES ||--o{ IP_ADDRESSES : assigned

    PROVIDERS ||--o{ CIRCUITS : provides

    DEVICES ||--o{ MONITORS : monitored_by
    CIRCUITS ||--o{ MONITORS : monitored_by
    MONITORS ||--o{ CHECK_RESULTS : produces
    MONITORS ||--o{ CHECK_ROLLUPS : aggregates
    MONITORS ||--o{ MAINTENANCE_WINDOWS : suppressed_by

    MONITORS ||--o{ INCIDENTS : triggers
    CIRCUITS ||--o{ INCIDENTS : affects
    INCIDENTS ||--o{ INCIDENT_EVENTS : has
    INCIDENTS }o--o{ ALERT_CHANNELS : notifies
```

## Entity clusters

- **Identity & audit** — `USERS`, `AUDIT_LOGS`
- **Inventory** — `SITES`, `VLANS`, `SUBNETS`, `IP_ADDRESSES`, `DEVICES`
- **Circuits** — `PROVIDERS`, `CIRCUITS`
- **Monitoring** — `MONITORS`, `CHECK_RESULTS`, `CHECK_ROLLUPS`, `MAINTENANCE_WINDOWS`
- **Incident management & alerting** — `INCIDENTS`, `INCIDENT_EVENTS`, `ALERT_CHANNELS`

See the full field-level table in the project plan (`NetWatch Project Plan.md` § 6) for column-level detail as each cluster is built out.
