# Database Security Guidelines

Version: 1.1

## Purpose
This document provides security standards for both the **Python Engine** and the **VILT Web Application**. All development must follow these rules when using **SQLAlchemy 2.0** or **Laravel Eloquent**.

## Principles
- **Least Privilege**: Application roles must have minimal DB permissions.
- **Defense in Depth**: Use multi-layer validation (Pydantic / Laravel FormRequests).
- **Auditability**: All scheduling decisions must be logged in the `decision_log.json`.

## Core Guidelines

### 1. Query Safety
- **ORM-First**: Use SQLAlchemy Mapped types or Laravel Eloquent Models.
- **Parameterized Queries**: Never use string interpolation (`f-strings`, `.format()`, or `$sql .= ...`) with untrusted input.
- **Validation**: All user-supplied data must be sanitized before ingestion.

### 2. Authentication & Authorization
- **Secrets**: Store `DATABASE_URL` or `DB_PASSWORD` in `.env` files.
- **Managed Roles**: PostgreSQL should use separate roles for app logic (CRUD) and reporting (Read-Only).

### 3. Data Integrity & Invariants
- **DB Constraints**: Use `schema.txt` as the master for `CHECK`, `FK`, and `NOT NULL` constraints.
- **Hard Constraints**: Both `ConstraintEngine` (Python) and `SchedulingService` (PHP) must fail-fast with explicit errors.

### 4. Secrets Management
- **Environment Overrides**: Use environment variables for sensitive configuration.
- **Ignored Files**: Ensure `.env` is listed in `.gitignore`.

### 5. AI-Assisted Security
- All AI-generated code must use parameterized queries or ORM binding.
- AI must flag insecure DB practices (e.g., ad-hoc SQL without placeholders).
- AI-generated configuration must use placeholder values (e.g., `DB_PASS=REPLACE_ME`).

## Quick Checklist
- [ ] SQLAlchemy / Eloquent ORM used?
- [ ] No hard-coded credentials?
- [ ] PII masked in publicly available reports?
- [ ] `CheckConstraint` added for `room_capacity` and `num_enrolled`?
- [ ] Database backups encrypted?
- [ ] `.env` ignored by git?

---
For development standards, see **[GUIDELINES.md](GUIDELINES.md)**.
For project architecture, see **[README.md](README.md)**.
