# Booke — مواصفات الإشعارات الكاملة للباكند

> **الغرض:** ملف مرجعي واحد لفريق الباكند لتجهيز كل أنظمة الإشعارات (Push, Email, SMS/OTP).  
> **الجمهور:** Backend / DevOps  
> **آخر تحديث:** 18 أغسطس 2026  
> **حالة تطبيق الموبايل:** جاهز لاستقبال Push + Inbox + Preferences. إنشاء الأحداث والإرسال من الباكند فقط.

---

## 1. ملخص القنوات

| القناة | الاستخدام | ملاحظات |
|--------|-----------|---------|
| **Push (FCM)** | إشعارات تفاعلية داخل التطبيق | القناة الرئيسية — مع Inbox داخلي |
| **Email** | إشعارات معاملات + OTP للبريد | يُحترم تفضيل `email` |
| **SMS** | **OTP فقط** | لا يُستخدم لإشعارات الحجز/الدفع/التذكيرات |
| **WhatsApp** | **غير مدعوم من الباكند** | المستخدم يشارك الروابط يدوياً من التطبيق فقط |

### قاعدة ذهبية

1. **كل إشعار Push يُكتب في Inbox** حتى لو فشل الإرسال عبر FCM.
2. **SMS = OTP فقط** — لا تُرسل رسائل تسويقية أو تذكيرات عبر SMS.
3. **Email** يُستخدم للإشعارات المهمة + رموز التحقق عبر البريد.
4. **تُطبَّق تفضيلات المستخدم** قبل الإرسال (Push / Email / Topics).
5. **التذكيرات المجدولة** idempotent لكل `(user_id + template_code + reference_id)`.

---

## 2. البنية التحتية

| البند | القيمة |
|-------|--------|
| Base URL | `{PASSENGER_API_ORIGIN}/api/v1` |
| Auth | Bearer Sanctum token |
| Response envelope | `{ success, message, data, meta }` |
| Push provider | Firebase Cloud Messaging (FCM) |
| Android channel ID | `cpbooke_default` |
| Android channel name | `Booke Notifications` |

### تسجيل الجهاز (FCM)

يُستدعى من الموبايل بعد Login:

```http
POST /api/v1/notifications/devices
Authorization: Bearer {token}
Content-Type: application/json

{
  "device_token": "FCM_TOKEN",
  "platform": "android|ios",
  "app_version": "1.0.0"
}
```

عند Logout:

```http
DELETE /api/v1/notifications/devices
{ "device_token": "FCM_TOKEN" }
```

تعطيل/تفعيل Push لجهاز:

```http
PATCH /api/v1/notifications/devices
{ "device_token": "FCM_TOKEN", "enabled": true|false }
```

---

## 3. APIs المطلوبة (Inbox + Preferences)

| Method | Path | الوصف |
|--------|------|--------|
| GET | `/notifications?page=&per_page=&unread_only=` | قائمة الإشعارات |
| GET | `/notifications/unread-count` | `{ unread_count }` |
| POST/PATCH | `/notifications/{id}/read` | تعليم إشعار كمقروء |
| POST | `/notifications/read-all` | تعليم الكل كمقروء |
| DELETE | `/notifications/{id}` | حذف إشعار |
| POST | `/notifications/clear` | مسح كل الإشعارات |
| GET | `/notifications/preferences` | جلب التفضيلات |
| PUT | `/notifications/preferences` | تحديث التفضيلات |
| POST | `/notifications/push-test` | اختبار Push (dev/staging) |
| POST/PATCH/DELETE | `/notifications/devices` | إدارة أجهزة FCM |

### شكل عنصر Inbox

```json
{
  "id": "notif_123",
  "title": "تم إصدار تذكرتك",
  "body": "رحلتك من الرياض إلى جدة جاهزة",
  "type": "success",
  "is_read": false,
  "created_at": "2026-08-18T10:00:00+00:00",
  "deep_link": "/my-orders",
  "meta": {
    "template_code": "FLIGHT_TICKET_ISSUED",
    "order_id": "order_456",
    "product_type": "flight"
  }
}
```

### أنواع `type` المدعومة في الموبايل

| type | الاستخدام |
|------|-----------|
| `success` | تأكيدات ناجحة (تذكرة، فندق، تأمين) |
| `flight` | رحلات، تذكيرات، تحديثات حالة |
| `payment` | دفع ناجح/فاشل |
| `order` | تذكيرات طلبات (مثل check-in فندق) |
| `system` | أمان، تسجيل دخول |
| `tag` | عروض ترويجية |

