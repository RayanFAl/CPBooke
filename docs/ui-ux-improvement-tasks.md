# CPBooke — خطة تحسين UI/UX

هذا الملف يحوّل [تقييم UI/UX](./cpbooke-system-overview.md) إلى مهام تنفيذية قابلة للتتبع.

**التقييم الحالي:** 7.2 / 10  
**الهدف بعد التنفيذ:** ~8.5 / 10

**المكدس:** Vue 3 + Inertia + Tailwind — `resources/js/modules/admin/`

---

## كيفية استخدام هذا الملف

| الحقل | المعنى |
|--------|--------|
| **ID** | معرّف المهمة (مثلاً `UX-01`) |
| **الأولوية** | 🔴 عالية · 🟡 متوسطة · 🟢 منخفضة |
| **الجهد** | S (< 4h) · M (4–8h) · L (1–2 يوم) · XL (3+ أيام) |
| **التبعيات** | مهام يجب إنجازها أولاً |
| **معايير القبول** | متى تُعتبر المهمة منتهية |

---

## المرحلة 0 — Design System (الأساس)

> **الهدف:** توحيد المكونات والألوان قبل أي تحسين واسع.  
> **التبعية:** معظم مهام المراحل 1–3 تعتمد على هذه المرحلة.

### UX-01 🔴 إنشاء Tailwind theme tokens للوحة الإدارية
- **الجهد:** M
- **الملفات:** `tailwind.config.js`, `resources/css/app.css`
- **الوصف:** توسيع `theme.extend` بألوان دلالية مركزية:
  - `brand` (cyan)
  - `surface` (slate-100, white, slate-950)
  - `status.success/warning/error/info`
  - `radius.admin` (xl, 2xl, 3xl)
- **معايير القبول:**
  - [ ] لا hardcoded `cyan-700` / `slate-950` في مكونات جديدة
  - [ ] توثيق مختصر للـ tokens في تعليق داخل `tailwind.config.js`

---

### UX-02 🔴 مكون `AdminButton`
- **الجهد:** M
- **الملفات:** `resources/js/modules/admin/components/AdminButton.vue`
- **الوصف:** زر موحّد بvariants: `primary`, `secondary`, `danger`, `ghost` + `size` + `loading`
- **معايير القبول:**
  - [ ] يدعم `:disabled` و `processing` (spinner داخلي)
  - [ ] focus ring متسق (`ring-cyan-600`)
  - [ ] يُستخدم في صفحة واحدة على الأقل كـ proof-of-concept

---

### UX-03 🔴 مكون `AdminInput` + `AdminSelect` + `AdminTextarea`
- **الجهد:** M
- **الملفات:** `resources/js/modules/admin/components/AdminInput.vue` (وأشقاؤه)
- **الوصف:** حقول إدخال موحّدة مع label، error message، hint
- **معايير القبول:**
  - [ ] `rounded-2xl border-slate-200 focus:border-cyan-600`
  - [ ] دعم `dir="rtl"` عبر prop
  - [ ] عرض خطأ validation من Inertia form

---

### UX-04 🔴 مكون `AdminModal`
- **الجهد:** L
- **الملفات:** `resources/js/modules/admin/components/AdminModal.vue`
- **الوصف:** modal مشترك بدل التنفيذ ad-hoc في كل صفحة
- **معايير القبول:**
  - [ ] Focus trap (Tab يبقى داخل الـ modal)
  - [ ] Escape للإغلاق
  - [ ] `aria-modal="true"` + `role="dialog"`
  - [ ] body scroll lock
  - [ ] backdrop click للإغلاق (اختياري عبر prop)
  - [ ] استبدال modal واحد على الأقل في Orders أو Support

---

### UX-05 🔴 مكون `AdminDrawer`
- **الجهد:** L
- **الملفات:** `resources/js/modules/admin/components/AdminDrawer.vue`
- **الوصف:** panel جانبي منزلق (customer context، actions)
- **معايير القبول:**
  - [ ] RTL-aware (slide from `start`/`end`)
  - [ ] Focus trap + Escape
  - [ ] استبدال drawer في `orders/pages/Show.vue`

---

