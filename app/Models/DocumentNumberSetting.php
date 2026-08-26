<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentNumberSetting extends Model
{
    protected $fillable = [
        'doc_type',
        'prefix',
        'next_number',
        'pad_width',
        'reset_on_fy',
        'last_fy_start_year',
    ];

    protected function casts(): array
    {
        return [
            'next_number'        => 'integer',
            'pad_width'          => 'integer',
            'reset_on_fy'        => 'boolean',
            'last_fy_start_year' => 'integer',
        ];
    }

    /**
     * @return array<string, array{prefix: string, table: string, column: string}>
     */
    public static function docTypeCatalog(): array
    {
        return [
            'invoice'              => ['prefix' => 'INV', 'table' => 'invoices', 'column' => 'invoice_number'],
            'bill'                 => ['prefix' => 'BILL', 'table' => 'bills', 'column' => 'bill_number'],
            'credit_note'          => ['prefix' => 'CN', 'table' => 'credit_notes', 'column' => 'cn_number'],
            'debit_note'           => ['prefix' => 'DN', 'table' => 'debit_notes', 'column' => 'dn_number'],
            'estimate'             => ['prefix' => 'EST', 'table' => 'estimates', 'column' => 'estimate_number'],
            'sales_order'          => ['prefix' => 'SO', 'table' => 'sales_orders', 'column' => 'so_number'],
            'delivery_order'       => ['prefix' => 'DO', 'table' => 'delivery_orders', 'column' => 'do_number'],
            'purchase_order'       => ['prefix' => 'PO', 'table' => 'purchase_orders', 'column' => 'po_number'],
            'goods_receipt'        => ['prefix' => 'GRN', 'table' => 'goods_receipts', 'column' => 'grn_number'],
            'supplier_credit_note' => ['prefix' => 'SCN', 'table' => 'supplier_credit_notes', 'column' => 'scn_number'],
            'supplier_debit_note'  => ['prefix' => 'SDN', 'table' => 'supplier_debit_notes', 'column' => 'sdn_number'],
            'ar_deposit'           => ['prefix' => 'DEP', 'table' => 'ar_deposits', 'column' => 'id'],
            'ap_deposit'           => ['prefix' => 'APD', 'table' => 'ap_deposits', 'column' => 'id'],
        ];
    }
}
