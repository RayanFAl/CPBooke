# Mobile Notifications — Backend Contract

Base path: `/api/v1/notifications` (Sanctum). Response envelope: `{ success, message, data, meta }`.

## Endpoints

| Method | Path | Notes |
|--------|------|--------|
| GET | `/notifications?page&per_page&unread_only` | Inbox list |
| GET | `/notifications/unread-count` | `{ unread_count }` in `data` |
| POST/PATCH | `/notifications/{id}/read` | Mark one read |
| POST | `/notifications/read-all` | Mark all read |
| DELETE | `/notifications/{id}` | Delete one |
| POST | `/notifications/clear` | Clear inbox |
| POST/PATCH/DELETE | `/notifications/devices` | FCM device register/update/disable |
| GET/PUT | `/notifications/preferences` | Channels + topics |
| POST | `/notifications/push-test` | Test push + inbox row |

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
  "deep_link": "/my-orders",
  "meta": { "order_id": "123", "product_type": "flight", "template_code": "FLIGHT_TICKET_ISSUED" }
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
| F7 | Flight detail change on sync | `FLIGHT_STATUS_UPDATED` | flight | `flight_updates` | `/my-orders` |
| H1 | Hotel order confirmed | `HOTEL_BOOKING_CONFIRMED` | success | `hotel` | `/my-orders` |
| I1 | Insurance confirmed | `INSURANCE_POLICY_ISSUED` | success | `insurance` | `/my-orders` |
| S1 | Login | `LOGIN_ALERT` (direct) | system | `login_alerts` | `/login` |
| H4 | Hotel check-in −24h | `HOTEL_CHECKIN_REMINDER_24H` | order | `booking_reminders` (+hotel) | `/my-orders` |

## Rules
1. Inbox is always written when `in_app` channel is allowed (even if push fails).
2. Channel preferences are enforced before send.
3. Flight reminders are idempotent per `order_id + template_code`.
4. Scheduler: `SendBookingReminderNotificationsJob` every 15 minutes.
5. Firebase: `FIREBASE_CREDENTIALS` or `storage/app/firebase/firebase_credentials.json`.
