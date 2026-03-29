<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Department;
use App\Models\Offering;
use App\Models\Room;
use App\Models\Block; // Assuming Block is a model used in offering_blocks
use App\Models\DegProg; // Assuming DegProg is a model used in offering_blocks

use App\Exceptions\ConstraintViolationException;
use App\Services\SchedulingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

// Mocking models might be necessary if their dependencies are complex
// For now, we'll try to instantiate them as needed.

class SchedulingServiceTest extends TestCase
{
    use RefreshDatabase; // Useful for creating model instances

    protected SchedulingService $schedulingService;
    protected Carbon $startDate;
    protected Collection $allowedDays;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schedulingService = new SchedulingService();

        // Define a standard 5-day period for testing
        // Assuming a Monday-Friday window for typical exams
        $this->startDate = Carbon::parse('2026-03-30'); // Monday
        $this->allowedDays = collect([
            $this->startDate->format('Y-m-d'),        // Monday
            $this->startDate->clone()->addDay()->format('Y-m-d'), // Tuesday
            $this->startDate->clone()->addDays(2)->format('Y-m-d'), // Wednesday
            $this->startDate->clone()->addDays(3)->format('Y-m-d'), // Thursday
            $this->startDate->clone()->addDays(4)->format('Y-m-d'), // Friday
        ]);
    }

    // --- Tests for validateExamDays ---

    /** @test */
    public function it_validates_exam_date_within_allowed_days()
    {
        $offering = Offering::factory()->make([
            'exam_date' => $this->startDate->clone()->addDay(2), // Wednesday
        ]);

        // Expect no exception
        $this->schedulingService->validateExamDays($offering, $this->allowedDays);

        $this->assertTrue(true); // If no exception is thrown, the test passes
    }

    /** @test */
    public function it_throws_exception_for_exam_date_outside_allowed_days()
    {
        $this->expectException(ConstraintViolationException::class);
        $this->expectExceptionMessageMatches('/outside the 5-day limit/');

        $offering = Offering::factory()->make([
            'exam_date' => $this->startDate->clone()->addDays(6), // Saturday (if not handled as exception) or Sunday
        ]);

        $this->schedulingService->validateExamDays($offering, $this->allowedDays);
    }

    /** @test */
    public function it_allows_saturday_exam_dates_due_to_current_logic()
    {
        // The current logic in validateExamDays:
        // "if date is outside window AND it's NOT a Saturday, throw error."
        // This implies Saturdays outside the window are allowed.

        $knownSaturday = Carbon::parse('2026-04-04'); // This is a Saturday

        $offeringSaturday = Offering::factory()->make([
            'exam_date' => $knownSaturday,
        ]);

        // Since the date is Saturday, the condition `if ($offering->exam_date->dayOfWeek !== Carbon::SATURDAY)` is false,
        // so the exception within the IF block is not thrown, even if Saturday is outside `allowedDays`.
        $this->schedulingService->validateExamDays($offeringSaturday, $this->allowedDays);
        $this->assertTrue(true); // Should not throw exception based on current implementation
    }

    /** @test */
    public function it_handles_offerings_without_exam_date_gracefully()
    {
        $offering = Offering::factory()->make([
            'exam_date' => null, // No exam date set
        ]);

        // Expect no exception
        $this->schedulingService->validateExamDays($offering, $this->allowedDays);

        $this->assertTrue(true); // If no exception is thrown, the test passes
    }

    // --- Tests for validateRoomCapacity ---
    // These tests will require database entities (Room, Offering)
    // For now, we'll mock them or use factories.

    /** @test */
    public function it_validates_room_capacity_when_enrollment_is_within_limit()
    {
        $room = Room::factory()->make(['room_capacity' => 100]);
        $offering = Offering::factory()->make(['num_enrolled' => 99]);

        // Expect no exception
        $this->schedulingService->validateRoomCapacity($offering, $room);
        $this->assertTrue(true);
    }

    /** @test */
    public function it_validates_room_capacity_when_enrollment_equals_limit()
    {
        $room = Room::factory()->make(['room_capacity' => 100]);
        $offering = Offering::factory()->make(['num_enrolled' => 100]);

        // Expect no exception
        $this->schedulingService->validateRoomCapacity($offering, $room);
        $this->assertTrue(true);
    }

    /** @test */
    public function it_throws_exception_for_room_capacity_exceeded()
    {
        $this->expectException(ConstraintViolationException::class);
        $this->expectExceptionMessageMatches('/exceeds capacity/');

        $room = Room::factory()->make(['room_capacity' => 100]);
        $offering = Offering::factory()->make(['num_enrolled' => 101]);

        $this->schedulingService->validateRoomCapacity($offering, $room);
    }

    // --- Tests for validateMajorRoomOwnership ---
    // Requires Course and Department models, and Room linked to Department

    /** @test */
    public function it_validates_major_course_room_ownership_when_departments_match()
    {
        $department = Department::factory()->create();
        $room = Room::factory()->make(['dept_id' => $department->id]);
        $course = Course::factory()->make(['dept_id' => $department->id, 'is_major' => true]);
        $offering = Offering::factory()->make(['course_id' => $course->id]);

        // Expect no exception
        $this->schedulingService->validateMajorRoomOwnership($offering, $room);
        $this->assertTrue(true);
    }

    /** @test */
    public function it_throws_exception_for_major_course_in_wrong_department_room()
    {
        $this->expectException(ConstraintViolationException::class);
        $this->expectExceptionMessageMatches('/Major course/');

        $majorDepartment = Department::factory()->create();
        $otherDepartment = Department::factory()->create();

        $room = Room::factory()->make(['dept_id' => $otherDepartment->id]); // Room in wrong department
        $course = Course::factory()->make(['dept_id' => $majorDepartment->id, 'is_major' => true]); // Major course in correct department
        $offering = Offering::factory()->make(['course_id' => $course->id]);

        $this->schedulingService->validateMajorRoomOwnership($offering, $room);
    }

    /** @test */
    public function it_does_not_validate_ownership_for_non_major_courses()
    {
        $majorDepartment = Department::factory()->create();
        $otherDepartment = Department::factory()->create();

        $room = Room::factory()->make(['dept_id' => $otherDepartment->id]); // Room in wrong department
        $course = Course::factory()->make(['dept_id' => $majorDepartment->id, 'is_major' => false]); // NOT a major course
        $offering = Offering::factory()->make(['course_id' => $course->id]);

        // Expect no exception, as it's not a major course
        $this->schedulingService->validateMajorRoomOwnership($offering, $room);
        $this->assertTrue(true);
    }

    // --- Tests for checkTimeOverlap ---

    /** @test */
    public function it_detects_no_overlap_when_times_are_separate()
    {
        $start1 = '08:00'; $end1 = '10:00';
        $start2 = '10:00'; $end2 = '12:00'; // Adjacent, no overlap

        $this->assertFalse($this->schedulingService->checkTimeOverlap($start1, $end1, $start2, $end2));
    }

    /** @test */
    public function it_detects_no_overlap_when_times_are_separate_reversed()
    {
        $start1 = '10:00'; $end1 = '12:00';
        $start2 = '08:00'; $end2 = '10:00'; // Adjacent, no overlap

        $this->assertFalse($this->schedulingService->checkTimeOverlap($start1, $end1, $start2, $end2));
    }

    /** @test */
    public function it_detects_overlap_when_one_time_is_within_another()
    {
        $start1 = '08:00'; $end1 = '12:00';
        $start2 = '09:00'; $end2 = '10:00'; // Second is inside first

        $this->assertTrue($this->schedulingService->checkTimeOverlap($start1, $end1, $start2, $end2));
    }

    /** @test */
    public function it_detects_overlap_when_times_partially_overlap()
    {
        $start1 = '08:00'; $end1 = '10:00';
        $start2 = '09:00'; $end2 = '11:00'; // Partial overlap

        $this->assertTrue($this->schedulingService->checkTimeOverlap($start1, $end1, $start2, $end2));
    }

    /** @test */
    public function it_detects_overlap_when_times_start_at_same_time()
    {
        $start1 = '08:00'; $end1 = '10:00';
        $start2 = '08:00'; $end2 = '09:00'; // Start at same time

        $this->assertTrue($this->schedulingService->checkTimeOverlap($start1, $end1, $start2, $end2));
    }

    /** @test */
    public function it_detects_overlap_when_times_end_at_same_time()
    {
        $start1 = '08:00'; $end1 = '10:00';
        $start2 = '09:00'; $end2 = '10:00'; // End at same time

        $this->assertTrue($this->schedulingService->checkTimeOverlap($start1, $end1, $start2, $end2));
    }

    // --- Tests for validateBlockConflicts ---
    // This requires setting up Offerings, Blocks, and their relationships.
    // This is more complex and may require more detailed model setup or mocking.

    /** @test */
    public function it_does_not_throw_exception_if_no_conflicts_exist()
    {
        // Setup: An offering with no shared block/time conflicts
        $offering1 = Offering::factory()->create([
            'exam_date' => $this->startDate,
            'time_slot_start' => '08:00',
            'time_slot_end' => '10:00',
        ]);
        // Attach block to offering1
        $block1 = Block::factory()->create(['block_no' => 1, 'degprog_id' => 1]);
        $offering1->offering_blocks()->attach($block1->id, ['block_no' => 1, 'degprog_id' => 1]);

        // Another offering in a different block or with no overlap
        $offering2 = Offering::factory()->create([
            'exam_date' => $this->startDate,
            'time_slot_start' => '10:00',
            'time_slot_end' => '12:00',
        ]);
        // Attach block to offering2
        $block2 = Block::factory()->create(['block_no' => 2, 'degprog_id' => 2]);
        $offering2->offering_blocks()->attach($block2->id, ['block_no' => 2, 'degprog_id' => 2]);

        // Test offering1, it should not conflict with offering2
        $this->schedulingService->validateBlockConflicts($offering1);
        $this->assertTrue(true); // No exception thrown
    }

    /** @test */
    public function it_throws_exception_for_block_conflict_with_time_overlap()
    {
        $this->expectException(ConstraintViolationException::class);
        $this->expectExceptionMessageMatches('/overlaps with/');

        // Setup: Two offerings sharing the same block and date, with overlapping times
        $date = $this->startDate; // Monday
        $block_no = 1;
        $degprog_id = 1;

        $offeringA = Offering::factory()->create([
            'exam_date' => $date,
            'time_slot_start' => '08:00',
            'time_slot_end' => '10:00',
            'offering_no' => 'OFF-A',
        ]);
        // Attach block to offeringA
        $blockA = Block::factory()->create(['block_no' => $block_no, 'degprog_id' => $degprog_id]);
        $offeringA->offering_blocks()->attach($blockA->id, ['block_no' => $block_no, 'degprog_id' => $degprog_id]);

        $offeringB = Offering::factory()->create([
            'exam_date' => $date,
            'time_slot_start' => '09:00', // Overlaps with offeringA
            'time_slot_end' => '11:00',
            'offering_no' => 'OFF-B',
        ]);
        // Attach block to offeringB
        $blockB = Block::factory()->create(['block_no' => $block_no, 'degprog_id' => $degprog_id]);
        $offeringB->offering_blocks()->attach($blockB->id, ['block_no' => $block_no, 'degprog_id' => $degprog_id]);

        // Test offeringB, it should conflict with offeringA
        $this->schedulingService->validateBlockConflicts($offeringB);
    }

    /** @test */
    public function it_does_not_throw_exception_if_same_block_different_date()
    {
        $date1 = $this->startDate;
        $date2 = $this->startDate->clone()->addDay(); // Next day
        $block_no = 1;
        $degprog_id = 1;

        $offeringA = Offering::factory()->create([
            'exam_date' => $date1,
            'time_slot_start' => '08:00',
            'time_slot_end' => '10:00',
            'offering_no' => 'OFF-A',
        ]);
        // Attach block to offeringA
        $blockA = Block::factory()->create(['block_no' => $block_no, 'degprog_id' => $degprog_id]);
        $offeringA->offering_blocks()->attach($blockA->id, ['block_no' => $block_no, 'degprog_id' => $degprog_id]);

        $offeringB = Offering::factory()->create([
            'exam_date' => $date2, // Different date
            'time_slot_start' => '08:00',
            'time_slot_end' => '10:00',
            'offering_no' => 'OFF-B',
        ]);
        // Attach block to offeringB
        $blockB = Block::factory()->create(['block_no' => $block_no, 'degprog_id' => $degprog_id]);
        $offeringB->offering_blocks()->attach($blockB->id, ['block_no' => $block_no, 'degprog_id' => $degprog_id]);

        // Test offeringB, it should not conflict with offeringA due to different dates
        $this->schedulingService->validateBlockConflicts($offeringB);
        $this->assertTrue(true); // No exception thrown
    }

    /** @test */
    public function it_does_not_throw_exception_if_different_blocks_same_date()
    {
        $date = $this->startDate;
        $block_no1 = 1;
        $degprog_id1 = 1;
        $block_no2 = 2; // Different block
        $degprog_id2 = 2; // Different degprog

        $offeringA = Offering::factory()->create([
            'exam_date' => $date,
            'time_slot_start' => '08:00',
            'time_slot_end' => '10:00',
            'offering_no' => 'OFF-A',
        ]);
        // Attach block to offeringA
        $blockA = Block::factory()->create(['block_no' => $block_no1, 'degprog_id' => $degprog_id1]);
        $offeringA->offering_blocks()->attach($blockA->id, ['block_no' => $block_no1, 'degprog_id' => $degprog_id1]);

        $offeringB = Offering::factory()->create([
            'exam_date' => $date,
            'time_slot_start' => '08:00', // Same time, but different block
            'time_slot_end' => '10:00',
            'offering_no' => 'OFF-B',
        ]);
        // Attach block to offeringB
        $blockB = Block::factory()->create(['block_no' => $block_no2, 'degprog_id' => $degprog_id2]);
        $offeringB->offering_blocks()->attach($blockB->id, ['block_no' => $block_no2, 'degprog_id' => $degprog_id2]);

        // Test offeringB, it should not conflict with offeringA due to different blocks
        $this->schedulingService->validateBlockConflicts($offeringB);
        $this->assertTrue(true); // No exception thrown
    }

    /** @test */
    public function it_handles_offerings_with_no_exam_date_or_time_slots_gracefully()
    {
        // Offering with no exam date
        $offering1 = Offering::factory()->make(['exam_date' => null, 'time_slot_start' => null, 'time_slot_end' => null]);
        $this->schedulingService->validateBlockConflicts($offering1);
        $this->assertTrue(true);

        // Offering with exam date but no time slots
        $offering2 = Offering::factory()->make(['exam_date' => $this->startDate, 'time_slot_start' => null, 'time_slot_end' => null]);
        $this->schedulingService->validateBlockConflicts($offering2);
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_offerings_with_no_offering_blocks_gracefully()
    {
        $offering = Offering::factory()->create([
            'exam_date' => $this->startDate,
            'time_slot_start' => '08:00',
            'time_slot_end' => '10:00',
        ]);
        // Ensure offering has no blocks attached
        $offering->offering_blocks()->detach();

        $this->schedulingService->validateBlockConflicts($offering);
        $this->assertTrue(true); // No exception thrown
    }
}
