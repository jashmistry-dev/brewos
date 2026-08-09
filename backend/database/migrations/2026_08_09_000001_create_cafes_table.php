<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cafes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->string('email', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('logo', 500)->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->char('currency', 3)->default('INR');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cafes');
    }
};
