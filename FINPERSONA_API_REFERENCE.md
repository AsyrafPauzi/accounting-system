# BukuCloud API for Fin Persona

No OAuth. No BukuCloud env setup for Fin Persona.

## How to Connect

### BukuCloud user

1. Go to **Settings → API & Integrations**
2. Click **Generate API key**
3. Copy the key (shown once)
4. Paste it into Fin Persona

### Fin Persona

1. Save the API key in Fin Persona backend
2. Call the API with:

```http
Authorization: Bearer pk_live_xxx
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

## Example

```bash
curl -H "Authorization: Bearer pk_live_xxx" \
  "https://app.bukucloud.com/api/v1/invoices?per_page=50"
```

## Response

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 50,
    "total": 0,
    "last_page": 1
  }
}
```

Use `?page=2` for the next page.

## Optional Filters

```text
transactions: start_date, end_date, account, search, per_page
invoices: status, customer_id, start_date, end_date, per_page
bills: status, supplier_id, start_date, end_date, per_page
customers: is_active, search, per_page
suppliers: is_active, search, per_page
```

## Limits

```text
120 requests per minute per API key
```

Tenant must be on Solo plan or above.

## Errors

```json
{
  "error": "invalid_token",
  "error_description": "API key is invalid or has been revoked."
}
```

## Fin Persona Checklist

- [ ] Add field for user to paste BukuCloud API key
- [ ] Store API key securely on backend
- [ ] Send `Authorization: Bearer <api_key>` on every request
- [ ] Call the 5 GET endpoints above
- [ ] Handle pagination

## BukuCloud Checklist

- [ ] No `FINPERSONA_*` env vars needed
- [ ] Deploy the Integrations page with **Generate API key**
- [ ] Tenant generates key and shares it with Fin Persona manually

## Contact

```text
Fin Persona backend: aizuddin@hirix.ai
```
