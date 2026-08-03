# CPBooke — خارطة المنتج المستقبلية (Product Roadmap)

هذا الملف يلخّص قدرات التوسيع المقترحة للمنصة، مع تقييم الوضع الحالي والأولويات.

المرجع الحالي للنظام: [`cpbooke-system-overview.md`](./cpbooke-system-overview.md)

---

## الأولوية المعتمدة (تركيز وكالة سفر — ليبيا/المنطقة)

هذا الترتيب هو **المرجع التشغيلي الحالي** لما يجب بناؤه، وليس قائمة أمنيات كاملة.

### تقييم الوضع الحالي (وكالة)
| البعد | التقييم |
|--------|---------|
| الوظائف الأساسية | 9.5/10 |
| القابلية للتوسع | 9/10 |
| جاهزية الإنتاج | 8.5/10 |

الخلاصة: الأساس قوي وجاهز للإنتاج تقريباً؛ الفجوة الأساسية في **إدارة المورد كشريك** و**التكلفة/الربح** و**الرقابة بالموافقات**، ثم التسوية والتشغيل الآلي.

### ذهب — ابنِ أولاً
| الترتيب | القدرة | لماذا الآن؟ | الحالة اليوم |
|---------|--------|-------------|--------------|
| 1 | **Supplier Management** | تحويل Provider من محفظة إلى شريك (عقود، عمولة، دورة فوترة، ائتمان، تواصل، حالة تكامل) | Providers + Wallets فقط |
| 2 | **Cost & Profit Engine** | الإجابة على: كم كلّفنا؟ كم ربحنا؟ الهامش؟ أرباح BookNow هذا الشهر؟ | سعر بيع قوي، تكلفة المزوّد ضعيفة |
| 3 | **Workflow + Approvals** | Refund / Cancel / Wallet Adjustment / Price Override لا تُنفَّذ مباشرة بلا رقابة | حالات ثابتة + تنفيذ مباشر بالصلاحية |

### فضة — بعد استقرار الذهبي
| الترتيب | القدرة | ملاحظة |
|---------|--------|--------|
| 4 | **Settlement** | مقارنة CPBooke ↔ فاتورة المزوّد ↔ فروقات ↔ تسوية (يعتمد على Cost + Supplier) |
| 5 | **Automation** | تنبيه انخفاض المحفظة، تصعيد تذكرة 48س، إعادة محاولة API |
| 6 | **Dashboard Analytics** | ربح يومي، دول، أداء موظفين، إلغاء، SLA دعم، اتجاه المحافظ (يحتاج Cost لأرقام صحيحة) |
| 7 | **نظام مستندات** | فاتورة، إيصال، تذكرة PDF، Boarding Pass، مرفقات العميل |

### برونز — لاحقاً
| الترتيب | القدرة | ملاحظة |
|---------|--------|--------|
| 8 | **CRM بسيط** | طلبات + تذاكر + إنفاق + ولاء + ملاحظات (توسيع Users Show) |
| 9 | **Monitoring** | صحة APIs، أخطاء تكامل، Queues، خدمات متوقفة |
| 10 | **Multi-Tenant** | فقط عند قرار بيع CPBooke كـ SaaS — قرار معماري كبير |

### لا يُضاف الآن (تجنّب التعقيد بلا قيمة تشغيلية)
- Chat AI
- Blockchain
- دردشة داخلية بين الموظفين
- ميزات «ذكية» كثيرة غير مربوطة بتدفق العمل اليومي

### موجة التنفيذ المقترحة (مختصرة)
```
Supplier Profile موسّع
        ↓
Cost & Profit على الطلب/البند
        ↓
Approvals (+ Workflow خفيف للعمليات الحساسة)
        ↓
Settlement شهري مع المزوّد
        ↓
Automation + Analytics + Documents
        ↓
CRM → Monitoring → (Multi-Tenant عند الحاجة)
```

**تبعية مهمة:** Settlement وAnalytics الربحية بدون Cost Engine تبقى تقريبية.  
Supplier Management وCost Engine يُفضَّل تصميمهما معاً حتى لو نُفِّذا على مرحلتين.

---

## كيف تُقرأ الأولوية؟

| الرمز | المعنى |
|-------|--------|
| P0 | أساس يجب وضعه قريباً (يمنع أخطاء تشغيل/مالية كبيرة) |
| P1 | قيمة عالية للوكالة خلال الأشهر القادمة |
| P2 | يميّز المنتج ويفتح نمو/SaaS |
| P3 | نضج متقدم بعد استقرار الأساسيات |

