<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->string('full_name');
            $table->string('phone_number');
            $table->string('email')->nullable();
            $table->string('gender');
            $table->string('age_group');
            
            $table->foreignId('state_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lga_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            $table->string('polling_unit')->nullable();

            $table->boolean('voted_2023');
            $table->boolean('vote_2027');

            $table->foreignId('occupation_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, reviewed, approved, rejected, rewarded
            
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            
            $table->timestamps();

            // Indexes for millions of records optimization
            $table->index('status');
            $table->index('state_id');
            $table->index('lga_id');
            $table->index('gender');
            $table->index('age_group');
            $table->index('voted_2023');
            $table->index('vote_2027');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