---

## 4. تفضيلات المستخدم (Preferences)

```json
GET/PUT /notifications/preferences

{
  "push": true,
  "email": true,
  "sms": false,
  "flight_updates": true,
  "booking_reminders": true,
  "promotional": false,
  "insurance": true,
  "hotel": true,
  "car_rental": false,
  "login_alerts": true
}
```

### القيم الافتراضية

| المفتاح | Default | ملاحظة |
|---------|---------|--------|
| `push` | `true` | |
| `email` | `true` | |
| `sms` | `false` | **لا يُستخدم لإشعارات — OTP فقط خارج هذا النظام** |
| `flight_updates` | `true` | |
| `booking_reminders` | `true` | |
| `promotional` | `false` | |
| `insurance` | `true` | |
| `hotel` | `true` | |
| `car_rental` | `false` | |
| `login_alerts` | `true` | |

### منطق التطبيق

```
IF channel == push AND user.push == false → لا ترسل Push
IF channel == email AND user.email == false → لا ترسل Email
IF event has topic_gate AND user[topic_gate] == false → لا ترسل (لكل القنوات)
ALWAYS → اكتب في Inbox (إلا إذا كان الحدث OTP فقط)
```

> **تنبيه:** مفتاح `sms` في التفضيلات موجود في الموبايل حالياً لكن **لا يجب ربطه بإشعارات الحجز**. SMS مخصص لـ OTP فقط (قسم 8).

---

## 5. Push Payload (FCM)

### حقول `data` الإلزامية

```json
{
  "title": "عنوان الإشعار",
  "body": "نص الإشعار",
  "type": "flight",
  "deep_link": "/my-orders",
  "template_code": "FLIGHT_REMINDER_24H",
  "notification_id": "notif_123",
  "order_id": "order_456"
}
```

### حقول اختيارية

```json
{
  "product_type": "flight|hotel|insurance|esim",
  "payment_request_id": "pr_789",
  "click_action": "FLUTTER_NOTIFICATION_CLICK"
}
```

### Android

- استخدم `channel_id: cpbooke_default`
- أرسل `notification` + `data` معاً

### Deep Links المدعومة حالياً

| deep_link | الاستخدام |
|-----------|-----------|
| `/my-orders` | معظم إشعارات الحجز والدفع |
| `/login` | تنبيهات الأمان |
| `/payment-requests/{id}` | *(مقترح)* تفاصيل طلب دفع |

---

## 6. حصر كامل لأحداث الإشعارات

### 6.1 الحجوزات والطلبات (Orders)

| # | template_code | المحفّز (Trigger) | المستلم | Push | Email | Topic Gate | type | deep_link |
|---|---------------|-------------------|---------|------|-------|------------|------|-----------|
| 1 | `PAYMENT_SUCCEEDED` | نجاح الدفع | صاحب الطلب | ✅ | ✅ | — | payment | `/my-orders` |
| 2 | `PAYMENT_FAILED` | فشل الدفع / order `failed` | صاحب الطلب | ✅ | ✅ | — | payment | `/my-orders` |
| 3 | `FLIGHT_TICKET_ISSUED` | Order → `ticketed` (رحلة) | صاحب الطلب | ✅ | ✅ | — | success | `/my-orders` |
| 4 | `FLIGHT_REMINDER_24H` | Scheduler: قبل الإقلاع 24 ساعة | صاحب الطلب | ✅ | ✅ | `booking_reminders` | flight | `/my-orders` |
| 5 | `FLIGHT_REMINDER_3H` | Scheduler: قبل الإقلاع 3 ساعات | صاحب الطلب | ✅ | ✅ | `booking_reminders` | flight | `/my-orders` |
| 6 | `FLIGHT_STATUS_UPDATED` | تغيّر حالة/تفاصيل الرحلة عند sync | صاحب الطلب | ✅ | ✅ | `flight_updates` | flight | `/my-orders` |
| 7 | `HOTEL_BOOKING_CONFIRMED` | تأكيد حجز فندق | صاحب الطلب | ✅ | ✅ | `hotel` | success | `/my-orders` |
| 8 | `HOTEL_CHECKIN_REMINDER_24H` | قبل check-in بـ 24 ساعة | صاحب الطلب | ✅ | ✅ | `booking_reminders` + `hotel` | order | `/my-orders` |
| 9 | `INSURANCE_POLICY_ISSUED` | إصدار وثيقة تأمين | صاحب الطلب | ✅ | ✅ | `insurance` | success | `/my-orders` |

