# API: Sync Insurance Order

## Product decisions

1. **Insurance purchase** → independent order in `orders` (`service_type = insurance`). ✅
2. **Insurance with a flight** → **separate insurance order** (not an item inside the flight order).  
   Optionally link via:
   ```json
   "metadata": {
     "related_flight_order_id": 123,
     "related_flight_booking_id": "BN-FLIGHT-ID"
   }
   ```

## Endpoint

```http
POST /api/v1/orders/sync-insurance
Authorization: Bearer {sanctum_token}
Content-Type: application/json
```

- **Idempotent** by `provider_booking.booking_id` (same key → update, HTTP 200).
- First sync → HTTP **201**.
- Customer is always taken from the auth token (`customer_id` is prohibited).
- `items[].item_details.item_id` is **required** (used later for policy PDF).

## Sample request

```json
{
  "source": "mobile_app",
  "product_type": "insurance",
  "status": "confirmed",
  "currency": "LYD",
  "grand_total": 150.0,
  "contact": {
    "name": "Ahmed Ali",
    "email": "ahmed@example.com",
    "phone": "+218..."
  },
  "provider_booking": {
    "booking_id": "12",
    "provider": "booknow_insurance",
    "order_id": "12",
    "order_number": "ORD-2026-0002"
  },
  "items": [
    {
      "type": "insurance",
      "product_type": "insurance",
      "product_subtype": "travel",
      "title": "Travel Insurance · 7 Days",
      "quantity": 1,
      "unit_price": 150.0,
      "item_details": {
        "item_id": "34",
        "provider": "albaraka",
        "ticket_number": "CMP-58737",
        "report_reference": "ENC789",
        "zone_id": 1,
        "zone_name": "Europe",
        "duration_id": 1,
        "duration_label": "7 Days",
        "policy_date_from": "2026-08-01",
        "policy_date_to": "2026-08-07"
      }
    }
  ],
  "payment": {
    "method": "card",
    "amount": 150.0,
    "currency": "LYD",
    "transaction_id": "ins_..."
  },
  "metadata": {
    "source_screen": "mobile_app",
    "booknow_insurance_item_id": "34",
    "related_flight_order_id": 123,
    "related_flight_booking_id": "BN-FLIGHT-ID"
  }
}
```

## Required / important fields

| Field | Why |
|-------|-----|
| `provider_booking.booking_id` | Idempotency key (= BookNow order.id) |
| `product_type: insurance` | My Orders filtering |
| `items[].item_details.item_id` | Load policy PDF later |
| `ticket_number` / `report_reference` | Document display |
| `related_flight_*` | Optional but important when bought with a flight |

## Sample success response (201)

```json
{
  "success": true,
  "message": "Order saved successfully.",
  "data": {
    "cpbooke_id": 987,
    "id": "12",
    "number": "CP0001BA",
    "product_type": "insurance",
    "service_type": "insurance",
    "status": "confirmed",
    "internal_status": "confirmed",
    "grand_total": "150.00",
    "currency": "LYD",
    "insurance": {
      "title": "Travel Insurance · 7 Days",
      "product_subtype": "travel",
      "item_id": "34",
      "provider": "albaraka",
      "ticket_number": "CMP-58737",
      "report_reference": "ENC789",
      "zone_id": 1,
      "zone_name": "Europe",
      "duration_id": 1,
      "duration_label": "7 Days",
      "policy_date_from": "2026-08-01",
      "policy_date_to": "2026-08-07",
      "quantity": 1
    },
    "items": [ ... ],
    "payment": { ... },
    "metadata": { ... }
  },
  "meta": {
    "created": true,
    "idempotent": false
  }
}
```

Key fields for the app:

| Field | Meaning |
|-------|---------|
| `data.cpbooke_id` | Internal CPBooke order id |
| `data.number` | CPBooke order number (e.g. `CP0001BA`) |
| `data.id` | Provider booking id (same as `booking_id`) |
| `data.insurance.item_id` | BookNow insurance item id (policy PDF) |
| `data.insurance.ticket_number` | Document number |

Notes:

- If `status` is `confirmed`/`paid`/`ticketed`/`completed` and `payment.status` is omitted, CPBooke treats payment as **paid**.
- Retry with the same `booking_id` returns **200** and `meta.idempotent: true`.

## List & detail

```http
GET /api/v1/orders
GET /api/v1/orders/{cpbooke_id}
```

Both return insurance orders with:

- `product_type` / `service_type`: `"insurance"`
- `insurance`: `{ item_id, ticket_number, report_reference, zone_name, policy_date_from, ... }`

## Local test (Postman)

1. Login customer → Sanctum token  
2. `POST http://127.0.0.1:8000/api/v1/orders/sync-insurance` with sample body  
3. Expect **201** + `cpbooke_id` + `number`  
4. Repeat same `booking_id` → **200** + `meta.idempotent: true`  
5. `GET /api/v1/orders` → see insurance in My Orders  

Related:

- Flight: `POST /api/v1/orders/sync-flight`
- eSIM: `POST /api/v1/orders/sync-esim`
