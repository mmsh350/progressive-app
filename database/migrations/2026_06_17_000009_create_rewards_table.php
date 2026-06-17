<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // airtime, data, cash, none
            $table->decimal('value', 10, 2)->default(0.00);
            $table->decimal('probability', 5, 2); // e.g. 15.00 for 15%
            $table->unsignedInteger('daily_limit')->default(0); // 0 = unlimited
            $table->unsignedInteger('inventory_limit')->default(0); // 0 = unlimited
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewards');
    }
};
