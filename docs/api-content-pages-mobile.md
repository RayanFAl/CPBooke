# Mobile handover — Content pages (legal + product policies)

Public CMS pages for settings and checkout. No login. Envelope: `{ success, message, data, meta }`.

**Fare rules stay with the provider** (`/flights/fare-rules`, hotel room cancellation). CMS is **company text only** and is shown beside those terms.

---

## Admin fields

| Field | Values | Notes |
|-------|--------|--------|
| `category` | `legal` or `product_policy` | Required |
| `product` | `flight` / `hotel` / `insurance` / `esim` | Required only for `product_policy` |
| `title` | text | Arabic + English |
| `body` | HTML | What the app renders (headings, lists, links) |
| `slug` | for legal pages | `privacy-policy`, `terms-of-service` |
| `url` | optional | Public `https://` page. If set, open it instead of `body` |

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
    "title": "سياسة حجز الطيران",
    "body": "<p>...</p>",
    "category": "product_policy",
    "product": "flight",
    "slug": "flight-policy",
    "url": null,
    "updated_at": "2026-08-18T12:00:00Z"
  }
}
```

List endpoints return `data` as an array of the same objects.

| Field | Rule |
|-------|------|
| `body` | HTML. Render as a web page. |
| `url` | Optional. If it is an `https://...` link, open it in-app instead of `body`. |
| Missing page | **404**. Hide the section. Do **not** block booking. |

Product slugs: `flight-policy`, `hotel-policy`, `insurance-policy`, `esim-policy`.

---

## Cache

Responses include `ETag` and `Cache-Control: public, max-age=60`. Send `If-None-Match` for `304`.
