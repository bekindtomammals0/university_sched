<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('degree_programs', function (Blueprint $blueprint) {
            $blueprint->string('id', 20)->primary();
            $blueprint->string('dept_id', 10)->nullable();
            $blueprint->string('degprog_name', 150);
            $blueprint->timestamps();

            $blueprint->foreign('dept_id')->references('id')->on('departments')->nullOnDelete();
        });

        // Majors are effectively part of Degree Programs in schema.txt, 
        // but schema.txt uses a separate mapping 'offering_blocks'.
        // We'll follow the offering_blocks table for block conflicts.

        Schema::create('offering_blocks', function (Blueprint $blueprint) {
            $blueprint->string('offering_no', 20);
            $blueprint->string('block_no', 10);
            $blueprint->string('degprog_id', 20);
            $blueprint->integer('year_level')->nullable();
            
            $blueprint->primary(['offering_no', 'block_no', 'degprog_id']);
            $blueprint->foreign('offering_no')->references('offering_no')->on('offerings')->cascadeOnDelete();
            $blueprint->foreign('degprog_id')->references('id')->on('degree_programs')->cascadeOnDelete();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offering_blocks');
        Schema::dropIfExists('degree_programs');
    }
};
