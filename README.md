Exam Scheduling Application

Version: 1.0
Author: Principal Software Architect — Exam Scheduling Project
Project Overview

This application creates an optimized, constraint-safe exam schedule for undergraduate offerings across departments. It translates the institutional data model (ERD) and business rules into a deterministic scheduling pipeline that enforces hard constraints (capacity, student conflicts, departmental room restrictions, 5-day limit) and applies optimization heuristics (student load balancing, even distribution of major courses). Outputs are three machine- and human-friendly reports used by administrators, faculty, and room managers.

Data Model Overview (ERD Summary)
The scheduler operates on these core entities:

    Department
        dept_id, dept_name
        Owner of courses, rooms, and degree programs.
    Course
        course_id, dept_id, crs_desc, is_major (boolean)
        Course-level attributes. is_major drives departmental room restriction and distribution heuristics.
    Faculty
        faculty_id, dept_id, faculty_name
        Assigned to Offerings; used for Faculty Load reports.
    Room
        room_id, dept_id, room_capacity
        Exam venues. dept_id used for is_major room restriction. room_capacity is a hard constraint.
    Degree Program
        degprog_id, dept_id, degprog_name
        Metadata; used by stakeholders but not directly in scheduling constraints unless mapped to Blocks.
    Major
        major_id, degprog_id, major_name
        Used to classify Blocks, belongs to some degree programs.
    Offering
        offering_id, course_id, faculty_id, room_id (preferred), num_enrolled, exam_date, start_time, end_time
        Core schedulable unit. exam_date/start_time/end_time are placeholders to be set by scheduler. num_enrolled is used for capacity checks.
    Block
        block_id, offering_id, major_id, year_level
        Represents a student group (collection of examinees for an offering). Block links drive student conflict constraints and student-load balancing.

Relationships:

    Course belongs to Department.
    Offering references Course and Faculty; can reference a preferred Room.
    Room belongs to Department.
    Block links Offerings to Majors and represents students who must not have overlapping exams.

The scheduler assumes the ERD to be loaded into relational tables matching these fields. Offerings are the primary units scheduled into (room, date, start_time, end_time) slots subject to constraints.
Core Features & Scheduling Logic

High-level flow:

    Load data (apply exclusions — see Exclusions section).
    Seed non-movable bookings (NSTP, pre-seeded Saturday entries).
    Build constraint model (rooms, time slots, offerings, blocks).
    Use constraint propagation and heuristic search to assign offerings to (date, time, room) within a 5-day window.
    Post-process to satisfy adjacency rules for same-course concurrent offerings and balance major-course distribution.
    Emit three reports.

Constraint Satisfaction (Hard Rules)

All listed constraints are enforced as non-negotiable:

    5-Day Limit
        Scheduling domain is five consecutive exam days (Day 1..Day 5). The solver will only assign exam_date within this set.
    Capacity
        For any assigned (offering, room): num_enrolled <= room_capacity. Violations are prevented by constraint checks.
    Major Course Room Ownership
        If course.is_major = True, the assigned exam room must have room.dept_id = course.dept_id.
    Block Conflict (student conflict)
        No two offerings that reference the same block_id may overlap in time (same date and intersecting time windows). Overlap includes identical start/end times or partial intersection.
    NSTP Pre-scheduled Fixed Bookings
        NSTP offerings are treated as fixed: they occupy their assigned Saturday slot/time and cannot be moved; other offerings must not conflict.
    Concurrent Same-Course Scheduling
        Multiple offerings with the same course_id that are scheduled at the same date & time follow this rule:
            Prefer assigning them to the same room (if combined num_enrolled <= room_capacity).
            If capacity insufficient, assign to adjacent rooms (see "Adjacent Rooms" below). The solver will attempt combinations to minimize the number of different room groups used.

Constraint Implementation Notes:

    The solver runs constraint propagation (domain pruning) before search:
        Remove rooms that violate capacity or departmental restriction from candidate domains.
        Remove date/time slots already occupied by fixed bookings for the room or for any offering sharing a block.
    Time Granularity:
        Time slots are treated as discrete blocks defined by start_time/end_time pairs. Start/end times must be consistent across offerings sharing a slot (e.g., common exam durations or allowed durations list).
    Conflict Detection:
        For blocks, conflict edges are created between offerings in the same block; the search ensures these edges are not scheduled into overlapping times.

Optimization Heuristics (Soft Goals — prioritized)

