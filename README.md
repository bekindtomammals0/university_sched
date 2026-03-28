# Exam Scheduling Application

Version: 1.1
Author: Principal Software Architect — Exam Scheduling Project

## Project Overview

The **Exam Scheduling Application** is an optimized, constraint-safe scheduling system for university exams. It transforms institutional data and business rules into a deterministic scheduling pipeline. The project is currently transitioning from a Python/CLI tool to a modern web application using the **VILT stack**.

## Dual-Stack Status

- **Python/CLI (Legacy/Engine)**: Core scheduling logic and CLI-based pipeline. Located in `university_sched/`.
- **VILT Stack (Active Migration)**: Modern web interface using Vue.js, Inertia.js, Laravel, and Tailwind CSS. Located in `vilt_migration/`.

## Tech Stack

### Core Engine (Python)
- **Language:** Python 3.10+
- **Database:** SQLAlchemy 2.0 with SQLite/PostgreSQL.
- **Validation:** Pydantic.
- **Testing:** Pytest with Hypothesis.

### Web Application (VILT)
- **Framework:** Laravel 11.
- **Frontend:** Vue.js 3 + Inertia.js.
- **Styling:** Tailwind CSS.
- **Language:** PHP 8.2+.

## Data Model (Updated per schema.txt)

The system uses natural keys and descriptive identifiers:
- **Departments**: `dept_id` (e.g., 'DABE') is the primary key.
- **Courses**: `course_code` (e.g., 'ABEn 132') is the primary key. Includes `is_major` and `is_grad` flags.
- **Offerings**: `offering_no` (e.g., 'A117') is the primary key.
- **Offering Blocks**: Association table linking `offering_no`, `block_no`, and `degprog_id`.

## Getting Started

### Python Engine Setup
1. **Virtual Environment:**
   ```bash
   python -m venv .venv
   source .venv/bin/activate
   pip install -r requirements.txt
   ```
2. **Execution:**
   ```bash
   PYTHONPATH=. .venv/bin/python university_sched/cli.py --config config.yml --outdir ./reports --csv --json
   ```

### VILT Migration Setup
1. **Laravel Backend:**
   ```bash
   cd vilt_migration
   composer install
   php artisan migrate
   ```
2. **Frontend:**
   ```bash
   npm install
   npm run dev
   ```

## Architecture

- `university_sched/`: Python core, constraint engine, and CLI.
- `vilt_migration/`: Laravel-based web application.
  - `app/Services/SchedulingService.php`: Ported constraint logic.
  - `database/migrations/`: Updated schema definitions.
- `schema.txt`: Definitive SQL schema for both implementations.

## Security & Guidelines

All development follows:
- **[SECURITY_GUIDELINES.md](SECURITY_GUIDELINES.md)**: Database security and query safety.
- **[GUIDELINES.md](GUIDELINES.md)**: Multi-stack development standards.

---
This README is the single source of truth for the project's dual-stack architecture.
