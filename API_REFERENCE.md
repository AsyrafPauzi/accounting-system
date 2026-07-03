# BukuCloud API Reference

Read-only JSON API for external apps.

## Step 1 — Get API Key

1. Log in to BukuCloud
2. Go to **Settings → API & Integrations**
3. Click **Generate API key**
4. Copy the key immediately (shown once only)
5. Paste the key into your app

Example key format:

```text
pk_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

## Step 2 — Authenticate

Every request must include:

```http
Authorization: Bearer <api_key>
Accept: application/json
```

## Base URL

```text
https://app.bukucloud.com
```

## Endpoints

```text
GET /api/v1/transactions
GET /api/v1/invoices
GET /api/v1/bills
GET /api/v1/customers
GET /api/v1/suppliers
```

## Full URLs

```text
GET https://app.bukucloud.com/api/v1/transactions
GET https://app.bukucloud.com/api/v1/invoices
GET https://app.bukucloud.com/api/v1/bills
GET https://app.bukucloud.com/api/v1/customers
GET https://app.bukucloud.com/api/v1/suppliers
```

### With filters

```text
GET https://app.bukucloud.com/api/v1/transactions?start_date=2026-01-01&end_date=2026-06-30&per_page=100
GET https://app.bukucloud.com/api/v1/invoices?status=paid&per_page=50
GET https://app.bukucloud.com/api/v1/bills?status=unpaid&per_page=50
GET https://app.bukucloud.com/api/v1/customers?is_active=true&search=abc
GET https://app.bukucloud.com/api/v1/suppliers?is_active=true&per_page=50
```

## cURL Example

```bash
curl -X GET "https://app.bukucloud.com/api/v1/invoices?per_page=50" \
  -H "Authorization: Bearer pk_live_xxx" \
  -H "Accept: application/json"
```

## Response

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

Status values for invoices/bills:

```text
draft, posted, paid, partial, overdue, void
```

Date format: `YYYY-MM-DD`

## Rate Limit

```text
600 requests per minute per API key (default)
```

Configurable on the server:

```text
API_RATE_LIMIT_PER_MINUTE=600
```

Tenant must be on **Solo plan or above**.

## Errors

```json
{
  "error": "invalid_token",
  "error_description": "API key is invalid or has been revoked."
}
```

HTTP status: `401`

## Revoke Key

```text
Settings → API & Integrations → Revoke
```

## Integration Checklist

- [ ] Add API key input in your app
- [ ] Store key securely on backend
- [ ] Send `Authorization: Bearer <api_key>` on every request
- [ ] Call the 5 GET endpoints
- [ ] Handle pagination via `meta.last_page`
- [ ] Handle `401` when key is revoked