### UX-06 🟡 مكون `AdminBadge` + status palette مركزي
- **الجهد:** M
- **الملفات:** `resources/js/modules/admin/components/AdminBadge.vue`, `config/statusPalette.js`
- **الوصف:** دمج ألوان OrderStatusBadge, SupportStatusBadge, FinanceStatusBadge في ملف واحد
- **معايير القبول:**
  - [ ] palette واحدة لـ draft/pending/success/error/warn
  - [ ] Badge components الحالية تستورد من المصدر المركزي

---

### UX-07 🟡 مكون `AdminCard` + `AdminPageHeader`
- **الجهد:** S
- **الملفات:** `resources/js/modules/admin/components/AdminCard.vue`, `AdminPageHeader.vue`
- **الوصف:** تغليف hero card + eyebrow + title + description المتكرر في كل صفحة
- **معايير القبول:**
  - [ ] يستبدل pattern المكرر في 3+ صفحات

---

### UX-08 🟡 مكون `AdminTable` (wrapper)
- **الجهد:** M
- **الملفات:** `resources/js/modules/admin/components/AdminTable.vue`
- **الوصف:** wrapper للجداول: `overflow-x-auto`, thead styling, empty state slot
- **معايير القبول:**
  - [ ] slot للـ empty state
  - [ ] slot للـ pagination
  - [ ] يُستخدم في Orders Index + Users Index

---

### UX-09 🟢 ترحيل تدريجي للصفحات إلى Design System
- **الجهد:** XL
- **التبعيات:** UX-01 → UX-08
- **الوصف:** استبدال Tailwind inline classes بالمكونات المشتركة
- **ترتيب الترحيل المقترح:**
  1. Orders (Index + Show)
  2. Support (Index + Show)
  3. Users (Index + Show)
  4. Settlements
  5. Finance
  6. باقي الوحدات
- **معايير القبول:**
  - [ ] checklist لكل وحدة (✅ migrated)
  - [ ] لا regression في Vitest UI tests

---

## المرحلة 1 — Feedback & Loading (تأثير فوري على UX)

### UX-10 🔴 نظام Toast notifications
- **الجهد:** M
- **الملفات:** `AdminToast.vue`, `useAdminToast.js`, تعديل `AdminLayout.vue`
- **الوصف:** toast عالمي للنجاح/الخطأ/معلومات — بديل/مكمّل لـ flash messages
- **معايير القبول:**
  - [ ] auto-dismiss (5s) + dismiss يدوي
  - [ ] stack متعدد
  - [ ] ألوان emerald/rose/sky
  - [ ] يعمل مع Inertia flash + استدعاء programmatic
  - [ ] `aria-live="polite"` للـ screen readers

---

### UX-11 🔴 Skeleton loaders للصفحات الرئيسية
- **الجهد:** M
- **الملفات:** `AdminSkeleton.vue`, `AdminTableSkeleton.vue`, `dashboard/pages/Index.vue`
- **الوصف:** placeholders أثناء التحميل الأولي
- **أولوية التطبيق:**
  1. Dashboard KPI cards
  2. Orders Index table
  3. Support Chat messages
- **معايير القبول:**
  - [ ] shimmer animation خفيف
  - [ ] يظهر أثناء `router.visit` أو fetch أولي
  - [ ] لا layout shift عند اكتمال التحميل

---

### UX-12 🟡 تحسين Inertia progress bar
- **الجهد:** S
- **الملفات:** `resources/js/app.js`
- **الوصف:** تغيير لون progress bar من gray إلى cyan-600 + delay 200ms
- **معايير القبول:**
  - [ ] متسق مع brand accent

---

### UX-13 🟡 Empty states موحّدة
- **الجهد:** M
- **الملفات:** `AdminEmptyState.vue`
- **الوصف:** illustration/icon + title + description + optional CTA
- **معايير القبول:**
  - [ ] تُستخدم في Orders, Users, Settlements, Audit
  - [ ] مترجمة عبر `t()`

---

### UX-14 🟡 Global error handling
- **الجهد:** M
- **الملفات:** `AdminErrorBoundary.vue` أو Inertia `onError` handler
- **الوصف:** صفحة/بانر خطأ بدل white screen عند 500/419
- **معايير القبول:**
  - [ ] رسالة واضحة + زر "Retry" أو "Go to Dashboard"
  - [ ] session expired (419) → redirect login مع رسالة

---

## المرحلة 2 — Navigation & Discoverability

