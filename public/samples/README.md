# Sample assets

Files in this directory are static, version-controlled assets used by the
super-admin OCR test feature (`/admin/ocr` → "Test with sample receipt").

## `receipt-sample.jpg`

A small (≤ 200 KB), readable receipt image used to verify that the active
OCR provider is wired correctly. **Drop a JPG file with this exact name**
to enable the test button. Suggestions:

- A scan or photo of a real Malaysian receipt (RM amounts, dd/mm/yyyy date)
- ~ 800–1200px on the long edge
- Reasonable contrast, not crumpled

If the file is missing, `POST /admin/ocr/test` returns a 422 with a friendly
"sample receipt not found" message instead of crashing.
