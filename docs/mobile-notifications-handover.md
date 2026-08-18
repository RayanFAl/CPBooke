# Mobile handover — Notifications Action Engine (Aug 2026)

Give this to the mobile team. Base path: `/api/v1/notifications` (Sanctum). Envelope: `{ success, message, data, meta }`.

Full contract: [`api-notifications-mobile.md`](./api-notifications-mobile.md).

---

## Must implement now

### 1. Inbox card is Notification + Actions

Do **not** render title/body only. Read `meta.actions[]` and show buttons.

```json
{
  "id": "123",
  "title": "تم إلغاء رحلتك إلى تونس",
  "body": "يمكنك اختيار رحلة بديلة أو طلب استرداد المبلغ.",
  "type": "flight",
  "is_read": false,
  "created_at": "2026-08-18T12:00:00+00:00",
  "deep_link": "/my-orders/123",
  "meta": {
    "order_id": "123",
    "product_type": "flight",
    "template_code": "FLIGHT_CANCELLED",
    "family": "operational",
    "category": "flights",
    "severity": "critical",
    "recipient": "passenger",
    "channels": ["push", "in_app"],
    "expires_at": "2026-08-18T18:00:00+00:00",
    "action_engine": true,
    "from_value": null,
    "to_value": null,
    "actions": [
      { "code": "view_alternatives", "label": "View alternative flights", "label_ar": "عرض الرحلات البديلة", "deep_link": "/flights" },
      { "code": "request_refund", "label": "Request refund", "label_ar": "طلب استرداد", "deep_link": "/my-orders/123" },
      { "code": "contact_support", "label": "Contact support", "label_ar": "التواصل مع الدعم", "deep_link": "/support?order_id=123" }
    ],
    "route": "Tripoli → Tunis",
    "journey_card": true,
    "stage": "before_departure"
  }
}
```

Rules:

- Use `label_ar` when app locale is Arabic.
- Navigate with `action.deep_link` (fallback: item `deep_link`).
- If `expires_at` is in the past, hide/disable CTAs (card can stay in inbox).
- `severity`: `critical` = prominent (gate / boarding / cancel). `warning` = highlight change. `info` = normal.
- `family`: `operational` / `transactional` are not marketing — do not treat as promo.

### 2. Action codes → screens

| `actions[].code` | Open |
|------------------|------|
| `view_flight` / `view_order` / `open` | Order details |
| `view_alternatives` | Flight search (usually `/flights`) |
| `request_refund` / `view_refund` | Order / refund |
| `contact_support` | Support (`?order_id=`) |
| `check_in` | Check-in (`/my-orders/{id}/check-in`) |
| `complete_payment` / `retry_payment` | Payment on the order |
| `cancel_booking` / `view_hotel` | Hotel booking |
| `upload_document` | `/profile/passengers` |
| `add_baggage` | `/my-orders/{id}/baggage` |
| `view_seats` | `/my-orders/{id}/seats` |
| `open_esim` | `/esim` |
| `open_wallet` | `/wallet` |

Unknown `code`: still open `deep_link`. Do not crash.

### 3. Inbox filters

`GET /notifications?category=`

New categories: **`wallet`**, **`documents`** (plus existing `flights`, `hotels`, `payments`, `insurance`, `esim`, `offers`, `security`).

`meta.unread_by_category` on the list response:

```json
"unread_by_category": {
  "flights": 2,
  "hotels": 0,
  "payments": 1,
  "insurance": 0,
  "esim": 0,
  "offers": 0,
  "security": 0,
  "wallet": 1,
  "documents": 0
}
```

### 4. Journey card (same notification, extra offer)

If `meta.journey_card` / `meta.next_best_offer` exists, show **one** extra CTA on the same card. Do not wait for a second push.

```json
"next_best_offer": {
  "code": "OFFER_ESIM",
  "title": "Need internet in Tunis?",
  "body": "Activate an eSIM before you land.",
  "deep_link": "/esim?country=TN",
  "reason": "missing_esim"
}
```

Optional: `meta.checklist[]` (`code`, `ready`, `label`, `label_ar`).

### 5. Report every flight search

After a flight search (even if the user does not book):

```http
POST /api/v1/notifications/search-intents
Authorization: Bearer {token}

{
  "origin": "TIP",
  "destination": "TUN",
  "departure_date": "2026-09-20",
  "lowest_price": 1250,
  "currency": "LYD"
}
```

Price watch (user opt-in):

```http
GET    /api/v1/notifications/price-alerts
POST   /api/v1/notifications/price-alerts
{ "origin": "TIP", "destination": "TUN", "departure_date": "2026-10-01", "target_price": 800, "currency": "LYD" }
DELETE /api/v1/notifications/price-alerts/{id}
```

### 6. Device register — send app version

```http
POST /api/v1/notifications/devices
{ "device_token": "FCM_TOKEN", "platform": "android|ios", "app_version": "1.x.x" }
```

---

## New pushes you will start receiving

Handle by `meta.template_code` + `deep_link` / `actions`. Do not hard-code copy.

| Code | Meaning | Typical CTA |
|------|---------|-------------|
| `ONLINE_CHECKIN_OPEN` | Check-in opened (~48h) | Check-in |
| `GATE_ASSIGNED` | Gate first assigned (critical) | View flight |
| `FLIGHT_GATE_CHANGED` | Gate changed (critical) | View flight |
| `BOARDING_STARTED` / `BOARDING_FINAL_CALL` / `BOARDING_CLOSED` | Boarding (critical) | View flight |
| `BOARDING_PASS_AVAILABLE` | Boarding pass ready | View flight |
| `SEAT_ASSIGNED` / `SEAT_CHANGED` | Seat | View flight |
| `PASSPORT_EXPIRY_REMINDER` | Passport expiring | Add document |
| `HOTEL_CHECKOUT_REMINDER` | Checkout tomorrow | View hotel |
| `ESIM_ACTIVATION_REMINDER` | Activate eSIM | Open eSIM |
| `WALLET_TOPUP_SUCCESS` / `WALLET_DEBIT` / `WALLET_REFUND` / `WALLET_LOW_BALANCE` | Wallet | Open wallet |
| `FLIGHT_CANCELLED` | Airline cancelled | Alternatives + refund + support |

24h / 3h flight reminders now include a **Check-in** action on the same card.

---

## Push payload (FCM)

Unchanged keys: `deep_link`, `type`, `order_id`, `notification_id`, `click_action`.  
Android channel: `cpbooke_default`.  
On tap: open `deep_link`, then refresh inbox for actions.

---

## Not this release (catalog only)

Do not wait for product APIs for: visa upload, extra baggage purchase, seat upgrade confirm, eSIM data usage, loyalty points, linked-account payment requests. Templates exist; events will come later. If `actions[]` appears, still render the buttons.

---

## Checklist for QA

- [ ] Inbox shows 1–3 action buttons from `meta.actions`
- [ ] Arabic uses `label_ar`
- [ ] Expired `expires_at` disables CTAs
- [ ] Critical gate/cancel cards look urgent
- [ ] Tabs: wallet + documents + unread badges
- [ ] Search screen POSTs `search-intents`
- [ ] Device register sends `app_version`
- [ ] Journey reminder shows `next_best_offer` on the same card
- [ ] Old clients still work: missing `actions` → tap whole row → `deep_link`
