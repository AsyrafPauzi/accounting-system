<?php

namespace Tests\Unit\Sales;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\MyInvoisService;
use Tests\TestCase;

class MyInvoisUblTest extends TestCase
{
    public function test_invoice_payload_is_ubl_json_not_a_custom_shape(): void
    {
        $invoice = new Invoice([
            'invoice_number' => 'INV-2',
            'issue_date'     => '2026-08-16',
            'currency'       => 'MYR',
            'tax_amount'     => 8,
            'total_amount'   => 108,
        ]);
        $invoice->setRelation('customer', new Customer([
            'name'  => 'Tropika Sdn Bhd',
            'tin'   => 'EI00000000010',
            'email' => 'finance@tropika.my',
            'phone' => '+60 3-2200 1100',
        ]));
        $invoice->setRelation('items', collect([
            new InvoiceItem([
                'description'         => 'Monthly retainer',
                'quantity'            => 1,
                'unit_price'          => 100,
                'tax_rate'            => 8,
                'discount_amount'     => 0,
                'item_classification' => '022',
                'amount'              => 100,
            ]),
        ]));

        $tenant = (object) [
            'legal_name'  => 'Demo Company',
            'display_name'=> 'Demo',
            'tin'         => 'IG26858002020',
            'brn'         => '202503175488',
            'msic_code'   => '62010',
            'sst_number'  => '',
            'street'      => 'Lot 1, Jalan Demo',
            'city'        => 'Kuala Lumpur',
            'postcode'    => '50000',
            'state'       => 'Wilayah Persekutuan',
            'phone'       => '0123456789',
            'email'       => 'hello@demo.my',
        ];

        $service = app(MyInvoisService::class);
        $payload = $service->buildDocument($invoice, $tenant);
        $json = $service->encodeDocument($payload);

        $this->assertArrayHasKey('_D', $payload);
        $this->assertArrayHasKey('Invoice', $payload);
        $this->assertSame('01', $payload['Invoice'][0]['InvoiceTypeCode'][0]['_']);
        $this->assertSame('1.0', $payload['Invoice'][0]['InvoiceTypeCode'][0]['listVersionID']);
        $this->assertArrayHasKey('AccountingSupplierParty', $payload['Invoice'][0]);
        $this->assertArrayHasKey('AccountingCustomerParty', $payload['Invoice'][0]);
        $this->assertSame('NRIC', $payload['Invoice'][0]['AccountingCustomerParty'][0]['Party'][0]['PartyIdentification'][1]['ID'][0]['schemeID']);
        $this->assertSame('NA', $payload['Invoice'][0]['AccountingCustomerParty'][0]['Party'][0]['PartyIdentification'][1]['ID'][0]['_']);
        $this->assertSame('004', $payload['Invoice'][0]['InvoiceLine'][0]['Item'][0]['CommodityClassification'][0]['ItemClassificationCode'][0]['_']);
        $this->assertSame('PASSPORT', $payload['Invoice'][0]['AccountingSupplierParty'][0]['Party'][0]['PartyIdentification'][1]['ID'][0]['schemeID']);
        $this->assertStringNotContainsString('invoiceTypeCode', $json);
        $this->assertStringContainsString('AccountingSupplierParty', $json);
    }

