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
        // 1. Create ordering_sessions table
        Schema::create('ordering_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('cafes')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('table_id')->constrained('restaurant_tables')->onDelete('cascade');
            $table->string('session_token', 64)->unique();
            $table->string('qr_token_used', 64);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->enum('status', ['active', 'completed', 'expired'])->default('active');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['cafe_id', 'status']);
            $table->index(['table_id', 'status']);
        });

        // 2. Create customer_requests table
        Schema::create('customer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cafe_id')->constrained('cafes')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('table_id')->constrained('restaurant_tables')->onDelete('cascade');
            $table->foreignId('ordering_session_id')->nullable()->constrained('ordering_sessions')->onDelete('set null');
            $table->enum('request_type', ['call_staff', 'water', 'request_bill', 'assistance', 'custom'])->default('call_staff');
            $table->enum('status', ['pending', 'acknowledged', 'completed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['cafe_id', 'status']);
            $table->index(['branch_id', 'status']);
        });

        // 3. Extend cafes table with QR Ordering settings
        Schema::table('cafes', function (Blueprint $table) {
            if (! Schema::hasColumn('cafes', 'qr_ordering_enabled')) {
                $table->boolean('qr_ordering_enabled')->default(true);
                $table->boolean('require_location')->default(false);
                $table->integer('location_radius_meters')->default(100);
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->boolean('pay_at_counter_enabled')->default(true);
                $table->boolean('online_payment_enabled')->default(false);
                $table->boolean('require_payment_before_kitchen')->default(true);
                $table->boolean('allow_customer_reorder')->default(true);
                $table->boolean('call_staff_enabled')->default(true);
                $table->boolean('request_bill_enabled')->default(true);
            }
        });

        // 4. Extend orders table with session & ordering linkage
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'ordering_session_id')) {
                $table->foreignId('ordering_session_id')->nullable()->constrained('ordering_sessions')->onDelete('set null');
                $table->enum('order_type', ['dine_in_qr', 'counter_pos', 'waiter_terminal', 'takeaway'])->default('counter_pos');
                $table->text('customer_notes')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['ordering_session_id']);
            $table->dropColumn(['ordering_session_id', 'order_type', 'customer_notes']);
        });

        Schema::table('cafes', function (Blueprint $table) {
            $table->dropColumn([
                'qr_ordering_enabled', 'require_location', 'location_radius_meters',
                'latitude', 'longitude', 'pay_at_counter_enabled', 'online_payment_enabled',
                'require_payment_before_kitchen', 'allow_customer_reorder',
                'call_staff_enabled', 'request_bill_enabled'
            ]);
        });

        Schema::dropIfExists('customer_requests');
        Schema::dropIfExists('ordering_sessions');
    }
};
