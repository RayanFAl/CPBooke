# API: Sync eSIM Order

## Product decisions

1. **eSIM from store** → independent order in `orders` (`service_type = esim`). ✅
2. **eSIM with a flight** → **separate eSIM order** (not an item inside the flight order).  
   Optionally link via:
   ```json
   "metadata": {
     "related_flight_order_id": 1452,
     "related_flight_booking_id": "01ktgspk..."
   }
   ```

## Endpoint

```http
POST /api/v1/orders/sync-esim
Authorization: Bearer {sanctum_token}
Content-Type: application/json
```

- **Idempotent** by `provider_booking.booking_id` (same key → update, HTTP 200).
- First sync → HTTP **201**.
- Customer is always taken from the auth token (`customer_id` is prohibited).

## Sample request

```json
{
  "source": "mobile_app",
  "product_type": "esim",
  "status": "confirmed",
  "currency": "USD",
  "grand_total": 12.5,
  "contact": {
    "name": "Rayan Fathi",
    "email": "a.rayan@median.ly"
  },
  "provider_booking": {
    "booking_id": "esim-booking-uuid-001",
    "provider": "booknow_esim",
    "order_id": "BN-ESIM-7788"
  },
  "items": [
    {
      "type": "esim",
      "title": "Tunisia 1GB 30 Days",
      "quantity": 1,
      "unit_price": 12.5,
      "item_details": {
        "country": "TN",
        "data": "1GB",
        "validity_days": 30,
        "iccid": "8901234567890123456",
        "activation_code": "ACTIVATION-CODE-001",
        "qr": "LPA:1$example.com$ACTIVATION"
      }
    }
  ],
  "payment": {
    "method": "wallet",
    "status": "paid",
    "amount": 12.5,
    "currency": "USD"
  }
}
```

## Sample success response (201)

```json
{
  "success": true,
  "message": "Order saved successfully.",
  "data": {
    "cpbooke_id": 1,
    "id": "esim-booking-uuid-001",
    "number": "CP0001BA",
    "product_type": "esim",
    "service_type": "esim",
    "status": "confirmed",
    "internal_status": "confirmed",
    "grand_total": "12.50",
    "currency": "USD",
    "esim": {
      "title": "Tunisia 1GB 30 Days",
      "country": "TN",
      "data": "1GB",
      "validity_days": 30,
      "iccid": "8901234567890123456",
      "activation_code": "ACTIVATION-CODE-001",
      "qr": "LPA:1$example.com$ACTIVATION"
    },
    "items": [ ... ],
    "payment": { ... }
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
| `data.esim.qr` | QR / LPA string for display |

## List & detail

```http
GET /api/v1/orders
GET /api/v1/orders/{cpbooke_id}
```

Both return eSIM orders with:

- `product_type` / `service_type`: `"esim"`
- `esim`: `{ title, country, data, validity_days, iccid, activation_code, qr }`

## Local test (Postman)

1. Login customer → Sanctum token  
2. `POST http://127.0.0.1:8000/api/v1/orders/sync-esim` with sample body  
3. Expect **201** + `cpbooke_id` + `number`  
4. Repeat same `booking_id` → **200** + `meta.idempotent: true`  
5. `GET /api/v1/orders` → see the eSIM in My Orders  

Related flight sync remains: `POST /api/v1/orders/sync-flight`
