# API: Sync Bundle Order

## Product decision

One **CPBooke order after payment**, with multiple items:

- `flight` (and seats inside flight `item_details`)
- `esim` (one or more)
- `insurance` (one or more)

Not three separate CPBooke orders.

BookNow issue APIs stay separate (`/flights/book`, `/esim/book`, `/insurance/travel/issue`).  
CPBooke unification happens only after those succeed, via this sync.

## Endpoint

```http
POST /api/v1/orders/sync-bundle
Authorization: Bearer {sanctum_token}
Content-Type: application/json
```

- **Idempotent** by `provider_booking.booking_id` (primary **flight** booking id).
- First sync → HTTP **201**.
- Retry same booking id → HTTP **200**, `meta.idempotent = true`.
- Customer always from auth token (`customer_id` prohibited).
- `product_type` is forced to `flight`; `metadata.bundle` is forced to `true`.

## Sample request

```json
{
  "source": "mobile_app",
  "product_type": "flight",
  "status": "confirmed",
  "currency": "LYD",
  "grand_total": 850.0,
  "provider_booking": {
    "booking_id": "BN-FLIGHT-123",
    "provider": "booknow",
    "pnr": "ABC123"
  },
  "contact": { "name": "Ahmed Ali", "email": "ahmed@example.com", "phone": "+218..." },
  "passengers": [
    { "type": "adult", "first_name": "Ahmed", "last_name": "Ali" }
  ],
  "items": [
    {
      "type": "flight",
      "product_type": "ticket",
      "title": "TIP → MJI",
      "unit_price": 600.0,
      "item_details": {
        "item_id": "11",
        "seats": { "0": { "0": "12A" } },
        "pnr": "ABC123"
      }
    },
    {
      "type": "esim",
      "product_type": "esim",
      "title": "Turkey 3GB / 7 Days",
      "unit_price": 100.0,
      "item_details": {
        "item_id": "esim-item-1",
        "booking_uuid": "...",
        "iccid": "...",
        "qr": "LPA:1$..."
      }
    },
    {
      "type": "insurance",
      "product_type": "insurance",
      "product_subtype": "travel",
      "title": "Travel Insurance · 7 Days",
      "unit_price": 150.0,
      "item_details": {
        "item_id": "34",
        "order_id": "12",
        "ticket_number": "CMP-58737",
        "report_reference": "ENC789",
        "duration_id": 1,
        "zone_id": 1,
        "policy_date_from": "2026-08-01",
        "policy_date_to": "2026-08-07"
      }
    }
  ],
  "payment": {
    "method": "card",
    "amount": 850.0,
    "currency": "LYD",
    "transaction_id": "txn_..."
  },
  "metadata": {
    "bundle": true,
    "booknow_flight_order_id": "...",
    "booknow_insurance_order_id": "12",
    "booknow_esim_booking_ids": ["..."]
  }
}
```

## Item rules

Every item needs:

| Field | Why |
|-------|-----|
| `type` / `product_type` | Section rendering |
| `item_details.item_id` | BookNow id for PDF / refund / status |
| price (`unit_price` / `total`) + currency | Display & totals |

Insurance specifically:

- `order_id` + `item_id` → policy PDF
- `ticket_number` / `report_reference` → document UI

eSIM specifically:

- `item_id` or `booking_uuid`
- `qr` / `activation_code` / `iccid`

Bundle must include **at least one** `type: flight` item.

## Sample success response (201)

```json
{
  "success": true,
  "message": "Order saved successfully.",
  "data": {
    "cpbooke_id": 987,
    "id": "BN-FLIGHT-123",
    "number": "CP0001BA",
    "product_type": "flight",
    "is_bundle": true,
    "status": "confirmed",
    "grand_total": "850.00",
    "currency": "LYD",
    "flight": { "item_id": "11", "pnr": "ABC123", "seats": { "0": { "0": "12A" } } },
    "seats": { "0": { "0": "12A" } },
    "esims": [{ "item_id": "esim-item-1", "qr": "LPA:1$..." }],
    "insurances": [{ "item_id": "34", "order_id": "12", "ticket_number": "CMP-58737" }],
    "esim": { "...first esim..." },
    "insurance": { "...first insurance..." },
    "items": [ "...full items array..." ],
    "metadata": { "bundle": true }
  },
  "meta": {
    "created": true,
    "idempotent": false
  }
}
```

## List & detail

```http
GET /api/v1/orders
GET /api/v1/orders/{cpbooke_id}
```

BookNow-synced orders (including bundles) return the same BookNow-shaped payload with:

- `is_bundle`
- `items[]`
- `flight` / `seats` / `esims` / `insurances`

## Mobile flow

1. Pay successfully (mocked for now)
2. `/flights/book`
3. `/esim/book` (if needed)
4. `/insurance/travel/issue`
5. `POST /orders/sync-bundle` ← one local order
6. Open unified ticket / order details screen

Standalone endpoints remain available:

- `POST /orders/sync-flight`
- `POST /orders/sync-esim`
- `POST /orders/sync-insurance`