| الحالة | المعنى |
|--------|--------|
| موجود جزئياً | فيه أساس يمكن البناء عليه |
| غير موجود | يحتاج تصميم وتنفيذ من الصفر |
| موجود كبذرة | جداول/مفاهيم فقط أو تغطية ضيقة |

---

## ملخص سريع للـ 20 نقطة

| # | القدرة | الحالة اليوم | أولوية مقترحة |
|---|--------|--------------|----------------|
| 1 | Workflow Engine | حالات طلب ثابتة | P1 |
| 2 | Task Management | غير موجود (Orders/Support فقط) | P1 |
| 3 | Approval System | ✅ Engine + Retry Execution | P0 |
| 4 | Accounting Integration | Ledger + CSV جزئي | P2 |
| 5 | Supplier Management | ✅ ملف مورد + `/admin/suppliers` | P1 |
| 6 | Settlement System | ✅ فترات + فاتورة + مطابقة + إغلاق | P0 |
| 7 | Reporting / BI Dashboard | Finance/Dashboard جيد لكن محدود | P1 |
| 8 | Product Engine | service_type جزئي (flight/hotel/insurance) | P1 |
| 9 | Rule Engine | if/loyalty/pricing مبعثر | P2 |
| 10 | Audit Dashboard | سجلات متفرقة | P1 |
| 11 | Global Search | بحث داخل كل وحدة فقط | P1 |
| 12 | Automation | أحداث/Jobs جزئية | P2 |
| 13 | Provider Health | غير موجود | P2 |
| 14 | Notification Center | Templates + logs + retry | P2 |
| 15 | Attachment System | غير ناضج كمنصة ملفات موحّدة | P1 |
| 16 | Multi Company (SaaS) | شركة واحدة | P2→P0 عند البيع كـ SaaS |
| 17 | Partner API | API موبايل فقط | P2 |
| 18 | Cost Engine | totals موجودة، تكلفة المزوّد ضعيفة | P0 |
| 19 | Document Generator | محدود (مثل تقارير طباعة) | P1 |
| 20 | CRM خفيف | صفحة مستخدم غنية لكن ليست CRM | P1 |

---

## التفصيل

### 1) Workflow Engine
**المطلوب:** حالات ومسارات قابلة للتخصيص لكل وكالة (موافقة مدير، انتظار مزوّد…).  
**اليوم:** حالات طلب ثابتة في الكود.  
**لماذا مهم:** كل وكالة تعمل differently؛ الحالات الصلبة تحدّ التوسع.  
**يعتمد على:** نموذج حالات مرن + صلاحيات + (لاحقاً) Approvals.  
**أولوية:** P1  
**ملاحظة:** ابدأ بـ Workflow للطلبات فقط، لا تعمّم على كل الكيانات من اليوم الأول.

### 2) Task Management
**المطلوب:** مهام تشغيل مرتبطة بطلب/تذكرة: تعيين، استحقاق، أولوية.  
**اليوم:** Orders + Support فقط.  
**لماذا مهم:** التشغيل اليومي يحتاج To-Do واضح غير محصور بالشات.  
**أولوية:** P1  
**تقاطع:** مع Approvals وWorkflow وCRM.

### 3) Approval System ⭐
**المطلوب:** موافقات متعددة الخطوات (Refund، Deposit محفظة…).  
**اليوم:** الموظف ذو الصلاحية ينفّذ مباشرة.  
**لماذا مهم:** يمنع أخطاء مالية كبيرة.  
**أولوية:** P0  
**اقتراح سياسة أولية:**
- أي Refund فوق حد معيّن → موافقة Finance
- أي Deposit/Adjustment لمحفظة مزوّد → موافقة Super Admin/Finance
- تغيير سعر/Markup لاحقاً → موافقة

### 4) Accounting Integration
**المطلوب:** تصدير إلى QuickBooks / Odoo / SAP / Zoho أو CSV محاسبي.  
**اليوم:** Ledger داخلي + تصدير Finance CSV.  
**أولوية:** P2 (بعد استقرار Cost Engine + Settlement)  
**ابدأ بـ:** قالب CSV محاسبي قياسي قبل تكامل البائعين.

### 5) Supplier Management
**المطلوب:** عمولة، دورة تسوية، credit limit، جهات اتصال، عقود، فواتير.  
**اليوم:** `providers` + wallets.  
**أولوية:** P1  
**تسلسل منطقي:** توسيع Provider Profile → ثم Settlement يعتمد عليه.

