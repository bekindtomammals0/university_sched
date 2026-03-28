<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $blueprint) {
            $blueprint->string('id', 50)->primary();
            $blueprint->string('dept_id', 10)->nullable();
            $blueprint->integer('room_capacity');
            $blueprint->timestamps();
            
            $blueprint->foreign('dept_id')->references('id')->on('departments')->nullOnDelete();
        });

        Schema::create('room_adjacency', function (Blueprint $blueprint) {
            $blueprint->string('room_id', 50);
            $blueprint->string('adjacent_room_id', 50);
            
            $blueprint->foreign('room_id')->references('id')->on('rooms')->cascadeOnDelete();
            $blueprint->foreign('adjacent_room_id')->references('id')->on('rooms')->cascadeOnDelete();
            $blueprint->primary(['room_id', 'adjacent_room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_adjacency');
        Schema::dropIfExists('rooms');
    }
};
