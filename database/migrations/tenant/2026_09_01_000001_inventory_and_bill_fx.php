<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bills') && ! Schema::hasColumn('bills', 'exchange_rate')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->decimal('exchange_rate', 18, 6)->default(1)->after('currency');
            });
        }

        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'track_inventory')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('track_inventory')->default(false)->after('is_active');
                $table->decimal('qty_on_hand', 15, 4)->default(0)->after('track_inventory');
                $table->decimal('avg_cost', 15, 4)->default(0)->after('qty_on_hand');
            });
        }

        if (! Schema::hasTable('inventory_movements')) {
            Schema::create('inventory_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->enum('type', ['receive', 'issue', 'adjust']);
                $table->decimal('qty', 15, 4);
                $table->decimal('unit_cost', 15, 4)->nullable();
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->date('movement_date');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'avg_cost')) {
                    $table->dropColumn('avg_cost');
                }
                if (Schema::hasColumn('products', 'qty_on_hand')) {
                    $table->dropColumn('qty_on_hand');
                }
                if (Schema::hasColumn('products', 'track_inventory')) {
                    $table->dropColumn('track_inventory');
                }
            });
        }

        if (Schema::hasTable('bills') && Schema::hasColumn('bills', 'exchange_rate')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->dropColumn('exchange_rate');
            });
        }
    }
};
