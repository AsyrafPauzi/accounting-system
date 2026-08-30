<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->string('supplier_invoice_path')->nullable()->after('reference');
            $table->string('payment_receipt_path')->nullable()->after('supplier_invoice_path');
        });

        if (Schema::hasColumn('bills', 'receipt_path')) {
            DB::table('bills')->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    if (! empty($row->receipt_path)) {
                        DB::table('bills')->where('id', $row->id)->update([
                            'supplier_invoice_path' => $row->receipt_path,
                        ]);
                    }
                }
            });
        }

        Schema::create('bill_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained('bills')->cascadeOnDelete();
            $table->string('slot', 32);
            $table->string('path');
            $table->string('original_filename')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('action', 16);
            $table->string('reason')->nullable();
            // Logical user id (central users) — no FK; mirrors invoice_attachments.uploaded_by
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
            $table->index(['bill_id', 'slot', 'created_at']);
        });

        if (Schema::hasColumn('bills', 'receipt_path')) {
            $now = now();
            DB::table('bills')->whereNotNull('supplier_invoice_path')->orderBy('id')->chunkById(100, function ($rows) use ($now) {
                foreach ($rows as $row) {
                    DB::table('bill_document_versions')->insert([
                        'bill_id' => $row->id,
                        'slot' => 'supplier_invoice',
                        'path' => $row->supplier_invoice_path,
                        'original_filename' => null,
                        'mime' => null,
                        'size_bytes' => null,
                        'action' => 'uploaded',
                        'reason' => null,
                        'uploaded_by' => $row->created_by ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });

            Schema::table('bills', function (Blueprint $table) {
                $table->dropColumn('receipt_path');
            });
        }
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            if (! Schema::hasColumn('bills', 'receipt_path')) {
                $table->string('receipt_path')->nullable()->after('reference');
            }
        });

        DB::table('bills')->whereNotNull('supplier_invoice_path')->update([
            'receipt_path' => DB::raw('supplier_invoice_path'),
        ]);

        Schema::dropIfExists('bill_document_versions');

        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['supplier_invoice_path', 'payment_receipt_path']);
        });
    }
};
