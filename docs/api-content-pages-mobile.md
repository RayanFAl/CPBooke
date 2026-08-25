# Mobile handover — Content pages (legal + product policies)

Public CMS pages for settings and checkout. **No login and no token.** Envelope: `{ success, message, data, meta }`.

**Fare rules stay with the provider** (`/flights/fare-rules`, hotel room cancellation). CMS is **company text only**.

**Open legal pages in the system browser** (Chrome / Safari), not inside a WebView:

```
Android: Intent.ACTION_VIEW / Custom Tabs
iOS:     SFSafariViewController or UIApplication.open
Flutter: url_launcher → LaunchMode.externalApplication
RN:      Linking.openURL(url)
```

Use the `url` field from the API. Do not hardcode production hosts in the app if you can avoid it — `url` already includes `APP_URL`.

---

## Public web URLs (HTML, no auth)

Two screens only. Header is **Booke**. No language switcher and no section chips. Locale comes from `?locale=ar` or `?locale=en` (the mobile app sends it).

Replace `{APP_URL}` with the live site (`https://...`). Local example: `http://127.0.0.1:8000`.

| Screen | Contents | Arabic | English |
|--------|----------|--------|---------|
| Privacy | Privacy policy + flight + hotel + insurance + eSIM | `{APP_URL}/pages?locale=ar` | `{APP_URL}/pages?locale=en` |
| Terms | Terms and conditions only | `{APP_URL}/pages/terms-of-service?locale=ar` | `{APP_URL}/pages/terms-of-service?locale=en` |

Same privacy screen:

- `{APP_URL}/pages/privacy-policy?locale=ar`
- `{APP_URL}/privacy-policy`
- `{APP_URL}/pages/product/flight`

Same terms screen: `{APP_URL}/terms`

Settings → Privacy: `data.legal['privacy-policy'].url`  
Settings → Terms: `data.legal['terms-of-service'].url`

Admin still edits each section separately.

---

**One workspace call** for mobile cache/bootstrap:

```http
GET /api/v1/pages/workspace?locale=ar
```

Returns grouped `legal` + `products` keys. Each screen can still call `/api/v1/pages/product/{product}` or `/api/v1/pages/{slug}` if it only needs one page.

---

## Admin fields

| Field | Values | Notes |
|-------|--------|--------|
| `category` | `legal` or `product_policy` | Required |
| `product` | `flight` / `hotel` / `insurance` / `esim` | Required only for `product_policy` |
| `title` | text | Arabic + English |
| `body` | HTML | Rendered on the public web page |
| `slug` | for legal pages | `privacy-policy`, `terms-of-service` |
| `url` | optional override | If empty, API returns the public `/pages/{slug}` link. If set to `https://...`, that link is used instead |

One product policy page per product (**4 pages only**).

Fixed legal pages:

- `privacy-policy`
- `terms-of-service`

---

## Public APIs (no auth)

Product policies — one request per product at booking/payment:

```http
GET /api/v1/pages/product/flight?locale=ar
GET /api/v1/pages/product/hotel?locale=ar
GET /api/v1/pages/product/insurance?locale=ar
GET /api/v1/pages/product/esim?locale=ar
```

Legal — settings and payment:

```http
GET /api/v1/pages/privacy-policy?locale=ar
GET /api/v1/pages/terms-of-service?locale=ar
GET /api/v1/pages?category=legal
GET /api/v1/pages?category=product_policy
```

Also supports `Accept-Language: ar`. Query `locale` wins when present.

---

## Response shape

```json
{
  "success": true,
  "data": {
    "title": "سياسة الخصوصية",
    "body": "<h1>...</h1>",
    "category": "legal",
    "product": null,
    "slug": "privacy-policy",
    "url": "https://example.com/pages?locale=ar",
    "updated_at": "2026-08-18T12:00:00Z"
  }
}
```

List endpoints return `data` as an array of the same objects.

Workspace response (`GET /api/v1/pages/workspace`):

```json
{
  "success": true,
  "data": {
    "legal": {
      "privacy-policy": { "title": "...", "body": "...", "slug": "privacy-policy", "category": "legal", "product": null, "url": "https://example.com/pages?locale=ar", "updated_at": "..." },
      "terms-of-service": { ... }
    },
    "products": {
      "flight": { "title": "...", "body": "...", "slug": "flight-policy", "category": "product_policy", "product": "flight", "url": "https://example.com/pages?locale=ar", "updated_at": "..." },
      "hotel": { ... },
      "insurance": { ... },
      "esim": { ... }
    }
  }
}
```

Mobile usage:

- Bootstrap once: `GET /api/v1/pages/workspace?locale=ar` → cache locally.
- Settings privacy: open `data.legal['privacy-policy'].url` in Chrome / Safari.
- Settings terms: open `data.legal['terms-of-service'].url` in Chrome / Safari.
- Checkout product: open `data.products.flight.url` (or hotel / insurance / esim) the same way.

| Field | Rule |
|-------|------|
| `url` | Always present for active pages. Open this in the **external browser**. |
| `body` | HTML on the public web page. You do not need to render it in-app if you open `url`. |
| Missing page | **404**. Hide the section. Do **not** block booking. |

Product slugs: `flight-policy`, `hotel-policy`, `insurance-policy`, `esim-policy`.

---

## Cache

Responses include `ETag` and `Cache-Control: public, max-age=60`. Send `If-None-Match` for `304`.
