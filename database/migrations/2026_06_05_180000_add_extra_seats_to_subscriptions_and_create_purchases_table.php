<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-serve "buy another seat" for tenants whose plan caps team size.
 *
 * Two changes:
 *
 *   1. `subscriptions.extra_seats` — how many *paid* extras the tenant
 *      currently holds. Authoritative count for billing & UI; bumped only
 *      after a successful Toyyibpay webhook.
 *
 *   2. `extra_seat_purchases` — server-side draft of a pending purchase.
 *      The team-add flow used to stuff the user's plaintext password into
 *      Toyyibpay's `billExternalReferenceNo`, where it surfaced in the
 *      gateway's logs and the webhook payload. Holding the draft here means
 *      we can pass only the purchase id to Toyyibpay; the password is
 *      hashed at draft time and stays in our DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'extra_seats')) {
                $table->unsignedInteger('extra_seats')->default(0)->after('gateway_subscription_id');
            }
        });

        if (! Schema::hasTable('extra_seat_purchases')) {
            Schema::create('extra_seat_purchases', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
                // The user record we'll create on successful payment. Stored
                // pre-hashed so a leak of this row never reveals the password.
                $table->string('name');
                $table->string('email');
                $table->string('password_hash');
                $table->string('role', 32);
                // Money side.
                $table->decimal('amount', 10, 2);
                $table->string('currency', 3)->default('MYR');
                // Lifecycle: pending → paid (user created) | failed | cancelled.
                $table->string('status', 16)->default('pending');
                $table->string('gateway', 32)->default('toyyibpay');
                $table->string('gateway_bill_code')->nullable();
                $table->unsignedBigInteger('user_id')->nullable(); // populated on success
                $table->timestamp('paid_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
                $table->index('gateway_bill_code');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_seat_purchases');

        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'extra_seats')) {
                $table->dropColumn('extra_seats');
            }
        });
    }
};
