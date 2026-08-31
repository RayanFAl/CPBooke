/**
 * Hover/tap explanations for admin screens.
 * More specific route names must be listed before their prefixes.
 */
export const pageHelpEntries = [
    {
        match: 'admin.customers.edit',
        en: 'Correct the customer’s name, phone, or email. This is what appears on tickets and login. It does not change their password or role.',
        ar: 'صحّح اسم العميل أو هاتفه أو بريده. هذا ما يظهر على التذكرة وتسجيل الدخول. لا يغيّر كلمة المرور ولا الدور.',
    },
    {
        match: 'admin.customers.show',
        en: 'Customer CRM: searches, logins, bookings, wallet, and support. Use Edit identity to fix name or phone.',
        ar: 'ملف العميل: البحث، الدخول، الحجوزات، المحفظة، والدعم. عدّل الهوية لتصحيح الاسم أو الهاتف.',
    },
    {
        match: 'admin.customers',
        en: 'List of app customers only. Open a row to see that person’s travel activity and wallet. This is not the staff list.',
        ar: 'قائمة عملاء التطبيق فقط. اضغط على صف لعرض نشاطه ومحفظته. هذه ليست قائمة فريق العمل.',
    },
    {
        match: 'admin.team.create',
        en: 'Create a Control Panel user: choose a role and permissions. This person logs into admin, not the customer app.',
        ar: 'إنشاء مستخدم للوحة التحكم: اختر الدور والصلاحيات. هذا الحساب يدخل لوحة الإدارة وليس تطبيق العملاء.',
    },
    {
        match: 'admin.team.show',
        en: 'Staff profile: roles and access only. There is no customer wallet here.',
        ar: 'ملف موظف: الأدوار والصلاحيات فقط. لا توجد محفظة عميل هنا.',
    },
    {
        match: 'admin.team',
        en: 'People who work in the Control Panel. Add, freeze, or open a member to change their access.',
        ar: 'فريق العمل الذين يدخلون لوحة التحكم. أضف عضواً أو جمّده أو افتحه لتعديل صلاحياته.',
    },
    {
        match: 'admin.users.edit',
        en: 'Edit a staff account’s name, contact, role, and permissions.',
        ar: 'تعديل اسم موظف لوحة التحكم وتواصله ودوره وصلاحياته.',
    },
    {
        match: 'admin.users.create',
        en: 'Create a Control Panel user: choose a role and permissions. This person logs into admin, not the customer app.',
        ar: 'إنشاء مستخدم للوحة التحكم: اختر الدور والصلاحيات. هذا الحساب يدخل لوحة الإدارة وليس تطبيق العملاء.',
    },
    {
        match: 'admin.users.show',
        en: 'User profile. Customers show CRM and wallet. Staff show roles and access.',
        ar: 'ملف المستخدم. العميل يظهر معه النشاط والمحفظة. الموظف يظهر معه الدور والصلاحيات.',
    },
    {
        match: 'admin.orders.show',
        en: 'One booking: tickets, payment, provider, and status. Change status only when you know the operational impact.',
        ar: 'حجز واحد: التذكرة، الدفع، المزوّد، والحالة. غيّر الحالة فقط إذا فهمت أثرها التشغيلي.',
    },
    {
        match: 'admin.orders',
        en: 'All bookings from the app. Filter by status or search a PNR / booking reference, then open a row for ticket details.',
        ar: 'كل حجوزات التطبيق. صفِّ حسب الحالة أو ابحث برقم PNR / مرجع الحجز، ثم افتح الصف لعرض التذكرة.',
    },
    {
        match: 'admin.support.reports',
        en: 'Support performance reports: volumes, waiting time, and resolution quality.',
        ar: 'تقارير أداء الدعم: حجم التذاكر، وقت الانتظار، وجودة الحل.',
    },
    {
        match: 'admin.support.create',
        en: 'Open a support ticket on behalf of a customer when they contacted you outside the app.',
        ar: 'افتح تذكرة دعم نيابة عن عميل تواصل معكم خارج التطبيق.',
    },
    {
        match: 'admin.support.show',
        en: 'Live chat with the customer. Reply, change ticket status, and take booking actions like refund from here when allowed.',
        ar: 'محادثة مباشرة مع العميل. رد، غيّر حالة التذكرة، ونفّذ إجراءات الحجز مثل الاسترداد عند السماح بذلك.',
    },
    {
        match: 'admin.support',
        en: 'Customer support inbox. Open a ticket to chat, then resolve or escalate.',
        ar: 'صندوق دعم العملاء. افتح تذكرة للدردشة ثم أغلقها أو صعّدها.',
    },
    {
        match: 'admin.finance',
        en: 'Money overview: revenue, costs, and reconciliation signals. It does not replace wallets or settlements.',
        ar: 'نظرة مالية عامة: الإيرادات والتكاليف وإشارات المطابقة. لا تغني عن المحافظ أو التسويات.',
    },
    {
        match: 'admin.customer-wallets.show',
        en: 'One customer wallet: balance, freeze, credit, or deduct. Every movement is stored in the statement.',
        ar: 'محفظة عميل واحدة: الرصيد، التجميد، الشحن، أو الخصم. كل حركة تُحفظ في الكشف.',
    },
    {
        match: 'admin.customer-wallets',
        en: 'Customer prepaid wallets. Search by name or wallet number, then open to top up or freeze.',
        ar: 'محافظ العملاء مسبقة الدفع. ابحث بالاسم أو رقم المحفظة ثم افتحها للشحن أو التجميد.',
    },
    {
        match: 'admin.approvals.show',
        en: 'One sensitive action waiting for a second person: approve to execute, or reject with a reason.',
        ar: 'إجراء حسّاس ينتظر شخصاً ثانياً: وافق لتنفيذه، أو ارفضه مع سبب.',
    },
    {
        match: 'admin.approvals',
        en: 'Maker-checker queue. Refunds, cancellations, wallet moves, and settlements wait here until another admin approves.',
        ar: 'طابور الموافقة الثنائية. الاسترداد والإلغاء وحركات المحفظة والتسويات تنتظر هنا حتى يوافق أدمن آخر.',
    },
    {
        match: 'admin.suppliers.show',
        en: 'One provider: commercial terms, linked wallets, contacts, and contract notes.',
        ar: 'مزوّد واحد: الشروط التجارية، المحافظ المرتبطة، جهات الاتصال، وملاحظات العقد.',
    },
    {
        match: 'admin.suppliers',
        en: 'Travel supplier profiles: commission, settlement cycle, credit limits, and linked wallets.',
        ar: 'ملفات مزوّدي السفر: العمولة، دورة التسوية، حدود الائتمان، والمحافظ المرتبطة.',
    },
    {
        match: 'admin.provider-wallets.create',
        en: 'Open a wallet for a provider so Booke can hold or move supplier funds.',
        ar: 'افتح محفظة لمزوّد حتى يتمكن بوكي من حفظ أو تحريك أموال المزوّد.',
    },
    {
        match: 'admin.provider-wallets.show',
        en: 'Provider money account: deposit, adjust, and see the ledger. Large moves may need Approvals.',
        ar: 'حساب أموال المزوّد: إيداع، تعديل، وعرض الكشف. الحركات الكبيرة قد تحتاج موافقة من صفحة الموافقات.',
    },
    {
        match: 'admin.provider-wallets',
        en: 'Wallets for travel providers, not customers. Open one to deposit or review movements.',
        ar: 'محافظ مزوّدي السفر وليست محافظ العملاء. افتح محفظة للإيداع أو مراجعة الحركات.',
    },
    {
        match: 'admin.settlements.create',
        en: 'Start a settlement period for a provider. Orders with supplier cost in that date range are loaded automatically.',
        ar: 'ابدأ فترة تسوية لمزوّد. الطلبات التي لها تكلفة مزوّد ضمن التواريخ تُحمَّل تلقائياً.',
    },
    {
        match: 'admin.settlements.show',
        en: 'Compare Booke costs with the provider invoice, resolve differences, then approve or close the period.',
        ar: 'قارن تكاليف بوكي مع فاتورة المزوّد، عالج الفروقات، ثم وافق على الفترة أو أغلقها.',
    },
    {
        match: 'admin.settlements',
        en: 'Reconcile what you owe a provider for a date range. Create a period, import the invoice, then close it.',
        ar: 'مطابقة ما تستحقونه لمزوّد خلال فترة. أنشئ فترة، استورد الفاتورة، ثم أغلقها.',
    },
    {
        match: 'admin.provider-health',
        en: 'Are provider APIs healthy? Timeouts and error rates appear here so operations can react before bookings fail.',
        ar: 'هل واجهات المزوّدين سليمة؟ تظهر هنا المهلات ونسب الأخطاء حتى يتدخل التشغيل قبل فشل الحجوزات.',
    },
    {
        match: 'admin.airports.create',
        en: 'Add an airport used in search and featured lists.',
        ar: 'أضف مطاراً يُستخدم في البحث والقوائم المميزة.',
    },
    {
        match: 'admin.airports.edit',
        en: 'Edit airport names, codes, and whether it is featured in the app.',
        ar: 'عدّل أسماء المطار ورموزه وما إذا كان مميزاً في التطبيق.',
    },
    {
        match: 'admin.airports',
        en: 'Airport directory for the app. Search, feature popular airports, and keep names in English and Arabic.',
        ar: 'دليل المطارات للتطبيق. ابحث، ميّز المطارات الشائعة، واحفظ الأسماء بالإنجليزية والعربية.',
    },
    {
        match: 'admin.home.banners',
        en: 'Home hero banners in the mobile app. Upload an image and choose what happens when the user taps it.',
        ar: 'بنرات الصفحة الرئيسية في التطبيق. ارفع صورة واختر ماذا يحدث عند الضغط عليها.',
    },
    {
        match: 'admin.home.offers',
        en: 'Promotional offer cards on the home screen. Different from Options & Market product tiles.',
        ar: 'بطاقات العروض الترويجية في الرئيسية. تختلف عن بلاطات المنتجات في الخيارات والسوق.',
    },
    {
        match: 'admin.home',
        en: 'Mobile home content: banners and offer cards. Use Options & Market for product images (insurance, eSIM, extras).',
        ar: 'محتوى الرئيسية في الموبايل: البنرات وبطاقات العروض. صور المنتجات (تأمين، eSIM، باقات) تُدار من الخيارات والسوق.',
    },
    {
        match: 'admin.catalog.create',
        en: 'Add a product type with two images: one for Options, one for Market. The app reads them from the catalog API.',
        ar: 'أضف نوع منتج بصورتين: واحدة للخيارات وواحدة للسوق. التطبيق يقرأها من API الكتالوج.',
    },
    {
        match: 'admin.catalog.edit',
        en: 'Change titles, images, or the screen the card opens. Toggle Options or Market if it should appear on one screen only.',
        ar: 'عدّل العناوين أو الصور أو الشاشة التي تفتحها البطاقة. أوقف الخيارات أو السوق إذا كان الظهور في شاشة واحدة فقط.',
    },
    {
        match: 'admin.catalog',
        en: 'Product tiles on the mobile Options and Market screens (travel insurance, orange, mandatory, eSIM, extras). Add more types anytime.',
        ar: 'بلاطات المنتجات في شاشتي الخيارات والسوق (تأمين سفر، برتقالي، إجباري، eSIM، باقات). يمكنك إضافة أنواع جديدة في أي وقت.',
    },
    {
        match: 'admin.content',
        en: 'Legal pages and product policies shown in the app (privacy, terms, checkout notes). This is text, not product images.',
        ar: 'الصفحات القانونية وسياسات المنتجات في التطبيق (الخصوصية، الشروط، نصوص الدفع). هذا نص وليست صور منتجات.',
    },
    {
        match: 'admin.loyalty',
        en: 'Loyalty program: tiers, rules, and benefits. Points and upgrades follow these rules after bookings.',
        ar: 'برنامج الولاء: المستويات والقواعد والمزايا. النقاط والترقيات تتبع هذه القواعد بعد الحجوزات.',
    },
    {
        match: 'admin.notifications',
        en: 'Push, SMS, and email: templates, failed deliveries, and channel health. Retry a failed send from here.',
        ar: 'الإشعارات (دفع، SMS، بريد): القوالب، الإرسالات الفاشلة، وصحة القنوات. أعد إرسال الفاشل من هنا.',
    },
    {
        match: 'admin.search',
        en: 'Search orders, customers, tickets, wallets, and settlements in one box. Use this when you only have a phone, PNR, or email.',
        ar: 'ابحث عن الطلبات والعملاء والتذاكر والمحافظ والتسويات من صندوق واحد. استخدمه إذا كان لديك هاتف أو PNR أو بريد فقط.',
    },
    {
        match: 'admin.monitoring',
        en: 'System health: queues, jobs, and operational alerts. Check here if bookings or notifications suddenly stop.',
        ar: 'صحة النظام: الطوابير والمهام والتنبيهات. راجعها إذا توقفت الحجوزات أو الإشعارات فجأة.',
    },
    {
        match: 'admin.governance',
        en: 'Control-center overview: access, finance integrity, delivery health, and loyalty movement in one place.',
        ar: 'نظرة حوكمة شاملة: الصلاحيات، سلامة المالية، صحة الإرسال، وحركة الولاء في مكان واحد.',
    },
    {
        match: 'admin.audit',
        en: 'Who changed what, when, and from where. Use this for investigations — it does not change data.',
        ar: 'من غيّر ماذا ومتى ومن أين. للتحقيقات فقط — لا يغيّر البيانات.',
    },
    {
        match: 'admin.partners.show',
        en: 'This partner’s API keys and webhooks. Keys let their system query orders. Webhooks notify them of payments and refunds.',
        ar: 'مفاتيح هذا الشريك وويب هوكه. المفتاح يسمح لنظامهم بالاستعلام عن الطلبات. الويب هوك يُعلمهم بالدفع والاسترداد.',
    },
    {
        match: 'admin.partners.create',
        en: 'Register an external company that will call Booke by API (bank, agency, or another app). Not a travel supplier.',
        ar: 'سجّل شركة خارجية ستتصل بـ بوكي عبر API (بنك أو وكالة أو تطبيق آخر). ليس مزوّد سفر.',
    },
    {
        match: 'admin.partners.edit',
        en: 'Update the partner’s name, status, or contact. Keys and webhooks stay on the partner profile.',
        ar: 'عدّل اسم الشريك أو حالته أو تواصله. المفاتيح والويب هوك تبقى في صفحة ملف الشريك.',
    },
    {
        match: 'admin.partners',
        en: 'External API partners (bank, agency, or another app) that connect to Booke. Not a travel supplier. Give them an API key and optional webhooks for order and refund events. Leave empty if you have no such company yet.',
        ar: 'شركاء API خارجيون (بنك أو وكالة أو تطبيق آخر) يرتبطون بـ بوكي. ليسوا مزوّدي سفر. أعطهم مفتاح API واختياريّاً إشعارات للطلبات والاسترداد. اترك الصفحة فارغة إذا لا توجد شركة من هذا النوع بعد.',
    },
    {
        match: 'admin.settings',
        en: 'Company, currency, commissions, and feature flags. Changes here affect the whole platform.',
        ar: 'الشركة، العملة، العمولات، وخصائص النظام. التغيير هنا يؤثر على المنصة كلها.',
    },
    {
        match: 'admin.mobile-app',
        en: 'Android APK releases and update links. Upload a build so the app can tell users to update.',
        ar: 'إصدارات تطبيق أندرويد وروابط التحديث. ارفع نسخة حتى يطلب التطبيق من المستخدمين التحديث.',
    },
    {
        match: 'admin.ai.logs',
        en: 'What customers asked the travel assistant, and what it answered. Use this to spot bad replies.',
        ar: 'ماذا سأل العملاء المساعد الذكي وماذا أجاب. استخدمه لاكتشاف الإجابات الخاطئة.',
    },
    {
        match: 'admin.ai',
        en: 'Travel AI assistant settings. Turn it off here if you need to stop automated answers in the app.',
        ar: 'إعدادات مساعد السفر الذكي. أوقفه من هنا إذا أردت إيقاف الإجابات التلقائية في التطبيق.',
    },
    {
        match: 'admin.dashboard',
        en: 'Today’s snapshot: app downloads, searches, bookings, money, and alerts. Play Store counts are not included — only what Booke can see.',
        ar: 'صورة اليوم: تحميلات التطبيق، البحث، الحجوزات، المال، والتنبيهات. أرقام متجر بلاي غير مشمولة — فقط ما تراه بوكي.',
    },
];