    public function test_customer_party_reads_brn_not_identification_number(): void
    {
        $customer = new Customer([
            'name' => 'Tropika Sdn Bhd',
            'tin' => 'C20001234567',
            'brn' => '201901012345',
            'identification_type' => 'BRN',
            'billing_street' => '1 Jalan Ampang',
            'billing_city' => 'Kuala Lumpur',
            'billing_zip' => '50450',
            'billing_state' => 'Kuala Lumpur',
            'phone' => '0312345678',
            'email' => 'ap@tropika.my',
        ]);
        $customer->exists = true;
        $customer->syncOriginal();

        $invoice = new Invoice([
            'invoice_number' => 'INV-11',
            'issue_date' => '2026-08-17',
            'currency' => 'MYR',
            'total_amount' => 100,
        ]);
        $invoice->setRelation('customer', $customer);
        $invoice->setRelation('items', collect([
            new InvoiceItem([
                'description' => 'Line',
                'quantity' => 1,
                'unit_price' => 100,
                'tax_rate' => 0,
                'amount' => 100,
                'item_classification' => '022',
            ]),
        ]));
        $tenant = (object) [
            'legal_name' => 'Demo',
            'tin' => 'IG26858002020',
            'myinvois_id_type' => 'PASSPORT',
            'myinvois_id_value' => 'NA',
            'msic_code' => '62010',
            'email' => 'hello@demo.my',
        ];

        $payload = app(MyInvoisService::class)->buildDocument($invoice, $tenant);
        $this->assertSame('201901012345', $payload['Invoice'][0]['AccountingCustomerParty'][0]['Party'][0]['PartyIdentification'][1]['ID'][0]['_']);
        $this->assertSame('BRN', $payload['Invoice'][0]['AccountingCustomerParty'][0]['Party'][0]['PartyIdentification'][1]['ID'][0]['schemeID']);
    }

    public function test_credit_and_debit_notes_are_ubl_types_02_and_03(): void
    {
        $tenant = (object) [
            'legal_name' => 'Demo',
            'tin' => 'IG26858002020',
            'brn' => 'NA',
            'myinvois_id_type' => 'PASSPORT',
            'myinvois_id_value' => 'NA',
            'msic_code' => '62010',
            'email' => 'hello@demo.my',
        ];
        $customer = new Customer([
            'name' => 'Buyer',
            'tin' => 'EI00000000010',
            'identification_type' => 'NRIC',
            'brn' => 'NA',
        ]);
        $item = new InvoiceItem([
            'description' => 'Line',
            'quantity' => 1,
            'unit_price' => 50,
            'tax_rate' => 0,
            'amount' => 50,
            'item_classification' => '022',
        ]);

        $cn = new \App\Models\CreditNote(['cn_number' => 'CN-1', 'issue_date' => '2026-08-17', 'currency' => 'MYR', 'total_amount' => 50]);
        $cn->setRelation('customer', $customer);
        $cn->setRelation('items', collect([
            new \App\Models\CreditNoteItem([
                'description' => 'Line',
                'quantity' => 1,
                'unit_price' => 50,
                'tax_rate' => 0,
                'amount' => 50,
                'item_classification' => '022',
            ]),
        ]));
        $cn->setRelation('invoice', new Invoice(['invoice_number' => 'INV-9']));

        $dn = new \App\Models\DebitNote(['dn_number' => 'DN-1', 'issue_date' => '2026-08-17', 'currency' => 'MYR', 'total_amount' => 50]);
        $dn->setRelation('customer', $customer);
        $dn->setRelation('items', collect([
            new \App\Models\DebitNoteItem([
                'description' => 'Line',
                'quantity' => 1,
                'unit_price' => 50,
                'tax_rate' => 0,
                'amount' => 50,
                'item_classification' => '022',
            ]),
        ]));
        $dn->setRelation('invoice', new Invoice(['invoice_number' => 'INV-9']));

        $service = app(MyInvoisService::class);
        $this->assertSame('02', $service->buildDocument($cn, $tenant)['Invoice'][0]['InvoiceTypeCode'][0]['_']);
        $this->assertSame('03', $service->buildDocument($dn, $tenant)['Invoice'][0]['InvoiceTypeCode'][0]['_']);
    }

    public function test_consolidated_payload_is_type_11_ubl(): void
    {
        $batch = new \App\Models\ConsolidatedEInvoice([
            'document_number' => 'CEI-0001',
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
            'total_amount' => 100,
        ]);
        $invoices = [new Invoice(['invoice_number' => 'INV-A', 'total_amount' => 100])];
        $tenant = (object) [
            'legal_name' => 'Demo',
            'tin' => 'IG26858002020',
            'myinvois_id_type' => 'PASSPORT',
            'myinvois_id_value' => 'NA',
            'msic_code' => '62010',
            'email' => 'hello@demo.my',
        ];
        $payload = app(MyInvoisService::class)->buildConsolidatedDocument($batch, $invoices, $tenant);
        $this->assertSame('11', $payload['Invoice'][0]['InvoiceTypeCode'][0]['_']);
        $this->assertSame('004', $payload['Invoice'][0]['InvoiceLine'][0]['Item'][0]['CommodityClassification'][0]['ItemClassificationCode'][0]['_']);
        $this->assertArrayNotHasKey('invoiceTypeCode', $payload);
    }