#### نصوص مقترحة (عربي)

| template_code | title | body (مثال) |
|---------------|-------|-------------|
| `PAYMENT_SUCCEEDED` | تم الدفع بنجاح | تم تأكيد دفع طلبك بمبلغ {amount} {currency} |
| `PAYMENT_FAILED` | فشل الدفع | تعذّر إتمام الدفع. يرجى المحاولة مرة أخرى |
| `FLIGHT_TICKET_ISSUED` | تم إصدار التذكرة | تذكرتك جاهزة — رقم الحجز: {pnr} |
| `FLIGHT_REMINDER_24H` | تذكير برحلتك | رحلتك غداً من {from} إلى {to} — الإقلاع {time} |
| `FLIGHT_REMINDER_3H` | رحلتك قريباً | رحلتك بعد 3 ساعات — تأكد من الوصول للمطار |
| `FLIGHT_STATUS_UPDATED` | تحديث حالة الرحلة | تغيّرت حالة رحلتك — اضغط للتفاصيل |
| `HOTEL_BOOKING_CONFIRMED` | تم تأكيد حجز الفندق | حجزك في {hotel_name} مؤكد |
| `HOTEL_CHECKIN_REMINDER_24H` | تذكير تسجيل الوصول | غداً موعد check-in في {hotel_name} |
| `INSURANCE_POLICY_ISSUED` | تم إصدار وثيقة التأمين | وثيقة التأمين الخاصة بك جاهزة |

---

### 6.2 الأمان (Security)

| # | template_code | المحفّز | المستلم | Push | Email | Topic Gate | type | deep_link |
|---|---------------|---------|---------|------|-------|------------|------|-----------|
| 10 | `LOGIN_ALERT` | تسجيل دخول جديد من جهاز/موقع | صاحب الحساب | ✅ | ✅ | `login_alerts` | system | `/login` |

#### نص مقترح

| template_code | title | body |
|---------------|-------|------|
| `LOGIN_ALERT` | تسجيل دخول جديد | تم تسجيل الدخول إلى حسابك من {device} في {location} |

---

### 6.3 طلبات الدفع (Payment Requests)

| # | template_code | المحفّز | المستلم | Push | Email | deep_link |
|---|---------------|---------|---------|------|-------|-----------|
| 11 | `PAYMENT_REQUEST_CREATED` | إنشاء طلب دفع جديد | المستلم (payer) | ✅ | ✅* | `/payment-requests/{id}` |
| 12 | `PAYMENT_REQUEST_PAID_CREATOR` | اكتمال الدفع | المنشئ (creator) | ✅ | ✅ | `/my-orders` |
| 13 | `PAYMENT_REQUEST_PAID_PAYER` | اكتمال الدفع | الدافع (payer) | ✅ | ✅ | `/my-orders` |
| 14 | `PAYMENT_REQUEST_EXPIRY_REMINDER` | قبل انتهاء الصلاحية (مثلاً 2 ساعة) | المستلم | ✅ | ✅* | `/payment-requests/{id}` |
| 15 | `PAYMENT_REQUEST_EXPIRED` | انتهت صلاحية الطلب | المنشئ + المستلم | ✅ | ✅ | — |
| 16 | `PAYMENT_REQUEST_CANCELLED` | إلغاء من المنشئ | المستلم | ✅ | ✅* | — |
| 17 | `PAYMENT_REQUEST_REFUND_ISSUED` | إرجاع مبلغ بعد إلغاء | الدافع | ✅ | ✅ | `/my-orders` |
| 18 | `PAYMENT_REQUEST_ACTION_APPROVAL` | طلب موافقة (إلغاء/تعديل) | الطرف الآخر | ✅ | ✅ | `/my-orders` |

\* Email للمستلم إذا وُجد `recipient_email` عند إنشاء الطلب.

#### نصوص مقترحة

