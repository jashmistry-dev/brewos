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
        Schema::table('cafes', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('phone');
            $table->text('address')->nullable()->after('logo_path');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('state', 100)->nullable()->after('city');
            $table->string('postal_code', 20)->nullable()->after('state');
            $table->string('country', 100)->default('US')->after('postal_code');
            $table->string('tax_number', 50)->nullable()->after('country');
            $table->decimal('tax_rate', 5, 2)->default(0.00)->after('tax_number');
            $table->json('business_hours')->nullable()->after('tax_rate');
            $table->timestamp('onboarded_at')->nullable()->after('business_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cafes', function (Blueprint $table) {
            $table->dropColumn([
                'logo_path',
                'address',
                'city',
                'state',
                'postal_code',
                'country',
                'tax_number',
                'tax_rate',
                'business_hours',
                'onboarded_at',
            ]);
        });
    }
};
