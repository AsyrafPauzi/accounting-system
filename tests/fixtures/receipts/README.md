# OCR Test Corpus

Drop real receipts/invoices into this folder to measure parser accuracy.

## Adding a fixture

1. Save the receipt as `<descriptive-name>.jpg`, `.png`, or `.pdf`. Keep names lowercase, hyphenated, no spaces.
2. (Optional but strongly recommended) Create a sidecar `<descriptive-name>.expected.json` with the values the parser SHOULD return. Without this, the audit can only show you what was extracted — not whether it's correct.

## Sidecar schema

```json
{
  "vendor_name": "Kedai Maju Sdn Bhd",
  "bill_date": "2025-05-21",
  "subtotal": 22.00,
  "tax_amount": 1.76,
  "total_amount": 23.76,
  "currency": "MYR",
  "reference": "RCP250521-001",
  "items_count": 4
}
```

All fields are optional. Set a field to `null` if the receipt genuinely doesn't have it (e.g. a simple cash receipt with no reference number). Omit the field entirely if you don't care to validate it.

`items_count` is enough — we don't ask you to label every line item by hand.

## Running the audit

```sh
php artisan ocr:audit
```

Optional filter:

```sh
php artisan ocr:audit --filter=kedai
```

The command processes every fixture, compares against expected, and prints a table with per-receipt and per-field accuracy. Useful before merging any change to OCR code.

## Privacy reminder

These fixtures live in the repo. **Do not commit anything containing personal data** (full IC numbers, full bank account numbers, customer addresses you don't have permission to share). Black out sensitive fields with an image editor before adding.