After satisfying hard constraints, the scheduler applies heuristics to improve fairness:

    Student Load Balancing (primary optimization)
        Objective: Each block should ideally have 2 exams/day, maximum 3/day.
        Mechanism:
            During assignment, track per-block per-day counts.
            Penalize assignments that push a block over 3 exams/day.
            Prefer assignments that achieve 2 exams/day by scoring candidate slots using a weighted cost:
                cost = w1*(deviation from 2 exams/day) + w2*(over-max-penalty) + w3*(schedule compactness).
            A local search (simulated annealing or hill-climbing) is used to swap assignments to reduce total penalty.

    Major Course Distribution (secondary)
        Objective: Spread is_major = True offerings evenly across the 5 days.
        Mechanism:
            Compute target per-day count = ceil(total_majors / 5).
            Penalize deviations; the solver applies balancing swaps that move major offerings between underutilized and overutilized days when constraints allow.

    Room Utilization & Faculty Movement (tertiary)
        Prefer grouping same-course offerings (concurrent) into one room or adjacent rooms to ease faculty movement and exam administration.
        Prefer using preferred room_id when it satisfies hard constraints and does not materially worsen optimization cost.

Scoring and search:

    Use a multi-objective cost function where hard constraints are infeasible (infinite cost), and soft objectives assign finite costs. The solver uses greedy initial placement followed by iterative improvement (Tabu search / simulated annealing) to escape local minima.

Special Case Handling

    NSTP Courses
        NSTP offerings are pre-seeded with a fixed slot on Saturday at a default time. They are treated as immovable bookings.
        The scheduler ignores these offerings for placement but enforces they occupy room/time resources so no other offering can conflict.

    Graduate Education Exclusion
        Any Course where course.dept_name or department is "Graduate Education" is excluded entirely from scheduling and outputs.

    Adjacent Rooms Definition & Assignment
        Adjacent rooms are defined by a maintained adjacency map (recommended) or, when not available, by deterministic proximity logic:
            Preferred: explicit adjacency table (room_adjacency(room_id) -> [adj_room_ids]) maintained by institution (recommended).
            Fallback: numerical/lexicographic adjacency (e.g., room numbers with contiguous numeric ranges or same-building grouping) defined by rules in configuration.
        Assignment behavior for concurrent same-course offerings:
            If multiple offerings of same course and same slot cannot fit in one room, solver searches adjacency groups in order of proximity and available combined capacity.
            The solver will allocate offerings to adjacent rooms minimizing the number of distinct room clusters used and favoring rooms owned by the same department where required.

Output Specifications

All reports are produced in CSV by default for easy integration. JSON export is also supported. PDF generation (formatted schedules) is available as an optional post-processing step (not required for integration tests).

Common conventions:

    Date: YYYY-MM-DD
    Time: 24-hour HH:MM (local timezone)
    CSV encoding: UTF-8, comma-delimited, double-quote escaping for fields containing commas.
    Filenames (defaults):
        exam_schedule.csv
        timetable_by_room.csv
        faculty_load.csv

    Exam Schedule

    File format: CSV (primary), JSON (optional), PDF (optional)
    Columns:
        Offering No. (offering_id)
        Course No. (course_id)
        Course Description (crs_desc)
        Date of Exam (YYYY-MM-DD)
        Time of Exam (start_time — end_time) (e.g., 09:00-12:00)
        Faculty of Offering (faculty_name)
        Room of Exam (room_id / room_name)
    Sorting order:
        Primary: Date of Exam (asc)
        Secondary: Time of Exam (asc)
        Tertiary: Course No. (asc)
    Notes:
        Offerings excluded (e.g., Graduate Education) do not appear.

    Time Table (per Room)

    File format: CSV (primary), JSON (optional)
    Columns:
        Date (YYYY-MM-DD)
        Time (start_time — end_time)
        Offering No. (offering_id)
        Course No. (course_id)
        Total # of Examinees (num_enrolled) — if multiple offerings combined in same room as concurrent sections, list each offering as its own row; an aggregated view (per slot) is also produced as timetable_by_room_aggregated.csv with total examinees aggregated.
    Grouping and Sorting:
        Grouped by Room (room_id). Within each room:
            Date (asc), Time (asc), Offering No. (asc)
    Additional Output:
        For aggregated view, include columns: Room, Date, Time, Offerings (comma-separated offering_ids), Total Examinees.

    Faculty Load

    File format: CSV (primary), JSON (optional)
    Columns:
        Faculty Name (faculty_name)
        Date (YYYY-MM-DD)
        Time (start_time — end_time)
        Room (room_id)
        Offering No. (offering_id)
        Course No. (course_id)
    Sorting order:
        Primary: Faculty Name (asc)
        Secondary: Date (asc)
        Tertiary: Time (asc)
    Notes:
        If a faculty has concurrent assignments (should only occur if faculty_id assigned to multiple simultaneous offerings via different sections — this is allowed only if not in same block), such conflicts are highlighted in a separate faculty_conflicts report and must be resolved manually or via rescheduling.

Report generation options:

    CLI flags to select output formats: --csv, --json, --pdf
    Option to generate per-department filtered reports: --dept-id

