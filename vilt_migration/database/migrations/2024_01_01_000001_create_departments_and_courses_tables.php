<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $blueprint) {
            $blueprint->string('id', 10)->primary(); // e.g., 'DABE'
            $blueprint->string('dept_name', 100);
            $blueprint->timestamps();
        });

        Schema::create('courses', function (Blueprint $blueprint) {
            $blueprint->string('course_code', 20)->primary();
            $blueprint->string('dept_id', 10)->nullable();
            $blueprint->text('course_description');
            $blueprint->boolean('is_major')->default(false);
            $blueprint->boolean('is_grad')->default(false);
            $blueprint->timestamps();
            
            $blueprint->foreign('dept_id')->references('id')->on('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
        Schema::dropIfExists('departments');
    }
};
