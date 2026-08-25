# Mobile Notifications — Backend Contract

> Full spec (AR): [`notifications-backend-spec.md`](./notifications-backend-spec.md) — all events, OTP, scheduler, DB schema, and phased checklist.

Base path: `/api/v1/notifications` (Sanctum). Response envelope: `{ success, message, data, meta }`.

## Endpoints

| Method | Path | Notes |
|--------|------|--------|
| GET | `/notifications?page&per_page&unread_only&category` | Inbox. `category`: `flights`, `hotels`, `payments`, `insurance`, `esim`, `offers`, `security`, `wallet`, `documents` |
| GET | `/notifications/unread-count` | `{ unread_count }` in `data` |
| POST/PATCH | `/notifications/{id}/read` | Mark one read |
| POST | `/notifications/read-all` | Mark all read |
| DELETE | `/notifications/{id}` | Delete one |
| POST | `/notifications/clear` | Clear inbox |
| POST/PATCH/DELETE | `/notifications/devices` | FCM device register/update/disable |
| GET/PUT | `/notifications/preferences` | Channels + topics |
| POST | `/notifications/search-intents` | Mobile reports a flight search (Abandoned Search + Price Alert source) |
| GET | `/notifications/price-alerts` | List active price watches |
| POST | `/notifications/price-alerts` | Create/update a target-price watch |
| DELETE | `/notifications/price-alerts/{id}` | Disable a watch |

### Device register
```json
POST /notifications/devices
{ "device_token": "FCM_TOKEN", "platform": "android|ios", "app_version": "1.0.0" }
```

### Preferences (defaults)
`push=true`, `email=true`, `sms=false`,  
`flight_updates=true`, `booking_reminders=true`, `promotional=false`,  
`insurance=true`, `hotel=true`, `car_rental=false`, `login_alerts=true`

## Inbox item
```json
{
  "id": "123",
  "title": "...",
  "body": "...",
  "type": "flight|success|payment|order|system|tag",
  "is_read": false,
  "created_at": "2026-08-09T12:00:00+00:00",
  "deep_link": "/my-orders/123",
  "meta": {
    "order_id": "123",
    "product_type": "flight",
    "template_code": "FLIGHT_TIME_CHANGED",
    "family": "operational",
    "category": "flights",
    "severity": "warning",
    "recipient": "passenger",
    "channels": ["push", "in_app"],
    "expires_at": "2026-09-20T18:00:00+00:00",
    "action_engine": true,
    "from_value": "15:30",
    "to_value": "17:00",
    "actions": [
      { "code": "view_flight", "label": "View flight details", "label_ar": "عرض تفاصيل الرحلة", "deep_link": "/my-orders/123" },
      { "code": "contact_support", "label": "Contact support", "label_ar": "التواصل مع الدعم", "deep_link": "/support?order_id=123" }
    ]
  }
    "route": "Tripoli → Tunis",
    "journey_card": true,
    "stage": "before_departure",
    "next_best_offer": {
      "code": "OFFER_ESIM",
      "title": "Need internet in Tunis?",
      "body": "Activate an eSIM and stay connected from the moment you land.",
      "deep_link": "/esim?country=TN",
      "reason": "missing_esim"
    },
    "checklist": [
      { "code": "flight", "ready": true, "label": "Flight", "label_ar": "الطيران" },
      { "code": "esim", "ready": false, "label": "eSIM", "label_ar": "eSIM" }
    ],
    "offers": []
  }
}
```

## Push
- Android channel: `cpbooke_default`
- Data keys: `deep_link`, `type`, `order_id`, `notification_id`, `click_action`

## Event → template map (MVP)

