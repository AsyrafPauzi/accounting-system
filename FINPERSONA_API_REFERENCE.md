# BukuCloud API for Fin Persona

## Step 1 — Get API Key (BukuCloud user)

1. Log in to BukuCloud
2. Go to **Settings → API & Integrations**
3. Click **Generate API key**
4. Copy the key immediately (shown once only)
5. Paste the key into Fin Persona

Example key format:

```text
pk_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

## Step 2 — Use API Key (Fin Persona)

Every request must include:

```http
Authorization: Bearer <api_key>
Accept: application/json
```

Replace `<api_key>` with the key from Step 1.

## Base URL

```text
https://app.bukucloud.com
```

## API URLs

### Transactions

```text
GET https://app.bukucloud.com/api/v1/transactions
```

With filters:

```text
GET https://app.bukucloud.com/api/v1/transactions?start_date=2026-01-01&end_date=2026-06-30&per_page=100
```

### Invoices

```text
GET https://app.bukucloud.com/api/v1/invoices
```

With filters:

```text
GET https://app.bukucloud.com/api/v1/invoices?status=paid&per_page=50
```

### Bills

```text
GET https://app.bukucloud.com/api/v1/bills
```

With filters:

```text
GET https://app.bukucloud.com/api/v1/bills?status=unpaid&per_page=50
```

### Customers

```text
GET https://app.bukucloud.com/api/v1/customers
```

With filters:

```text
GET https://app.bukucloud.com/api/v1/customers?is_active=true&search=abc&per_page=50
```

### Suppliers

```text
GET https://app.bukucloud.com/api/v1/suppliers
```

With filters:

```text
GET https://app.bukucloud.com/api/v1/suppliers?is_active=true&per_page=50
```

## cURL Examples

Invoices:

```bash
curl -X GET "https://app.bukucloud.com/api/v1/invoices?per_page=50" \
  -H "Authorization: Bearer pk_live_xxx" \
  -H "Accept: application/json"
```

Bills:

```bash
curl -X GET "https://app.bukucloud.com/api/v1/bills?per_page=50" \
  -H "Authorization: Bearer pk_live_xxx" \
  -H "Accept: application/json"
```

Transactions:

```bash
curl -X GET "https://app.bukucloud.com/api/v1/transactions?start_date=2026-01-01&end_date=2026-06-30" \
  -H "Authorization: Bearer pk_live_xxx" \
  -H "Accept: application/json"
```

## Response Format

All list endpoints return:

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 50,
    "total": 120,
    "last_page": 3
  }
}
```

Next page:

```text
GET https://app.bukucloud.com/api/v1/invoices?page=2&per_page=50
```

## Query Parameters

| Endpoint | Parameters |
| --- | --- |
| transactions | `start_date`, `end_date`, `account`, `search`, `per_page` (max 500) |
| invoices | `status`, `customer_id`, `start_date`, `end_date`, `per_page` (max 200) |
| bills | `status`, `supplier_id`, `start_date`, `end_date`, `per_page` (max 200) |
| customers | `is_active`, `search`, `per_page` (max 200) |
| suppliers | `is_active`, `search`, `per_page` (max 200) |

Invoice/bill status values:

```text
draft, posted, paid, partial, overdue, void
```

Date format:

```text
YYYY-MM-DD
```

## Limits

```text
120 requests per minute per API key
```

BukuCloud tenant must be on **Solo plan or above**.

## Errors

Invalid or revoked key:

```json
{
  "error": "invalid_token",
  "error_description": "API key is invalid or has been revoked."
}
```

HTTP status: `401`

## Revoke Access

BukuCloud user can revoke the key anytime from:

```text
Settings → API & Integrations → Revoke
```

After revoke, all API calls return `401`.

## Fin Persona Implementation

1. Add input field for BukuCloud API key
2. Save key securely on backend
3. Call the 5 GET URLs above with Bearer token
4. Read `data` array from response
5. Use `meta.last_page` for pagination
6. Handle `401` if key is revoked

## Contact

```text
Fin Persona backend: aizuddin@hirix.ai
```