| template_code | title | body (مثال) |
|---------------|-------|-------------|
| `PAYMENT_REQUEST_CREATED` | طلب دفع جديد | {creator_name} يطلب منك دفع {amount} {currency} |
| `PAYMENT_REQUEST_PAID_CREATOR` | تم الدفع! | {payer_name} دفع {amount} {currency} لطلبك |
| `PAYMENT_REQUEST_PAID_PAYER` | تم إصدار التذكرة | تم الدفع بنجاح — رقم الحجز: {pnr} |
| `PAYMENT_REQUEST_EXPIRY_REMINDER` | طلب دفع ينتهي قريباً | يتبقى {hours} ساعات لدفع {amount} {currency} |
| `PAYMENT_REQUEST_EXPIRED` | انتهت صلاحية طلب الدفع | انتهت صلاحية طلب الدفع دون إتمام الدفع |
| `PAYMENT_REQUEST_CANCELLED` | تم إلغاء طلب الدفع | {creator_name} ألغى طلب الدفع |
| `PAYMENT_REQUEST_REFUND_ISSUED` | تم إرجاع المبلغ | تم إرجاع {amount} {currency} إلى حسابك |
| `PAYMENT_REQUEST_ACTION_APPROVAL` | طلب موافقة | {user_name} يطلب {action} — هل توافق؟ |

#### حالات طلب الدفع

| Status | الوصف |
|--------|-------|
| `pending` | في انتظار الدفع |
| `paid` | تم الدفع |
| `expired` | انتهت الصلاحية |
| `cancelled` | أُلغي من المنشئ |

---

### 6.4 الحسابات المرتبطة (Linked Accounts)

| # | template_code | المحفّز | المستلم | Push | Email |
|---|---------------|---------|---------|------|-------|
| 19 | `LINK_REQUEST_RECEIVED` | إرسال طلب ربط حساب | المستخدم المستهدف | ✅ | ❌ |
| 20 | `LINK_REQUEST_ACCEPTED` | قبول طلب الربط | مُرسِل الطلب | ✅ | ❌ |
| 21 | `LINK_REQUEST_REJECTED` | رفض طلب الربط | مُرسِل الطلب | ✅ | ❌ |
| 22 | `PAYMENT_REQUEST_FROM_LINKED` | طلب دفع من حساب مرتبط (in-app) | الحساب المرتبط | ✅ | ❌ |

#### Push payload مثال — طلب ربط

```json
{
  "type": "system",
  "title": "طلب ربط حساب",
  "body": "محمد يريد ربط حسابه بحسابك",
  "deep_link": "/linked-accounts",
  "template_code": "LINK_REQUEST_RECEIVED",
  "request_id": "req_456",
  "from_user_id": "user_123"
}
```

---

### 6.5 العروض الترويجية (اختياري — MVP لاحقاً)

| # | template_code | المحفّز | Push | Email | Topic Gate |
|---|---------------|---------|------|-------|------------|
| 23 | `PROMO_OFFER` | حملة تسويقية | ✅ | ✅ | `promotional` |

---

### 6.6 Action Engine (Notification + Action)

Booke does not send status-only pushes. Each event resolves to:

`Event → Severity → Recipient → Channels → Message → Deep link → Actions → Expiry`

- **Critical** (6h): flight cancelled, gate assigned/changed, boarding started/final/closed, security alert.
- **Warning** (24h): time/terminal/delay, documents missing, wallet low, payment action required.
- **Info** (72h): confirmations, wallet top-up, loyalty.

Operational/transactional families **bypass promotional preferences**. SMS remains OTP-only.

Wired now (besides MVP table): `PASSPORT_EXPIRY_REMINDER`, `ONLINE_CHECKIN_OPEN`, `GATE_ASSIGNED`, `BOARDING_*`, `SEAT_ASSIGNED` / `SEAT_CHANGED`, `HOTEL_CHECKOUT_REMINDER`, `ESIM_ACTIVATION_REMINDER`, `WALLET_*` (top-up / debit / refund / low balance).

Catalog-only until the product event exists: visa/docs, baggage, seat upgrade via Provider API, eSIM data usage, loyalty points, linked-account payment requests, extra security (`NEW_DEVICE_LOGIN`, `PASSWORD_CHANGED`, …).

Admin Sync templates after deploy.

---

## 7. Email — إشعارات المعاملات

### متى يُرسل Email؟

- كل حدث في الأقسام 6.1 – 6.3 حيث العمود Email = ✅
- فقط إذا `user.email == true`
- يُفضّل دعم لغتين: `ar` و `en` حسب `user.locale`

### قوالب Email المطلوبة