Input Assumptions & Data Requirements

    Offerings
        exam_date, start_time, end_time are considered placeholders and should be ignored by the scheduler unless explicitly flagged as FIXED (e.g., NSTP or other pre-seeded fixed bookings).
        num_enrolled must be accurate and reflect actual expected examinees for capacity checks.
    Blocks
        Blocks accurately enumerate student cohort membership via offering_id -> block_id linkage. Blocks represent discrete student groups; a student belonging to multiple blocks must be reflected in multiple block records and will be considered for conflict resolution.
    NSTP Courses
        NSTP offerings are pre-seeded with a Saturday date/time and a room booking. These are treated as fixed in the input and not re-assigned.
    Graduate Education
        Courses and Offerings whose department is "Graduate Education" are present in the source database but must be filtered out by the scheduler.
    Room Adjacency
        An adjacency map is strongly recommended and should be provided via a room_adjacency table:
            CREATE TABLE room_adjacency(room_id INT, adjacent_room_id INT);
        If not provided, the scheduler uses fallback adjacent rules defined in configuration (e.g., same building prefix and numeric proximity).
    Time slots and allowed durations
        The institution should provide a list of allowed start/end time patterns (e.g., 08:00-11:00, 13:00-16:00). The scheduler will conform to these to ensure uniformity.

Example SQL snippets (for reference):

    Filter out Graduate Education:
        SELECT * FROM offering o JOIN course c ON o.course_id = c.course_id JOIN department d ON c.dept_id = d.dept_id WHERE d.dept_name <> 'Graduate Education';
    Example room adjacency table:
        CREATE TABLE room_adjacency (room_id INT, adjacent_room_id INT);

Setup & Installation (Placeholder)

    Clone repo:
        git clone
    Create virtual environment and install:
        python -m venv .venv
        source .venv/bin/activate
        pip install -r requirements.txt
    Database:
        Configure DATABASE_URL in environment (Postgres recommended).
        Run migrations:
            alembic upgrade head
        Seed required reference tables (departments, rooms, adjacency, NSTP bookings).
    Configuration:
        Edit config.yml to set:
            exam_days (5 date strings)
            allowed_time_slots
            room_adjacency_fallback rules
            weights for optimization cost function (w1, w2, w3).

Usage (Placeholder)

    Run full scheduling pipeline:
        python -m scheduler.run --config config.yml --outdir ./reports --format csv
    Example flags:
        --dry-run (validate constraints and report infeasibilities)
        --dept-id (limit scheduling to a department)
        --force-resolve (attempt aggressive swaps to resolve remaining student load penalties)
    Generate reports from existing schedule:
        python -m scheduler.export_reports --format csv --outdir ./reports

Logging:

    Logs to console and rotating file. Severity levels: INFO (progress), WARN (soft objective violations), ERROR (hard constraint violations or data errors).

Constraints & Limitations (Explicit Exclusions)

    Graduate Education:
        All courses/offering belonging to the "Graduate Education" department are excluded entirely from scheduling and outputs.
    Course Type (Lecture vs Lab):
        The scheduler ignores lecture/lab distinctions. Nursing lab subjects are scheduled with no special handling.
    Existing Class Schedule:
        The normal class meeting times are not used as constraints.
    NSTP:
        NSTP courses are pre-scheduled on Saturday at a fixed single default time and must not be moved. They occupy room/time resources.
    Room adjacency:
        Accurate adjacency requires a maintained adjacency table; fallback heuristics may not reflect physical reality and should be validated by facilities staff.
    Faculty simultaneous assignments:
        The system will flag simultaneous assignments for the same faculty. These are allowed only if policy permits; otherwise manual resolution is required.
    Perfect optimization is not guaranteed:
        The solver prioritizes hard constraints above all; the soft optimizations are heuristics — results will depend on input distributions and may require manual tuning of cost weights.

Testing & Validation Recommendations

    Unit tests for:
        Capacity checks
        Block conflict generation and detection
        Major-room departmental restriction
        NSTP immutability
    Integration tests:
        End-to-end scheduling on a representative dataset.
        Stress testing with high-density enrollment to validate swaps and adjacency logic.
    Acceptance tests:
        Verify student load distributions per block (2/day target, max 3/day).
        Verify even distribution of is_major offerings across exam days.

Configuration & Extensibility Notes

    Cost function weights (w1..w3) and allowed_time_slots should be externally configurable.
    Adjacency map should be maintained by Facilities and fed into the database for accurate same-course placement.
    Plug-in architecture:
        Constraint modules and optimization heuristics are pluggable so institution-specific rules can be added without changing the core scheduler.

Contact & Governance

    For schema changes, adjacency map updates, or policy exceptions (e.g., overriding NSTP), coordinate with Scheduling Office and Facilities. Changes to constraints require sign-off from Academic Affairs.

This README is the single source of truth for developers, testers, and stakeholders integrating or extending the Exam Scheduling Application. For design details (architecture diagrams, sequence diagrams, API references), consult the /docs directory and the architecture ADRs.