### 6) Settlement System ⭐⭐⭐⭐⭐
**المطلوب:** طلبات ↔ فاتورة مزوّد ↔ فرق ↔ تسوية شهرية.  
**اليوم:** خصم wallet فقط، بلا مطابقة شهرية.  
**لماذا مهم:** قلب تشغيل السفر B2B.  
**أولوية:** P0  
**يعتمد على:** Cost Engine (#18) + Supplier (#5) + حركات Wallet.

### 7) Reporting
**المطلوب:** Profit, Margin, Top Airlines/Countries, Refund/Cancel rates, AOV, SLA, Wallet trends…  
**اليوم:** Dashboard + Finance analytics موجودة جزئياً.  
**أولوية:** P1  
**يعتمد بشدة على:** Cost Engine لأرقام الربح الحقيقية.

### 8) Product Engine
**المطلوب:** طبقة منتج موحّدة (Flights/Hotels/Visa/Cars/… ).  
**اليوم:** `service_type` + مسارات ناضجة أكثر للرحلات.  
**أولوية:** P1  
**نصيحة:** Product catalog + pricing/cost adapters لكل نوع، بدل نسخ منطق الحجز 6 مرات.

### 9) Rule Engine
**المطلوب:** قواعد بدل `if` صلبة (ولاء، موافقات، تنبيهات رصيد).  
**اليوم:** ولاء/تسعير في كود مخصص.  
**أولوية:** P2  
**يمكن أن يغذي:** Approvals + Automation + Pricing.

### 10) Audit Dashboard
**المطلوب:** من عدّل؟ من استرجع؟ من لمس Wallet؟  
**اليوم:** سجلات موزعة (طلبات، دعم، مالية، حوكمة).  
**أولوية:** P1  
**ابدأ بـ:** Event stream موحّد + فلاتر فاعل/كيان/تاريخ.

### 11) Global Search
**المطلوب:** بحث واحد: اسم، هاتف، PNR، رقم طلب، مزوّد…  
**اليوم:** بحث داخل كل صفحة.  
**أولوية:** P1  
**خيارات:** DB full-text أولاً، ثم Scout/Meilisearch/Elastic لاحقاً.

### 12) Automation
**المطلوب:** قواعد زمنية/شرطية (إغلاق تذكرة، تنبيه wallet، إشعار بعد الموافقة).  
**اليوم:** Events + Queued jobs جزئياً.  
**أولوية:** P2  
**يعتمد على:** Rule Engine أو محرك أتمتة بسيط فوق الأحداث الحالية.

### 13) Provider Health
**المطلوب:** حالة API، Latency، Errors، Wallet، Last Sync.  
**اليوم:** غير موجود (والحجز أصلاً خارجي/sync).  
**أولوية:** P2  
**قيمة أعلى** عندما يصبح عندكم استدعاءات HTTP خارجة فعّالة للمزوّدين.

### 14) Notification Center
**المطلوب:** حملات، شرائح، جدولة، تحليلات فتح/تسليم.  
**اليوم:** Templates + logs + retry.  
**أولوية:** P2  
**افصل:** Transactional notifications (موجودة) عن Marketing campaigns (جديد).

### 15) Attachment System
**المطلوب:** مرفقات لأي Order/Ticket/Refund (جواز، فاتورة، PDF…).  
**اليوم:** لا يوجد نظام مرفقات موحّد ناضج.  
**أولوية:** P1  
**تصميم:** `attachments` polymorphic + صلاحيات + تخزين خاص.

### 16) Multi Company (SaaS) ⭐⭐⭐⭐⭐
**المطلوب:** عزل بيانات Company A/B/C.  
**اليوم:** مستأجر واحد.  
**أولوية:** P2 الآن، و**P0** قبل بيع المنتج كـ SaaS.  
**تحذير:** أغلى قرار معماري؛ يفضّل تصميم `company_id` مبكراً حتى لو بقيتم شركة واحدة لفترة.

### 17) Partner API
**المطلوب:** API للشركاء + Webhooks + OAuth/API Keys.  
**اليوم:** API موبايل Sanctum.  
**أولوية:** P2  
**ابدأ بـ:** API keys + webhooks لأحداث Order/Refund قبل OAuth الكامل.

### 18) Cost Engine ⭐⭐⭐⭐⭐
**المطلوب:** Selling / Supplier Cost / Commission / Tax / Markup / Profit / Net Profit.  
**اليوم:** `grand_total` + بعض الحقول؛ التقارير والـ wallet تميل لسعر البيع.  
**أولوية:** P0  
**هذه حجر الأساس لـ:** Settlement، Reporting، وربحية صحيحة.

### 19) Document Generator
**المطلوب:** Invoice, Receipt, Voucher, Itinerary, Refund letter…  
**اليوم:** بعض صفحات الطباعة (مثل تقارير الدعم).  
**أولوية:** P1  
**يعتمد على:** بيانات الطلب + Cost/Tax الصحيحة.

### 20) CRM بسيط
**المطلوب:** ملف عميل موحّد: طلبات، ملاحظات، مكالمات، تذاكر، ولاء، إيراد، وجهات مفضلة.  
**اليوم:** صفحة مستخدم أدمن غنية نسبياً لكنها ليست CRM كامل.  
**أولوية:** P1  
**يمكن دمجه تدريجياً** فوق Users Show + Support + Orders بدل بناء CRM منفصل ضخم.

---

## ترتيب تنفيذ مقترح (موجات)

### الموجة A — أساس مالي صحيح (أولاً) ✅ جاري/منفّذ جزئياً
1. **Supplier Management (#5 / ذهب 1)** — ✅ ملف مورد كامل + واجهة `/admin/suppliers`
2. **Cost Engine (#18 / ذهب 2)** — ✅ حقول بيع/تكلفة/ربح على الطلب + خصم المحفظة حسب `supplier_cost`
3. **Approval System (#3)** — ✅ Engine + قواعد + Retry Execution
4. **Settlement System (#6)** — ✅ فترات تسوية + استيراد فاتورة + مطابقة فروقات + إغلاق
5. **Provider Health NOC** — ✅ لوحة صحة المزودين + Health Score + Alerts
6. **Monitoring & Observability** — ✅ System Health + Queues/Failures + Scheduler jobs
7. **Audit Center + Timeline + Global Search** — ✅ Unified audit trail, entity timelines, ops search
8. **Production Readiness (Phase A)** — ✅ جاري/أساس: Security hardening · Indexes · Backup · Deploy/DR docs  
   → التفاصيل: [`docs/production/01-production-readiness.md`](./production/01-production-readiness.md)
9. **Phase B — Testing** · **Phase C — Documentation** — مخطط في `docs/production/`
10. **Feature Freeze v1.0** — بعد اكتمال A/B الحرجة

**النتيجة:** أرقام ربح حقيقية + رقابة + مطابقة مزوّد.

### الموجة B — تشغيل الفريق
5. **Task Management (#2)**  
6. **Workflow Engine (#1)** للطلبات (نسخة أولى قابلة للضبط)  
7. **Attachments (#15)**  
8. **Global Search (#11)**  
9. **Audit Dashboard (#10)**

**النتيجة:** فريق تشغيل أسرع وأضبط.

### الموجة C — ذكاء وتقارير
10. **Reporting الاحترافي (#7)**  
11. **Document Generator (#19)**  
12. **CRM خفيف (#20)**  
13. **Product Engine (#8)** لتوحيد الفندق/التأشيرة/…

### الموجة D — نمو المنصة / SaaS
14. تصميم **Multi Company (#16)** مبكراً حتى لو التفعيل لاحقاً  
15. **Automation (#12)** + بدايات **Rule Engine (#9)**  
16. **Notification Center (#14)** للتسويق  
17. **Provider Health (#13)**  
18. **Accounting Integration (#4)**  
19. **Partner API (#17)**

---

## ما موجود اليوم ويمكن البناء عليه فوراً

- Ledger مالي + Finance dashboard  
- Provider + Provider Wallets + WalletService عام  
- RBAC ناضج  
- Support actions (refund/compensation)  
- Order statuses + history  
- Loyalty + Pricing preview  
- Notifications transactional  
- Governance كبذرة رقابة  
- Users Show كبذرة CRM  

---

## قرارات يجب تثبيتها قبل التنفيذ الكبير

1. **هل الهدف القريب وكالة واحدة أم SaaS متعدد الشركات؟**  
   إن كان SaaS خلال سنة → ابدأ `company_id` مبكراً.
2. **هل الربح يُحسب من تكلفة المزوّد أم من تقدير العمولة؟**  
   يحدد تصميم Cost Engine.
3. **هل الموافقات إلزامية دائماً أم حسب مبلغ/دور؟**  
   يحدد Approval Engine.
4. **هل التسوية شهرية ثابتة أم حسب كل مزوّد؟**  
   يحدد Settlement + Supplier profile.

---

## الخلاصة

هذه الـ 20 نقطة ليست «ميزات تجميلية»؛ هي تحويل CPBooke من:

> لوحة تشغيل ما بعد الحجز جيدة

إلى:

> منصة وكالة سفر قابلة للبيع والتشغيل المؤسسي (Operations + Finance + Suppliers + SaaS)

**الأهم الآن (حسب أثر مالي وتشغيلي):**
**Cost Engine → Approvals → Settlement → Provider Health → ثم Feature Freeze v1.0 (استقرار/مراقبة).**

بدون Cost Engine وSettlement، معظم تقارير الربح والتسوية ستبقى تقريبية حتى لو بنيتم Dashboards جميلة.
