# BukuCloud API

Interactive reference (OpenAPI, auth, code samples, Try Request):

**https://bukucloud.com/api-reference**

Generate an API key and signing key in **Settings → API & Integrations** (shown once). GET feeds use `Authorization: Bearer <api_key>`. POST writes also require HMAC headers signed with the signing key.

The machine-readable spec in this repo is [`openapi.yaml`](openapi.yaml). Keep it aligned with `bukucloud-website/public/openapi.yaml` when endpoints change.