    public function test_self_billed_bill_is_ubl_type_12_with_swapped_parties(): void
    {
        $bill = new \App\Models\Bill([
            'bill_number'  => 'BILL-0009',
            'bill_date'    => '2026-08-17',
            'currency'     => 'MYR',
            'tax_amount'   => 8,
            'total_amount' => 108,
            'status'       => 'unpaid',
        ]);
        $bill->setRelation('supplier', new \App\Models\Supplier([
            'name' => 'Klang Logistics Sdn Bhd',
            'tin'  => 'C30002345678',
            'brn'  => '202001012345',
            'email'=> 'ar@klanglogistics.my',
        ]));
        $bill->setRelation('items', collect([
            new \App\Models\BillItem([
                'description' => 'Freight',
                'quantity'    => 1,
                'unit_amount' => 100,
                'amount'      => 100,
            ]),
        ]));
        $tenant = (object) [
            'legal_name' => 'Demo Company',
            'tin' => 'IG26858002020',
            'myinvois_id_type' => 'PASSPORT',
            'myinvois_id_value' => 'NA',
            'msic_code' => '62010',
            'email' => 'hello@demo.my',
        ];
        $payload = app(MyInvoisService::class)->buildSelfBilledDocument($bill, $tenant);
        $this->assertSame('12', $payload['Invoice'][0]['InvoiceTypeCode'][0]['_']);
        $this->assertSame('Klang Logistics Sdn Bhd', $payload['Invoice'][0]['AccountingSupplierParty'][0]['Party'][0]['PartyLegalEntity'][0]['RegistrationName'][0]['_']);
        $this->assertSame('Demo Company', $payload['Invoice'][0]['AccountingCustomerParty'][0]['Party'][0]['PartyLegalEntity'][0]['RegistrationName'][0]['_']);
        $this->assertSame('C30002345678', $payload['Invoice'][0]['AccountingSupplierParty'][0]['Party'][0]['PartyIdentification'][0]['ID'][0]['_']);
        $this->assertSame('IG26858002020', $payload['Invoice'][0]['AccountingCustomerParty'][0]['Party'][0]['PartyIdentification'][0]['ID'][0]['_']);
    }

    public function test_json_signer_adds_ubl_extensions_when_pkcs12_present(): void
    {
        if (! function_exists('openssl_pkcs12_export')) {
            $this->markTestSkipped('OpenSSL PKCS12 export is not available.');
        }
        $pkey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['countryName' => 'MY', 'organizationName' => 'Test', 'commonName' => 'Test'], $pkey, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $pkey, 365, ['digest_alg' => 'sha256']);
        openssl_pkcs12_export($x509, $p12, $pkey, 'secret');

        $tenant = (object) [
            'myinvois_cert' => encrypt(base64_encode($p12)),
            'myinvois_cert_password' => encrypt('secret'),
        ];
        $payload = [
            '_D' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
            '_A' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
            '_B' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
            'Invoice' => [[
                'ID' => [['_' => 'INV-S']],
                'InvoiceTypeCode' => [['_' => '01', 'listVersionID' => '1.0']],
            ]],
        ];
        $json = app(\App\Services\MyInvoisJsonSigner::class)->sign($payload, $tenant);
        $this->assertStringContainsString('UBLExtensions', $json);
        $this->assertStringContainsString('"listVersionID":"1.1"', $json);
        $this->assertStringContainsString('SignatureValue', $json);
    }

    public function test_invalid_is_not_mapped_as_valid(): void
    {
        $map = new \ReflectionMethod(MyInvoisService::class, 'mapLhdnStatus');
        $map->setAccessible(true);
        $service = app(MyInvoisService::class);

        $this->assertSame('rejected', $map->invoke($service, 'Invalid'));
        $this->assertSame('valid', $map->invoke($service, 'Valid'));
        $this->assertSame('submitted', $map->invoke($service, 'Submitted'));
    }
}
