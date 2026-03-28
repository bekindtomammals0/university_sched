<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculties', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('dept_id', 10)->nullable();
            $blueprint->string('faculty_name', 150);
            $blueprint->timestamps();

            $blueprint->foreign('dept_id')->references('id')->on('departments')->nullOnDelete();
        });

        Schema::create('offerings', function (Blueprint $blueprint) {
            $blueprint->string('offering_no', 20)->primary();
            $blueprint->string('course_code', 20);
            $blueprint->foreignId('faculty_id')->nullable()->constrained('faculties')->nullOnDelete();
            $blueprint->integer('num_enrolled');
            
            // Scheduling fields (merged results)
            $blueprint->string('room_id', 50)->nullable();
            $blueprint->date('exam_date')->nullable();
            $blueprint->time('time_slot_start')->nullable();
            $blueprint->time('time_slot_end')->nullable();
            
            $blueprint->timestamps();
            
            $blueprint->foreign('course_code')->references('course_code')->on('courses')->cascadeOnDelete();
            $blueprint->foreign('room_id')->references('id')->on('rooms')->nullOnDelete();
            $blueprint->index(['exam_date', 'time_slot_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offerings');
        Schema::dropIfExists('faculties');
    }
};