| الفئة | عدد القوالب |
|-------|-------------|
| Orders (6.1) | 9 |
| Security (6.2) | 1 |
| Payment Requests (6.3) | 8 |
| OTP (قسم 8) | 3 (هاتف، بريد، استعادة كلمة المرور) |

### حقول مشتركة في كل إيميل معاملة

```
- user_name
- template_code
- cta_url (رابط deep link أو web)
- amount, currency (إن وُجد)
- order_id / payment_request_id
- support_email
```

---

## 8. SMS — OTP فقط

> **مهم:** SMS **لا يُستخدم** لإشعارات الحجز أو الدفع أو التذكيرات.  
> الاستخدام الوحيد: إرسال رمز تحقق (OTP) للهاتف.

### 8.1 حالات OTP عبر SMS

| # | الحدث | API | القناة | المستلم |
|---|-------|-----|--------|---------|
| O1 | التحقق من رقم الهاتف | `POST /users/verify/phone/send` | SMS | المستخدم |
| O2 | تأكيد رقم الهاتف | `POST /users/verify/phone/confirm` | — | المستخدم يُدخل OTP في التطبيق |

### 8.2 حالات OTP عبر Email (ليست SMS)

| # | الحدث | API |
|---|-------|-----|
| E1 | التحقق من البريد | `POST /users/verify/email/send` |
| E2 | تأكيد البريد | `POST /users/verify/email/confirm` |
| E3 | تغيير البريد | `POST /users/email/change-request` |
| E4 | استعادة كلمة المرور | `POST /auth/forgot-password` |
| E5 | تأكيد OTP استعادة كلمة المرور | `POST /auth/verify-reset-otp` |

### 8.3 مواصفات OTP

```json
POST /users/verify/phone/send

Response:
{
  "success": true,
  "message": "Verification code sent.",
  "data": {
    "otp_expires_in_seconds": 600,
    "masked_phone": "+9665****4567"
  }
}
```

| البند | القيمة المقترحة |
|-------|-----------------|
| طول OTP | 6 أرقام |
| مدة الصلاحية | 600 ثانية (10 دقائق) |
| Rate limit | 3 محاولات / 15 دقيقة لكل رقم |
| Max verify attempts | 5 محاولات خاطئة ثم lock مؤقت |
| تخزين OTP | hashed — لا plain text |

### نص SMS مقترح (عربي)

```
رمز التحقق من Booke: {otp}
صالح لمدة 10 دقائق. لا تشاركه مع أحد.
```

### نص SMS مقترح (إنجليزي)

```
Your Booke verification code: {otp}
Valid for 10 minutes. Do not share it.
```

---

## 9. WhatsApp

| البند | الحالة |
|-------|--------|
| تكامل WhatsApp Business API | **غير مطلوب حالياً** |
| إرسال تلقائي من الباكند | **لا** |
| المشاركة اليدوية | المستخدم يشارك روابط طلبات الدفع/الرحلات عبر share sheet في الموبايل |

---

## 10. Scheduler (المجدول)

### التذكيرات الدورية

| المهمة | التكرار | الأحداث |
|--------|---------|---------|
| تذكيرات الرحلات والفنادق | كل **15 دقيقة** | `FLIGHT_REMINDER_24H`, `FLIGHT_REMINDER_3H`, `FLIGHT_REMINDER_1H`, `ONLINE_CHECKIN_OPEN` (~48h), `DESTINATION_ARRIVAL`, `POST_TRIP_THANKS`, `HOTEL_CHECKIN_REMINDER_24H`, `HOTEL_CHECKOUT_REMINDER`, `HOTEL_CANCELLATION_DEADLINE_REMINDER`, `ESIM_ACTIVATION_REMINDER`, `PASSPORT_EXPIRY_REMINDER`, `PAYMENT_EXPIRED` |
| Abandoned Search / Price Alert | نفس المهمة كل **15 دقيقة** | `ABANDONED_FLIGHT_SEARCH` (بعد 2–48 ساعة من بحث بدون حجز)، `PRICE_ALERT_HIT` |
| تذكير انتهاء طلب الدفع | كل **15 دقيقة** | `PAYMENT_REQUEST_EXPIRY_REMINDER` |
| انتهاء صلاحية طلبات الدفع | كل **15 دقيقة** | تحديث status → `expired` + `PAYMENT_REQUEST_EXPIRED` |

### قواعد Idempotency

