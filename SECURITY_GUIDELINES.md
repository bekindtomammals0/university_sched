Version: 1.0

Purpose: Provide concise, actionable database security best practices to be used as context for AI-assisted code generation and developer guidance. These guidelines summarize widely accepted defensive practices for designing, building, and operating secure database-backed applications. They are intended to be practical, prescriptive, and safe-by-default.

Principles (high level)

    Defense in depth: apply multiple complementary controls (network, auth, encryption, app-layer validation).
    Least privilege: grant the minimum access needed for each role, user, service, and query.
    Fail-safe defaults: when in doubt, deny access or fail the operation with a clear error.
    Auditability & traceability: log security-relevant events so actions can be reconstructed and audited.
    Immutable secrets: do not hard-code credentials or secrets into code or documentation.

Authentication & Authorization

    Require authentication for all database access. Disable anonymous/guest accounts.
    Use strong, unique credentials per application/service. Avoid shared accounts for processes that can be separated.
    Prefer identity federation (OIDC, SAML) / managed identities where supported by the platform.
    Enforce multi-factor authentication (MFA) for administrator and DBA accounts.
    Use role-based access control (RBAC) or attribute-based access control (ABAC) to separate duties:
        Admin roles (DBA) for schema and infra operations.
        App roles (read-write, read-only) for application services.
        Reporting roles with time-limited or scoped access.
    Do not run application code with DB superuser or admin privileges.

Network & Perimeter Controls

    Restrict DB access to private networks and specific application hosts using VPC/subnet rules and firewall/security groups.
    Use IP allowlists or service identity rules; avoid exposing DB ports to the public internet.
    Limit management interfaces (admin consoles) to bastion hosts or VPN access with MFA.
    Use network segmentation to isolate databases from untrusted services.

Encryption

    Enforce TLS/SSL for all client and internal DB connections; prevent use of plaintext connections.
    Require certificate validation (no permissive skipping of hostname or CA checks).
    Use strong cipher suites and rotate certificates regularly.
    Enable at-rest encryption for database storage, logs, and backups (disk-level or DB native encryption).
    Ensure encryption keys are managed by a centralized key management system (KMS) with proper rotation policies.

Query Safety & Injection Protection

    Prefer parameterized queries or prepared statements for every database access path.
    Use an ORM or safe database abstraction layer only as a convenience — still review generated SQL and ensure parameters are used.
    For hand-written queries, never build SQL via string concatenation with untrusted input.
    Apply least-privilege on SQL features (e.g., restrict EXEC/EXECUTE, dynamic SQL) and avoid enabling unsafe extensions unless required and reviewed.

Input Validation & Data Integrity

    Validate and sanitize all user-supplied data at the application boundary before any DB write:
        Use whitelists for allowed values, types, lengths, and patterns.
        Enforce domain invariants both in application logic and in the DB (constraints, CHECK, FK).
    Use database constraints (NOT NULL, UNIQUE, CHECK, FOREIGN KEY) to enforce integrity regardless of application correctness.
    Apply server-side validation even if client-side validation is present.

Secrets & Credentials Management

    Never commit secrets, credentials, or private keys to source control.
    Use a secret store or vault (HashiCorp Vault, cloud KMS/Secret Manager) for DB credentials; rotate credentials periodically and on incident.
    Use short-lived credentials or ephemeral tokens for services when supported.
    Ensure CI/CD pipelines fetch secrets securely at runtime; do not bake secrets into images.

Backups & Recovery

    Implement automated, regular backups (full + incremental) with retention policies aligned to business needs and compliance.
    Encrypt backups at rest and in transit.
    Store backups in an isolated, access-controlled location.
    Regularly test restore procedures (table-level and full DB restores) and document RTO/RPO.
    Version and validate backup integrity (checksums).

Monitoring, Logging & Auditing

    Log authentication attempts, role changes, schema changes, failed/suspicious queries, and data-access anomalies.
    Centralize logs (SIEM/Log aggregation) and protect log integrity and access.
    Retain audit logs per policy and ensure logs themselves are access-controlled and encrypted.
    Create alerts for suspicious patterns: brute-force auth attempts, privilege escalations, large data exports, high-frequency failed queries.

Patching & Maintenance

    Keep DB engines, drivers, and related packages up to date with security patches.
    Subscribe to vendor/security advisories and apply critical patches in a timely, tested manner.
    Maintain a maintenance window and rollout plan with backups and rollback plans.

Database Configuration & Hardening

    Disable unused DB features, ports, and sample/demo databases.
    Remove or secure default accounts and sample schemas.
    Reduce surface area: disable remote file access, disable or limit UDFs/extensions unless required.
    Limit or disable ad-hoc execution of system commands from DB functions/procedures.

Data Classification & Access Controls

    Classify data (PII, sensitive, public) and apply controls accordingly.
    Mask or redact sensitive fields (PII) when returning to non-privileged roles.
    Use column-level encryption or tokenization for especially sensitive data where required.

Migration & Schema Changes

    Apply schema changes via CI/CD with migrations (version-controlled, reversible).
    Test migrations on staging with representative data and backups.
    Review schema changes for security impact (e.g., new columns exposing PII, new privileges).

Third-Party Libraries & Supply Chain

    Vet database drivers, ORMs, and DB clients for security posture and license.
    Use dependency scanners to detect known vulnerabilities (Snyk, Dependabot, safety).
    Lock dependency versions and review transitive dependencies.

Performance vs Security Tradeoffs

    Avoid weakening security for marginal performance gains (e.g., disabling TLS).
    If network-level performance caching is required, ensure caches are secured and do not leak sensitive data.

Incident Response & Forensics

    Maintain an incident response plan specific to DB breaches:
        Steps to isolate DB, revoke credentials, preserve logs and backups.
        Communication and escalation paths, legal/compliance contacts.
    Preserve forensic evidence (immutable copies of logs/backups) before making destructive changes.
    Rotate affected credentials and perform a root-cause analysis.

Dev & Test Environment Best Practices

    Do not use production data in dev/test without masking/anonymization.
    Apply the same access controls in lower environments as in production where possible.
    Limit data retention in non-prod environments.

AI Code-Generation Specific Rules (how AI should behave)

    Never emit plaintext secrets, credentials, tokens, or private keys in generated code, examples, or sample config files.
    Generated DB client code must use parameterized queries or ORM parameter binding; any example containing interpolation must be flagged and corrected.
    If the model produces sample credentials for demo: mark them clearly as placeholders (e.g., DB_USER=example_user, DB_PASS=change_me) and include instructions to replace with secret-managed credentials.
    Generated migration or data scripts must include explicit safety checks (backups present, dry-run option) and not auto-drop/overwrite production data without explicit confirmation flags.
    Provide secure-by-default connection code snippets:
        TLS required, validate certificates, use secret retrieval APIs for credentials.
    When suggesting configuration or policies, include explicit fallback guidance (e.g., “If KMS unavailable, fail closed and require manual intervention”).
    For any generated alerting/monitoring rules, include thresholds and rationale and recommend tuning against representative traffic.
    Always include guidance on audit logging for any generated DB-accessing routine.
    When generating SQL, use parameter placeholders and include a short unit test or query example demonstrating safe usage.

Quick Checklist (for developers and AI generators)

    Authentication enabled and MFA for admins
    TLS enforced for all DB connections
    Parameterized queries / ORM used
    Least-privilege roles in place for app accounts
    Secrets in vault; not in repo
    Automated, encrypted backups with tested restores
    Monitoring & alerting on auth failures and data exfil attempts
    Regular patching plan and dependency scanning
    Audit logging for sensitive operations
    Dev/test environments use masked or synthetic data