### UX-15 🔴 إضافة الصفحات المخفية إلى Sidebar
- **الجهد:** S
- **الملفات:** `resources/js/modules/admin/config/navigation.js`
- **الوصف:** إدراج:
  - Monitoring → مجموعة Platform أو Operations
  - Global Search → header shortcut (UX-18) + أو Platform
  - Provider Health → مجموعة Providers
  - Governance → مجموعة Platform
- **معايير القبول:**
  - [ ] كل صفحة reachable من sidebar
  - [ ] permissions صحيحة لكل رابط
  - [ ] أيقونات مناسبة (ليست كلها `governance`)

---

### UX-16 🔴 Breadcrumbs
- **الجهد:** M
- **الملفات:** `AdminBreadcrumbs.vue`, `AdminLayout.vue`, صفحات Show/Edit
- **الوصف:** مسار تنقل: Dashboard > Orders > Order #12345
- **معايير القبول:**
  - [ ] يظهر في Show/Edit/Create pages
  - [ ] RTL-aware (separator معكوس)
  - [ ] آخر عنصر non-clickable (current page)
  - [ ] `aria-label="Breadcrumb"`

---

### UX-17 🟡 Global Search في Header
- **الجهد:** L
- **الملفات:** `AdminLayout.vue`, `search/pages/Index.vue`, composable `useGlobalSearch.js`
- **الوصف:** input في header + Ctrl+K shortcut + dropdown نتائج سريعة
- **معايير القبول:**
  - [ ] Ctrl+K / Cmd+K يفتح البحث
  - [ ] نتائج grouped (Orders, Users, Support...)
  - [ ] Enter → صفحة Search الكاملة
  - [ ] keyboard navigation في النتائج

---

### UX-18 🟡 Quick actions menu
- **الجهد:** M
- **الملفات:** `AdminQuickActions.vue`, `AdminLayout.vue`
- **الوصف:** dropdown في header: New Support Ticket, New Settlement, Go to Monitoring
- **معايير القبول:**
  - [ ] permission-gated
  - [ ] keyboard accessible

---

### UX-19 🟢 Dashboard "What's next" section
- **الجهد:** M
- **الملفات:** `dashboard/pages/Index.vue`
- **الوصف:** بطاقات إجراءات مقترحة: "3 tickets need response", "2 approvals pending"
- **معايير القبول:**
  - [ ] links مباشرة للصفحات ذات الصلة
  - [ ] تختفي عند عدم وجود pending items

---

## المرحلة 3 — i18n & RTL

### UX-20 🔴 ترجمة صفحات Auth
- **الجهد:** M
- **الملفات:** `Pages/Auth/*.vue`, `useAdminLocale.js` (أو composable مشترك)
- **الوصف:** Login, ForgotPassword, ResetPassword, VerifyEmail — EN + AR
- **معايير القبول:**
  - [ ] toggle لغة على Login (أو detect من browser)
  - [ ] `dir="rtl"` على GuestLayout عند العربية
  - [ ] Tajawal font applied

---

### UX-21 🔴 توحيد styling Login مع Admin
- **الجهد:** M
- **التبعيات:** UX-01, UX-20
- **الملفات:** `Layouts/GuestLayout.vue`, `Pages/Auth/Login.vue`
- **الوصف:** slate/cyan بدل gray/indigo — جسر بصري بين Auth و Admin
- **معايير القبول:**
  - [ ] نفس rounded-2xl inputs
  - [ ] نفس primary button (slate-950)
  - [ ] logo + company name من platform props

---

### UX-22 🔴 ترجمة Customer Chat
- **الجهد:** L
- **الملفات:** `Pages/Support/Chat.vue`, locale dictionary
- **الوصف:** كل strings + RelativeTimeFormat يحترم locale
- **معايير القبول:**
  - [ ] AR/EN toggle
  - [ ] RTL layout للمحادثة
  - [ ] category/priority labels مترجمة

---

### UX-23 🟡 ترجمة Profile pages
- **الجهد:** S
- **الملفات:** `Pages/Profile/**/*.vue`
- **معايير القبول:**
  - [ ] UpdateProfile, UpdatePassword, DeleteUser — مترجمة

---

### UX-24 🟢 تقييم vue-i18n migration
- **الجهد:** L
- **الملفات:** `useAdminLocale.js` (~2900 سطر)
- **الوصف:** spike: هل ننقل dictionary إلى JSON + vue-i18n؟
- **معايير القبول:**
  - [ ] قرار موثّق (نعم/لا + أسباب)
  - [ ] إن نعم: POC في module واحد