```
UNIQUE KEY (user_id, template_code, reference_id)

reference_id =
  - order_id        للحجوزات
  - payment_request_id  لطلبات الدفع
  - request_id      لربط الحسابات
```

لا تُرسل نفس التذكير مرتين لنفس `(user, event, reference)`.

---

## 11. تدفق الإرسال (Backend Flow)

```
Event occurs (payment, ticket, scheduler, ...)
        │
        ▼
Resolve recipients (order owner, creator, payer, linked user)
        │
        ▼
Load user preferences
        │
        ▼
Check topic gates (booking_reminders, hotel, ...)
        │
        ▼
Create inbox row (ALWAYS for app notifications)
        │
        ├── push enabled? → Send FCM to all active device tokens
        │
        └── email enabled? → Send transactional email
```

**لا يمر هذا التدفق على SMS** إلا لأحداث OTP في القسم 8.

---

## 12. جداول قاعدة البيانات المقترحة

### `notifications`

```sql
CREATE TABLE notifications (
    id              VARCHAR(36) PRIMARY KEY,
    user_id         VARCHAR(36) NOT NULL,
    title           VARCHAR(255) NOT NULL,
    body            TEXT NOT NULL,
    type            VARCHAR(32) NOT NULL,
    template_code   VARCHAR(64) NOT NULL,
    deep_link       VARCHAR(255),
    meta            JSON,
    is_read         BOOLEAN DEFAULT FALSE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_unread (user_id, is_read, created_at DESC)
);
```

### `notification_devices`

