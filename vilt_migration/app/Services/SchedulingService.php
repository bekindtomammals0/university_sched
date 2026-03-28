<?php

namespace App\Services;

use App\Models\Offering;
use App\Models\Room;
use App\Exceptions\ConstraintViolationException;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SchedulingService
{
    /**
     * Enforces the 5-Day Limit constraint.
     */
    public function validateExamDays(Offering $offering, Collection $allowedDays): void
    {
        if ($offering->exam_date && !$allowedDays->contains($offering->exam_date->format('Y-m-d'))) {
            // Handle NSTP (Saturday is allowed for NSTP)
            if ($offering->exam_date->dayOfWeek !== Carbon::SATURDAY) {
                throw new ConstraintViolationException(
                    "Offering {$offering->offering_no} assigned date {$offering->exam_date->toDateString()} which is outside the 5-day limit."
                );
            }
        }
    }

    /**
     * Enforces the Capacity constraint.
     */
    public function validateRoomCapacity(Offering $offering, Room $room): void
    {
        if ($offering->num_enrolled > $room->room_capacity) {
            throw new ConstraintViolationException(
                "Offering {$offering->offering_no} (enrollment: {$offering->num_enrolled}) exceeds capacity of Room {$room->id} (capacity: {$room->room_capacity})."
            );
        }
    }

    /**
     * Enforces Major Course Room Ownership restriction.
     */
    public function validateMajorRoomOwnership(Offering $offering, Room $room): void
    {
        if ($offering->course->is_major) {
            if ($offering->course->dept_id !== $room->dept_id) {
                throw new ConstraintViolationException(
                    "Major course offering {$offering->offering_no} (Dept: {$offering->course->dept_id}) assigned to Room {$room->id} owned by Dept: {$room->department_id}."
                );
            }
        }
    }

    /**
     * Checks if two time windows overlap.
     */
    public function checkTimeOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        return max($start1, $start2) < min($end1, $end2);
    }

    /**
     * Enforces Block Conflict (student conflict) constraint.
     */
    public function validateBlockConflicts(Offering $offering): void
    {
        if (!$offering->exam_date || !$offering->time_slot_start || !$offering->time_slot_end) {
            return;
        }

        $blocks = $offering->offering_blocks;
        if ($blocks->isEmpty()) {
            return;
        }

        foreach ($blocks as $block) {
            // Find other offerings sharing this same block (block_no + degprog_id)
            $conflictingOfferings = Offering::whereHas('offering_blocks', function ($query) use ($block) {
                $query->where('block_no', $block->block_no)
                      ->where('degprog_id', $block->degprog_id);
            })
            ->where('offering_no', '!=', $offering->offering_no)
            ->where('exam_date', $offering->exam_date)
            ->get();

            foreach ($conflictingOfferings as $other) {
                if ($other->time_slot_start && $other->time_slot_end) {
                    if ($this->checkTimeOverlap(
                        $offering->time_slot_start, $offering->time_slot_end,
                        $other->time_slot_start, $other->time_slot_end
                    )) {
                        throw new ConstraintViolationException(
                            "Offering {$offering->offering_no} overlaps with {$other->offering_no} for shared block group ({$block->degprog_id}-{$block->block_no})."
                        );
                    }
                }
            }
        }
    }
}