---

## المرحلة 4 — Accessibility (A11y)

### UX-25 🔴 Skip-to-content link
- **الجهد:** S
- **الملفات:** `AdminLayout.vue`
- **معايير القبول:**
  - [ ] `<a href="#main-content">` visible on focus
  - [ ] `<main id="main-content">` في layout

---

### UX-26 🔴 Focus trap لجميع modals/drawers
- **الجهد:** M
- **التبعيات:** UX-04, UX-05
- **الوصف:** تطبيق على كل overlay في Admin (Orders, Support, Settlements)
- **معايير القبول:**
  - [ ] Tab cycling داخل modal
  - [ ] focus returns to trigger on close

---

### UX-27 🟡 استبدال emoji status في Monitoring
- **الجهد:** S
- **الملفات:** `monitoring/pages/Index.vue`
- **الوصف:** 🟢🟡🔴 → AdminBadge + text label
- **معايير القبول:**
  - [ ] لا reliance على color-only
  - [ ] screen reader text ("Healthy", "Degraded", "Down")

---

### UX-28 🟡 Heading hierarchy audit
- **الجهد:** M
- **الوصف:** مراجعة h1/h2/h3 في كل صفحة admin
- **معايير القبول:**
  - [ ] h1 واحد per page
  - [ ] لا skip levels (h1 → h3)
  - [ ] checklist per module

---

### UX-29 🟡 Confirm dialogs موحّدة
- **الجهد:** M
- **التبعيات:** UX-04
- **الملفات:** `useAdminConfirm.js`
- **الوصف:** استبدال `window.confirm()` بـ AdminModal confirm
- **معايير القبول:**
  - [ ] danger variant للحذف
  - [ ] keyboard: Enter confirm, Escape cancel
  - [ ] لا native confirm() في admin modules

---

### UX-30 🟢 WCAG AA audit (manual + axe)
- **الجهد:** L
- **الوصف:** تشغيل axe-core على 5 صفحات رئيسية
- **معايير القبول:**
  - [ ] تقرير issues + fix plan
  - [ ] contrast ratios ≥ 4.5:1 للنص

---

## المرحلة 5 — Responsive & Mobile

### UX-31 🟡 Card view للجداول على mobile
- **الجهد:** L
- **الملفات:** Orders Index, Users Index (POC)
- **الوصف:** `< md`: cards بدل table rows
- **معايير القبول:**
  - [ ] نفس البيانات، tap → Show page
  - [ ] لا horizontal scroll على mobile

---

### UX-32 🟡 Orders Show — mobile layout
- **الجهد:** M
- **الملفات:** `orders/pages/Show.vue`
- **الوصف:** tabs → accordion على mobile، drawer full-screen
- **معايير القبول:**
  - [ ] usable على 375px width
  - [ ] actions menu reachable بإبهام واحد

---

### UX-33 🟢 Support Show — mobile polish
- **الجهد:** M
- **الملفات:** `support/pages/Show.vue`
- **معايير القبول:**
  - [ ] resolution form usable on mobile
  - [ ] timeline readable without zoom

---

## المرحلة 6 — Module-Specific Polish

### UX-34 🟡 Dashboard — interactive charts
- **الجهد:** L
- **الملفات:** `dashboard/pages/Index.vue`
- **الوصف:** SVG sparklines → hover tooltips + click drill-down
- **معايير القبول:**
  - [ ] tooltip shows date + value
  - [ ] click revenue chart → Finance page filtered

---

### UX-35 🟡 Finance — export UX
- **الجهد:** M
- **الملفات:** `finance/pages/Index.vue`
- **الوصف:** زر Export CSV/PDF + toast on success
- **معايير القبول:**
  - [ ] loading state أثناء export
  - [ ] error toast on failure

---

### UX-36 🟡 Settlements — import progress
- **الجهد:** M
- **الملفات:** `settlements/pages/Show.vue`
- **الوصف:** progress bar أثناء import + row-by-row error summary
- **معايير القبول:**
  - [ ] لا freeze UI أثناء upload
  - [ ] toast عند اكتمال import

---

### UX-37 🟡 Notifications — template preview
- **الجهد:** S
- **الملفات:** `notifications/components/TemplateManager.vue`
- **الوصف:** live preview أثناء الكتابة (EN + AR side by side)
- **معايير القبول:**
  - [ ] variables highlighted
  - [ ] RTL preview for AR

