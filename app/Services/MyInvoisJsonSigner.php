<?php

namespace App\Services;

/**
 * LHDN MyInvois JSON (XAdES-enveloped) signer.
 *
 * When a PKCS#12 is stored on the tenant, documents are signed and sent as
 * e-Invoice v1.1. Without a cert we keep unsigned v1.0 so preprod still works.
 *
 * @see https://sdk.myinvois.hasil.gov.my/signature-creation-json/
 */
class MyInvoisJsonSigner
{
    public function canSign(?object $tenant): bool
    {
        if (! $tenant) {
            return false;
        }

        return filled($tenant->myinvois_cert ?? null) && filled($tenant->myinvois_cert_password ?? null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sign(array $payload, object $tenant): string
    {
        $certs = $this->readPkcs12($tenant);
        $der = $this->certificateDer($certs['cert']);
        $certB64 = base64_encode($der);
        $certDigest = base64_encode(hash('sha256', $der, true));
        $parsed = openssl_x509_parse($certs['cert']);
        if ($parsed === false) {
            throw new \LogicException('Could not parse the MyInvois signing certificate.');
        }
        $issuer = $this->dnString($parsed['issuer'] ?? []);
        $subject = $this->dnString($parsed['subject'] ?? []);
        $serial = $this->serialDecimal($parsed);

        if (isset($payload['Invoice'][0]['InvoiceTypeCode'][0]['listVersionID'])) {
            $payload['Invoice'][0]['InvoiceTypeCode'][0]['listVersionID'] = '1.1';
        }

        $unsigned = $this->minify($payload);
        $docDigest = base64_encode(hash('sha256', $unsigned, true));

        $signingTime = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $signedProperties = [[
            'Id' => 'id-xades-signed-props',
            'SignedSignatureProperties' => [[
                'SigningTime' => [['_' => $signingTime]],
                'SigningCertificate' => [[
                    'Cert' => [[
                        'CertDigest' => [[
                            'DigestMethod' => [['_' => '', 'Algorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256']],
                            'DigestValue' => [['_' => $certDigest]],
                        ]],
                        'IssuerSerial' => [[
                            'X509IssuerName' => [['_' => $issuer]],
                            'X509SerialNumber' => [['_' => $serial]],
                        ]],
                    ]],
                ]],
            ]],
        ]];

        $signedPropsForDigest = $this->minify([
            'Target' => 'signature',
            'SignedProperties' => $signedProperties,
        ]);
        $signedPropsDigest = base64_encode(hash('sha256', $signedPropsForDigest, true));

        openssl_sign($unsigned, $signature, $certs['pkey'], OPENSSL_ALGO_SHA256);
        $signatureValue = base64_encode($signature);

        $payload['Invoice'][0]['UBLExtensions'] = [[
            'UBLExtension' => [[
                'ExtensionURI' => [['_' => 'urn:oasis:names:specification:ubl:dsig:enveloped:xades']],
                'ExtensionContent' => [[
                    'UBLDocumentSignatures' => [[
                        'SignatureInformation' => [[
                            'ID' => [['_' => 'urn:oasis:names:specification:ubl:signature:1']],
                            'ReferencedSignatureID' => [['_' => 'urn:oasis:names:specification:ubl:signature:Invoice']],
                            'Signature' => [[
                                'Id' => 'signature',
                                'Object' => [[
                                    'QualifyingProperties' => [[
                                        'Target' => 'signature',
                                        'SignedProperties' => $signedProperties,
                                    ]],
                                ]],
                                'KeyInfo' => [[
                                    'X509Data' => [[
                                        'X509Certificate' => [['_' => $certB64]],
                                        'X509SubjectName' => [['_' => $subject]],
                                        'X509IssuerSerial' => [[
                                            'X509IssuerName' => [['_' => $issuer]],
                                            'X509SerialNumber' => [['_' => $serial]],
                                        ]],
                                    ]],
                                ]],
                                'SignatureValue' => [['_' => $signatureValue]],
                                'SignedInfo' => [[
                                    'SignatureMethod' => [[
                                        '_' => '',
                                        'Algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
                                    ]],
                                    'Reference' => [
                                        [
                                            'Type' => 'http://uri.etsi.org/01903/v1.3.2#SignedProperties',
                                            'URI' => '#id-xades-signed-props',
                                            'DigestMethod' => [['_' => '', 'Algorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256']],
                                            'DigestValue' => [['_' => $signedPropsDigest]],
                                        ],
                                        [
                                            'Type' => '',
                                            'URI' => '',
                                            'DigestMethod' => [['_' => '', 'Algorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256']],
                                            'DigestValue' => [['_' => $docDigest]],
                                        ],
                                    ],
                                ]],
                            ]],
                        ]],
                    ]],
                ]],
            ]],
        ]];
        $payload['Invoice'][0]['Signature'] = [[
            'ID' => [['_' => 'urn:oasis:names:specification:ubl:signature:Invoice']],
            'SignatureMethod' => [['_' => 'urn:oasis:names:specification:ubl:dsig:enveloped:xades']],
        ]];

        return $this->minify($payload);
    }

    /**
     * @return array{cert: string, pkey: \OpenSSLAsymmetricKey|resource}
     */
    private function readPkcs12(object $tenant): array
    {
        $raw = decrypt($tenant->myinvois_cert);
        $password = decrypt($tenant->myinvois_cert_password);
        $binary = base64_decode($raw, true);
        if ($binary === false) {
            $binary = $raw;
        }
        $certs = [];
        if (! openssl_pkcs12_read($binary, $certs, $password)) {
            throw new \LogicException('Could not read the MyInvois PKCS#12 certificate. Check the file and password.');
        }

        return $certs;
    }

    private function certificateDer(string $pem): string
    {
        $body = preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $pem);

        return base64_decode($body) ?: '';
    }

    /**
     * @param  array<string, mixed>  $dn
     */
    private function dnString(array $dn): string
    {
        $parts = [];
        foreach (['CN', 'OU', 'O', 'L', 'ST', 'C'] as $key) {
            if (! isset($dn[$key])) {
                continue;
            }
            $value = $dn[$key];
            if (is_array($value)) {
                $value = implode('+', $value);
            }
            $parts[] = $key.'='.$value;
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function serialDecimal(array $parsed): string
    {
        $hex = (string) ($parsed['serialNumberHex'] ?? '');
        if ($hex === '' && isset($parsed['serialNumber'])) {
            return (string) $parsed['serialNumber'];
        }
        $hex = ltrim($hex, '0');
        if ($hex === '') {
            return '0';
        }
        if (function_exists('gmp_init')) {
            return gmp_strval(gmp_init($hex, 16), 10);
        }

        return (string) hexdec($hex);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function minify(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