| Spec | Trigger | Template code | type | topic gate | deep_link |
|------|---------|---------------|------|------------|-----------|
| F2 | `PaymentSucceeded` | `PAYMENT_SUCCEEDED` | payment | — | `/my-orders` |
| F3 | `PaymentFailed` / order `failed` | `PAYMENT_FAILED` | payment | — | `/my-orders` |
| F4 | Order → `ticketed` (flight) | `FLIGHT_TICKET_ISSUED` | success | — | `/my-orders` |
| F5 | Scheduler 24h before departure | `FLIGHT_REMINDER_24H` | flight | `booking_reminders` | `/my-orders` |
| F6 | Scheduler 3h before departure | `FLIGHT_REMINDER_3H` | flight | `booking_reminders` | `/my-orders` |
| F7 | Airline detail change on sync | `FLIGHT_GATE_CHANGED` / `FLIGHT_TIME_CHANGED` / `FLIGHT_DELAYED` / … | flight | **none** (operational) | `/my-orders/{id}` |
| C1 | Airline cancelled the flight | `FLIGHT_CANCELLED` | flight | **none** | alternatives + refund + support |
| C2 | Customer/admin cancelled booking | `BOOKING_CANCELLED` / `HOTEL_BOOKING_CANCELLED` | order | **none** | `/my-orders/{id}` |
| R1 | Refund requested/approved | `REFUND_INITIATED` | payment | **none** | `/my-orders/{id}` |
| R2 | Refund executed | `REFUND_ISSUED` | payment | **none** | `/my-orders/{id}` |
| R3 | Refund failed | `REFUND_FAILED` | payment | **none** | support |
| H6 | Free-cancel deadline today | `HOTEL_CANCELLATION_DEADLINE_REMINDER` | order | `booking_reminders` | `/my-orders/{id}` |
| H1 | Hotel order confirmed | `HOTEL_BOOKING_CONFIRMED` | success | `hotel` | `/my-orders` |
| I1 | Insurance confirmed | `INSURANCE_POLICY_ISSUED` | success | `insurance` | `/my-orders` |
| S1 | Login | `LOGIN_ALERT` (direct) | system | `login_alerts` | `/login` |
| H4 | Hotel check-in −24h | `HOTEL_CHECKIN_REMINDER_24H` | order | `booking_reminders` (+hotel) | `/my-orders/{id}` |
| J1 | Scheduler 1h before departure | `FLIGHT_REMINDER_1H` | flight | `booking_reminders` | `/my-orders/{id}` |
| J2 | Embedded on 3h card | `OFFER_ESIM` | tag | destination country + no eSIM | `/esim?country=TN` |
| J3 | Embedded on 3h card | `OFFER_INSURANCE` | tag | `insurance` + no trip cover | `/insurance?order_id=` |
| J4 | Scheduler at `arrival_time` | `DESTINATION_ARRIVAL` | flight | `booking_reminders` | `/my-orders/{id}` or hotels |
| J5 | Embedded on arrival card | `OFFER_HOTELS_AT_DESTINATION` | tag | `hotel` + no same-day hotel | `/hotels?city=tunis` |
| J6 | ~3 days after `arrival_time` | `POST_TRIP_THANKS` | success | `booking_reminders` | `/loyalty` or next-trip |
| M1 | Search with no booking after 2h | `ABANDONED_FLIGHT_SEARCH` | tag | — | `/flights?origin=&destination=&date=` |
| M2 | Watched fare ≤ target | `PRICE_ALERT_HIT` | tag | creating the alert is opt-in | `/flights?...` |
| D1 | Passport expires in 14–30 days | `PASSPORT_EXPIRY_REMINDER` | order | `booking_reminders` | `/profile/passengers` |
| K1 | Scheduler ~48h before departure | `ONLINE_CHECKIN_OPEN` | flight | `booking_reminders` | `/my-orders/{id}/check-in` |
| G1 | Provider first fills gate | `GATE_ASSIGNED` | flight | **none** (critical) | `/my-orders/{id}` |
| G2 | Provider changes gate | `FLIGHT_GATE_CHANGED` | flight | **none** (critical) | `/my-orders/{id}` |
| W1 | Wallet credit / debit / refund | `WALLET_TOPUP_SUCCESS` / `WALLET_DEBIT` / `WALLET_REFUND` | order | **none** | `/wallet` |
| H7 | Scheduler checkout −24h | `HOTEL_CHECKOUT_REMINDER` | order | `booking_reminders` + `hotel` | `/my-orders/{id}` |
| E1 | eSIM owned, ~12h before departure | `ESIM_ACTIVATION_REMINDER` | order | `booking_reminders` | `/esim` |

## Action Engine

Every important event is **notification + action**, not a status dump.

```
Event → Severity → Recipient → Channels → Message → Deep link → Actions → Expiry
```

| Field | Meaning |
|-------|---------|
| `meta.family` | `transactional` / `operational` / `journey` / `marketing` |
| `meta.severity` | `critical` (6h) / `warning` (24h) / `info` (72h) |
| `meta.recipient` | `passenger` |
| `meta.channels` | Intended send channels (SMS is still OTP-only) |
| `meta.actions[]` | CTAs: `code`, `label`, `label_ar`, `deep_link` |
| `meta.expires_at` | When the CTA is no longer useful |
| `meta.action_engine` | Always `true` for engine-built cards |

Example — `FLIGHT_CANCELLED` (critical): **Find alternative** + **Request refund** + **Contact support**.

Operational/transactional events **bypass promotional topic gates**. `LOGIN_ALERT` stays on `login_alerts`. Seat upgrades are never written by admin in the DB — user confirms, Provider API executes.

Templates added in Admin → Notifications → **Sync templates**. Catalog-only until the product event exists: visa/docs upload, baggage purchase, seat upgrade via Provider, eSIM data usage, loyalty points, linked-account payment requests, extra security events (`PASSWORD_CHANGED`, …).

Mobile must POST every flight search:

```json
POST /notifications/search-intents
{
  "origin": "TIP",
  "destination": "IST",
  "departure_date": "2026-09-20",
  "lowest_price": 1250,
  "currency": "LYD"
}
```

Price watch:

```json
POST /notifications/price-alerts
{
  "origin": "TIP",
  "destination": "TUN",
  "departure_date": "2026-10-01",
  "target_price": 800,
  "currency": "LYD"
}
```

## Next Best Offer (one CTA per stage)

Do **not** send a separate push per product. Render `meta.next_best_offer` (and optional extra `meta.offers[]`) on the same journey card.

| Stage | When | Priority |
|-------|------|----------|
| `after_booking` | `FLIGHT_TICKET_ISSUED` | Insurance → eSIM → Hotel → Return flight |
| `before_departure` | 24h + 3h reminders | eSIM → Insurance → Hotel |
| `during_journey` | arrival | Hotel → Car → eSIM |
| `after_journey` | ~3 days after arrival | Next trip → Loyalty |

Skip any product the traveler already bought for this trip. 1h reminder stays reminder-only (no offers).

## Rules
1. Inbox is always written when `in_app` channel is allowed (even if push fails).
2. Channel preferences are enforced before send.
3. Flight reminders are idempotent per `order_id + template_code`.
4. Scheduler: `SendBookingReminderNotificationsJob` every 15 minutes.
5. Firebase: `FIREBASE_CREDENTIALS` or `storage/app/firebase/firebase_credentials.json`.