---

### UX-38 🟢 AI Assistant — chat UI polish
- **الجهد:** M
- **الملفات:** `ai/pages/Index.vue`
- **الوصف:** typing indicator, message bubbles, copy response
- **معايير القبول:**
  - [ ] consistent with Support Chat patterns

---

## المرحلة 7 — Performance (Perceived)

### UX-39 🟡 Lazy load TipTap editor
- **الجهد:** S
- **الملفات:** `RichTextEditor.vue`, `content/pages/Form.vue`
- **الوصف:** `defineAsyncComponent` لـ TipTap
- **معايير القبول:**
  - [ ] bundle size reduction measurable
  - [ ] skeleton while loading editor

---

### UX-40 🟢 Virtual scrolling للجداول الكبيرة
- **الجهد:** XL
- **الوصف:** تقييم `@tanstack/vue-virtual` لـ Orders/Users عند 1000+ rows
- **معايير القبول:**
  - [ ] spike doc + decision
  - [ ] POC if justified

---

## ملخص الأولويات (Sprint Planning)

### Sprint 1 — Quick Wins (أسبوع 1) ✅
| ID | المهمة | الجهد | الحالة |
|----|--------|-------|--------|
| UX-15 | Sidebar: صفحات مخفية | S | ✅ |
| UX-25 | Skip-to-content | S | ✅ |
| UX-12 | Progress bar brand color | S | ✅ |
| UX-27 | Monitoring emoji → badges | S | ✅ |
| UX-01 | Tailwind tokens | M | ✅ |

### Sprint 2 — Design System Core (أسبوع 2) ✅
| ID | المهمة | الجهد | الحالة |
|----|--------|-------|--------|
| UX-02 | AdminButton | M | ✅ |
| UX-03 | AdminInput/Select | M | ✅ |
| UX-04 | AdminModal | L | ✅ |
| UX-05 | AdminDrawer | L | ✅ |

### Sprint 3 — Feedback & Nav (أسبوع 3) ✅
| ID | المهمة | الجهد | الحالة |
|----|--------|-------|--------|
| UX-10 | Toast system | M | ✅ |
| UX-11 | Skeleton loaders | M | ✅ |
| UX-16 | Breadcrumbs | M | ✅ |
| UX-13 | Empty states | M | ✅ |

### Sprint 4 — i18n Bridge (أسبوع 4) ✅
| ID | المهمة | الجهد | الحالة |
|----|--------|-------|--------|
| UX-20 | Auth translation | M | ✅ |
| UX-21 | Login styling bridge | M | ✅ |
| UX-22 | Chat translation | L | ✅ |
| UX-29 | Confirm dialogs | M | ✅ |

### Sprint 5 — Polish & Migration ✅
| ID | المهمة | الجهد | الحالة |
|----|--------|-------|--------|
| UX-09 | Page migration to DS (Search) | XL | ✅ جزئي |
| UX-17 | Global Search header | L | ✅ |
| UX-31 | Mobile card view | L | ✅ |
| UX-34 | Dashboard charts | L | ✅ |

---

## تتبع التقدم

```
المرحلة 0 (Design System):     [ ] 5/9
المرحلة 1 (Feedback):          [ ] 4/5   (UX-10 ✅, UX-11 ✅, UX-12 ✅, UX-13 ✅)
المرحلة 2 (Navigation):        [ ] 3/5   (UX-15 ✅, UX-16 ✅, UX-17 ✅)
المرحلة 3 (i18n):              [ ] 3/5   (UX-20 ✅, UX-21 ✅, UX-22 ✅)
المرحلة 4 (A11y):              [ ] 3/6   (UX-25 ✅, UX-27 ✅, UX-29 ✅)
المرحلة 5 (Responsive):        [ ] 1/3   (UX-31 ✅)
المرحلة 6 (Module Polish):     [ ] 1/5   (UX-34 ✅)
المرحلة 7 (Performance):       [ ] 0/2

الإجمالي: 21/40 مهمة
```

---

## ملاحظات

- **لا تبدأ UX-09 (migration)** قبل إكمال UX-01 → UX-08
- **Vitest UI tests** موجودة — أضف tests للمكونات الجديدة
- **مرجع التقييم الأصلي:** محادثة UI/UX audit (Aug 2026)
- **Owner مقترح:** frontend dev + review من UX/product

---

*آخر تحديث: 2026-08-23*