```sql
CREATE TABLE notification_devices (
    id              VARCHAR(36) PRIMARY KEY,
    user_id         VARCHAR(36) NOT NULL,
    device_token    VARCHAR(512) NOT NULL UNIQUE,
    platform        ENUM('android', 'ios') NOT NULL,
    app_version     VARCHAR(32),
    enabled         BOOLEAN DEFAULT TRUE,
    last_seen_at    TIMESTAMP,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### `notification_preferences`

```sql
CREATE TABLE notification_preferences (
    user_id             VARCHAR(36) PRIMARY KEY,
    push                BOOLEAN DEFAULT TRUE,
    email               BOOLEAN DEFAULT TRUE,
    sms                 BOOLEAN DEFAULT FALSE,
    flight_updates      BOOLEAN DEFAULT TRUE,
    booking_reminders   BOOLEAN DEFAULT TRUE,
    promotional         BOOLEAN DEFAULT FALSE,
    insurance           BOOLEAN DEFAULT TRUE,
    hotel               BOOLEAN DEFAULT TRUE,
    car_rental          BOOLEAN DEFAULT FALSE,
    login_alerts        BOOLEAN DEFAULT TRUE,
    updated_at          TIMESTAMP
);
```

### `notification_delivery_log` (اختياري — للتتبع)

```sql
CREATE TABLE notification_delivery_log (
    id              VARCHAR(36) PRIMARY KEY,
    notification_id VARCHAR(36),
    user_id         VARCHAR(36) NOT NULL,
    channel         ENUM('push', 'email', 'sms_otp') NOT NULL,
    template_code   VARCHAR(64) NOT NULL,
    status          ENUM('sent', 'failed', 'skipped') NOT NULL,
    error_message   TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### `travel_search_intents` (Abandoned Search)

الموبايل يرسل كل بحث رحلات إلى `POST /api/v1/notifications/search-intents`. المجدول يتابع المسار، ويتخطى الإشعار إذا حُجزت نفس الرحلة.

### `price_alerts`

المستخدم يحدد سعراً مستهدفاً (`POST /notifications/price-alerts`). عندما يصل `last_seen_price` في بحث لاحق إلى الهدف أو دونه، يُرسل `PRICE_ALERT_HIT` مع deep link لنتائج الرحلات.

### `otp_challenges`

```sql
CREATE TABLE otp_challenges (
    id              VARCHAR(36) PRIMARY KEY,
    user_id         VARCHAR(36),
    purpose         ENUM('phone_verify', 'email_verify', 'password_reset', 'email_change') NOT NULL,
    destination     VARCHAR(255) NOT NULL,
    channel         ENUM('sms', 'email') NOT NULL,
    code_hash       VARCHAR(255) NOT NULL,
    expires_at      TIMESTAMP NOT NULL,
    attempts        INT DEFAULT 0,
    consumed_at     TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_destination (destination, purpose, expires_at)
);
```

---

## 13. متغيرات البيئة المطلوبة (Backend / DevOps)

```env
# Firebase
FIREBASE_PROJECT_ID=
FIREBASE_CLIENT_EMAIL=
FIREBASE_PRIVATE_KEY=

# Email provider (SendGrid / SES / Mailgun)
MAIL_FROM_ADDRESS=noreply@booke.app
MAIL_FROM_NAME=Booke
MAIL_PROVIDER_API_KEY=

# SMS provider (OTP only — Twilio / Unifonic / etc.)
SMS_PROVIDER_API_KEY=
SMS_SENDER_ID=Booke
SMS_OTP_ENABLED=true

# App
APP_DEEP_LINK_BASE=https://app.booke.app
APP_WEB_URL=https://booke.app
```

---

## 14. Checklist التنفيذ

### المرحلة 1 — الأساس (MVP)

- [ ] جداول: `notifications`, `notification_devices`, `notification_preferences`
- [ ] APIs: Inbox CRUD + Preferences + Device register/delete
- [ ] FCM integration + Android channel `cpbooke_default`
- [ ] أحداث: `PAYMENT_SUCCEEDED`, `PAYMENT_FAILED`, `FLIGHT_TICKET_ISSUED`
- [ ] OTP هاتف: `/users/verify/phone/send` + confirm
- [ ] Email provider + قوالب OTP بريد واستعادة كلمة المرور

### المرحلة 2 — التذكيرات

- [ ] Scheduler كل 15 دقيقة
- [ ] `FLIGHT_REMINDER_24H`, `FLIGHT_REMINDER_3H`
- [ ] `HOTEL_CHECKIN_REMINDER_24H`
- [ ] Idempotency keys
- [ ] `FLIGHT_STATUS_UPDATED`

### المرحلة 3 — طلبات الدفع

- [ ] أحداث 11–18 (Payment Requests)
- [ ] Email للمستلم عند وجود `recipient_email`
- [ ] تذكير انتهاء الصلاحية + تحديث status

### المرحلة 4 — الحسابات المرتبطة

- [ ] أحداث 19–22 (Linked Accounts)
- [ ] Push in-app لطلبات الدفع من حساب مرتبط

### المرحلة 5 — إضافات

- [ ] `LOGIN_ALERT`
- [ ] `HOTEL_BOOKING_CONFIRMED`, `INSURANCE_POLICY_ISSUED`
- [ ] `PROMO_OFFER` (ترويجي)
- [ ] `notification_delivery_log`
- [ ] دعم لغتين في القوالب (ar/en)
- [ ] `POST /notifications/push-test`

---

## 15. ملخص سريع — عدد الإشعارات

| الفئة | العدد |
|-------|-------|
| حجوزات وطلبات | 9 |
| أمان | 1 |
| طلبات دفع | 8 |
| حسابات مرتبطة | 4 |
| ترويجي (اختياري) | 1 |
| OTP SMS | 1 نوع (تحقق هاتف) |
| OTP Email | 3 أنواع |
| **المجموع أحداث Push/Email** | **23** (+ 1 ترويجي اختياري) |

---

## 16. مراجع من المشروع

| الملف | المحتوى |
|-------|---------|
| `docs/api-notifications-mobile.md` | عقد API الإشعارات + أحداث MVP |
| `docs/payment-request-api.md` | API طلبات الدفع |
| `docs/payment-request-summary.md` | سيناريوهات الإشعارات لطلبات الدفع |
| `docs/linked-accounts-api.md` | إشعارات ربط الحسابات |
| `lib/features/notification/` | كود الموبايل (FCM + Inbox) |

---

## 17. أسئلة مفتوحة للباكند

1. هل نُزيل مفتاح `sms` من `notification_preferences` لاحقاً أم نُبقيه غير مفعّل دائماً؟
2. هل `deep_link` لطلبات الدفع `/payment-requests/{id}` مدعوم في الراوتر حالياً أم نكتفي بـ `/my-orders`؟
3. متى يُرسل `PAYMENT_REQUEST_EXPIRY_REMINDER` بالضبط؟ (مقترح: قبل 2 ساعة من `expires_at`)
4. هل يُرسل `LOGIN_ALERT` عند كل login أم فقط من جهاز/موقع جديد؟

---

*تم إعداد هذا الملف من تطبيق Booke Mobile — آخر مراجعة: 18 أغسطس 2026*
