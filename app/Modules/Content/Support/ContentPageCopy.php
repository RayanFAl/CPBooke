<?php

namespace App\Modules\Content\Support;

final class ContentPageCopy
{
    public static function privacyEn(): string
    {
        return <<<'HTML'
<h1>Privacy Policy</h1>
<p>Booke operates a travel marketplace application for flights, hotels, travel cover, and eSIM plans. This policy explains what personal data we collect, why we use it, and the rights you have. Last updated: 19 August 2026.</p>
<h2>1. Who we are</h2>
<p>When you create an account or complete a booking, Booke is the controller of the personal data you provide through the app. Airlines, hotels, insurers, and connectivity providers act as independent controllers for the services they deliver.</p>
<h2>2. Data we collect</h2>
<ul>
<li>Account data: name, email, phone number, password, and language preference.</li>
<li>Traveller profiles: passport or ID details, date of birth, nationality, and saved passengers.</li>
<li>Booking data: itinerary, hotel stay, insurance plan, eSIM package, payment status, and order history.</li>
<li>Payment metadata: transaction references, wallet movements, and billing country. We do not store full card numbers on Booke servers.</li>
<li>Device data: app version, device type, approximate location when you search nearby, and crash logs.</li>
<li>Support data: messages, attachments, and call-back requests you send to customer care.</li>
</ul>
<h2>3. How we use your data</h2>
<p>We use personal data to create and manage your account, search and confirm bookings, process refunds and wallet credits, send operational notifications (gate changes, vouchers, payment results), prevent fraud, and improve the app. Marketing messages are sent only if you opt in, and you can turn them off at any time from settings.</p>
<h2>4. Sharing</h2>
<p>We share only what is required with: (a) airlines, hotel suppliers, insurers, and eSIM providers to fulfil an order; (b) payment and wallet processors; (c) cloud hosting and push-notification vendors bound by contract; and (d) authorities when required by law. We do not sell personal data.</p>
<h2>5. Retention</h2>
<p>Account data is kept while your account is active. Booking and invoice records are retained for the period required by tax and consumer rules, then deleted or anonymised. Support tickets are kept for up to 24 months unless a longer period is legally required.</p>
<h2>6. Security</h2>
<p>We use encrypted transport (HTTPS), access controls, and audit logs. No method of transmission is completely secure; please protect your password and enable two-factor authentication when available.</p>
<h2>7. Your rights</h2>
<p>Subject to applicable law, you may request access, correction, deletion, or a copy of your data, and you may object to direct marketing. Use in-app support or the contact details in the app store listing. You may also lodge a complaint with your local data protection authority.</p>
<h2>8. Children</h2>
<p>Booke accounts are intended for adults. Traveller details for minors may be added by a parent or guardian solely to complete a booking.</p>
<h2>9. International transfers</h2>
<p>Because travel is cross-border, some suppliers process data outside your country. We require appropriate contractual safeguards where they apply.</p>
<h2>10. Changes</h2>
<p>We will update this policy when our practices change. The revised text is published on this page and, where required, notified in the app.</p>
HTML;
    }

    public static function privacyAr(): string
    {
        return <<<'HTML'
<h1>سياسة الخصوصية</h1>
<p>تشغّل Booke تطبيقاً لحجز السفر يشمل الطيران والفنادق وتغطية السفر وباقات eSIM. توضّح هذه السياسة البيانات الشخصية التي نجمعها، وسبب استخدامها، وحقوقك. آخر تحديث: 19 أغسطس 2026.</p>
<h2>1. من نحن</h2>
<p>عند إنشاء حساب أو إتمام حجز، تكون Booke الجهة المسؤولة عن البيانات التي تقدّمها عبر التطبيق. شركات الطيران والفنادق وشركات التأمين ومزوّدو الاتصال جهات مستقلة بالنسبة للخدمات التي تقدّمها.</p>
<h2>2. البيانات التي نجمعها</h2>
<ul>
<li>بيانات الحساب: الاسم، البريد الإلكتروني، رقم الهاتف، كلمة المرور، وتفضيل اللغة.</li>
<li>ملفات المسافرين: بيانات الجواز أو الهوية، تاريخ الميلاد، الجنسية، والمسافرون المحفوظون.</li>
<li>بيانات الحجز: خط السير، الإقامة، خطة التأمين، باقة eSIM، حالة الدفع، وسجل الطلبات.</li>
<li>بيانات الدفع: مراجع العمليات، حركة المحفظة، ودولة الفوترة. لا نخزّن أرقام البطاقات كاملة على خوادم Booke.</li>
<li>بيانات الجهاز: إصدار التطبيق، نوع الجهاز، الموقع التقريبي عند البحث القريب، وسجلات الأعطال.</li>
<li>بيانات الدعم: الرسائل والمرفقات وطلبات التواصل التي ترسلها لخدمة العملاء.</li>
</ul>
<h2>3. كيف نستخدم بياناتك</h2>
<p>نستخدم البيانات لإنشاء حسابك وإدارته، والبحث عن الحجوزات وتأكيدها، ومعالجة الاسترداد ورصيد المحفظة، وإرسال إشعارات تشغيلية (تغيّر البوابة، القسائم، نتيجة الدفع)، ومنع الاحتيال، وتحسين التطبيق. الرسائل التسويقية تُرسل فقط إذا وافقت عليها، ويمكن إيقافها من الإعدادات في أي وقت.</p>
<h2>4. المشاركة</h2>
<p>نشارك الحد الأدنى اللازم مع: (أ) شركات الطيران ومزوّدي الفنادق والتأمين وeSIM لتنفيذ الطلب؛ (ب) معالجي الدفع والمحفظة؛ (ج) مزوّدي الاستضافة والإشعارات بموجب عقود؛ (د) الجهات الرسمية عند وجوب القانون. لا نبيع البيانات الشخصية.</p>
<h2>5. مدة الاحتفاظ</h2>
<p>تُحفظ بيانات الحساب طالما بقي الحساب نشطاً. تُحفظ سجلات الحجز والفواتير للمدة التي تفرضها قواعد الضرائب وحماية المستهلك ثم تُحذف أو تُجعل مجهولة الهوية. تُحفظ تذاكر الدعم حتى 24 شهراً ما لم يُطلب قانوناً مدة أطول.</p>
<h2>6. الأمان</h2>
<p>نستخدم تشفير النقل (HTTPS) وضوابط صلاحيات وسجلات تدقيق. لا توجد وسيلة نقل آمنة بالكامل؛ احمِ كلمة المرور وفعّل التحقق بخطوتين عند توفره.</p>
<h2>7. حقوقك</h2>
<p>بحسب القانون المعمول به يمكنك طلب الاطلاع أو التصحيح أو الحذف أو نسخة من بياناتك، والاعتراض على الرسائل التسويقية. استخدم الدعم داخل التطبيق أو بيانات التواصل في صفحة المتجر. كما يمكنك تقديم شكوى لدى جهة حماية البيانات في بلدك.</p>
<h2>8. الأطفال</h2>
<p>حسابات Booke مخصّصة للبالغين. يمكن لولي الأمر إضافة بيانات مسافر قاصر فقط لإتمام حجز.</p>
<h2>9. النقل الدولي</h2>
<p>لأن السفر عبر الحدود، قد يعالج بعض المزوّدين البيانات خارج بلدك. نطلب ضمانات تعاقدية مناسبة حيث ينطبق ذلك.</p>
<h2>10. التعديلات</h2>
<p>نحدّث هذه السياسة عند تغيّر ممارساتنا. يُنشر النص المحدَّث في هذه الصفحة، ويُخطر به داخل التطبيق عند الحاجة.</p>
HTML;
    }

    public static function termsEn(): string
    {
        return <<<'HTML'
<h1>Terms and Conditions</h1>
<p>These terms govern your use of the Booke mobile application and website. By creating an account or placing an order you agree to this agreement. Last updated: 19 August 2026.</p>
<h2>1. The service</h2>
<p>Booke is an intermediary. We display offers from airlines, hotels, insurers, and connectivity partners and help you pay and manage orders. The transport, stay, cover, or connectivity contract is between you and the supplier named on the booking confirmation.</p>
<h2>2. Eligibility</h2>
<p>You must be legally able to enter a contract. You are responsible for the accuracy of passenger names, travel documents, and contact details. Bookings made with incorrect data may be non-refundable under supplier rules.</p>
<h2>3. Accounts</h2>
<p>Keep your login credentials confidential. Activity performed from your account is treated as yours unless you notify us of unauthorised access without delay. We may suspend an account that is used for fraud, abuse, or chargeback misuse.</p>
<h2>4. Prices and payment</h2>
<p>Prices include the amounts shown at checkout before you confirm. Taxes, supplier fees, and payment charges are displayed when known. Payment is taken when you confirm the order, unless a later capture is stated. Wallet credit, if offered, follows the wallet rules in the app.</p>
<h2>5. Changes, cancellation, and refunds</h2>
<p>Airline fare rules, hotel cancellation windows, insurance cooling-off rights, and eSIM activation rules are set by the supplier. Booke processes change and refund requests according to those rules and any Booke service fee shown at checkout. Refunds return to the original payment method or wallet when the supplier allows it, and may take several business days after the supplier approves.</p>
<h2>6. Documents and check-in</h2>
<p>You must complete check-in, visa, health, and entry requirements. Booke does not guarantee boarding, hotel check-in, cover payment, or network registration if a supplier or authority refuses service.</p>
<h2>7. Acceptable use</h2>
<p>You may not scrape the app, interfere with other users, submit false bookings, or use Booke to test stolen payment instruments. We may cancel orders and withhold unused credit where we reasonably suspect fraud.</p>
<h2>8. Liability</h2>
<p>Booke is not liable for delays, schedule changes, overbooking, property standards, medical outcomes, or network coverage of suppliers. Our liability for the Booke platform itself is limited to the Booke service fees you paid for the affected order, except where liability cannot be limited by law (including death or personal injury caused by negligence, or fraud).</p>
<h2>9. Intellectual property</h2>
<p>The Booke name, logos, and app content belong to Booke or its licensors. You receive a limited licence to use the app for personal booking purposes.</p>
<h2>10. Governing law</h2>
<p>These terms are governed by the laws of the country in which Booke is established, without affecting mandatory consumer protections in your country of residence. If a clause is unenforceable, the rest remains in force.</p>
<h2>11. Contact</h2>
<p>For complaints about a Booke order, use in-app support and include the order number. Supplier issues (lost baggage, room complaints, claim files, SIM activation) should also be raised with the named supplier.</p>
HTML;
    }

    public static function termsAr(): string
    {
        return <<<'HTML'
<h1>الشروط والأحكام</h1>
<p>تنظّم هذه البنود استخدامك لتطبيق وموقع Booke. بإنشاء حساب أو تقديم طلب فإنك توافق على هذه الاتفاقية. آخر تحديث: 19 أغسطس 2026.</p>
<h2>1. طبيعة الخدمة</h2>
<p>Booke وسيط. نعرض عروضاً من شركات الطيران والفنادق والتأمين والاتصال ونساعدك على الدفع وإدارة الطلبات. عقد النقل أو الإقامة أو التغطية أو الاتصال يكون بينك وبين المزوّد المذكور في تأكيد الحجز.</p>
<h2>2. الأهلية</h2>
<p>يجب أن تكون أهلاً للتعاقد. أنت مسؤول عن دقة أسماء المسافرين ووثائق السفر وبيانات التواصل. الحجوزات ببيانات خاطئة قد تكون غير قابلة للاسترداد وفق قواعد المزوّد.</p>
<h2>3. الحسابات</h2>
<p>حافظ على سرية بيانات الدخول. تُعامل النشاطات من حسابك على أنها صادرة عنك ما لم تُبلغنا فوراً عن استخدام غير مصرّح. يجوز تعليق حساب يُستخدم للاحتيال أو الإساءة أو إساءة الاعتراض على الدفع.</p>
<h2>4. الأسعار والدفع</h2>
<p>الأسعار تشمل المبالغ الظاهرة عند إتمام الطلب قبل التأكيد. تُعرض الضرائب ورسوم المزوّد ورسوم الدفع عند معرفتها. يُحصَّل الدفع عند تأكيد الطلب ما لم يُذكر تحصيل لاحق. رصيد المحفظة إن وُجد يتبع قواعد المحفظة داخل التطبيق.</p>
<h2>5. التعديل والإلغاء والاسترداد</h2>
<p>قواعد أجرة الطيران ونوافذ إلغاء الفندق وحقوق التراجع عن التأمين وقواعد تفعيل eSIM يحدّدها المزوّد. تعالج Booke طلبات التعديل والاسترداد وفق تلك القواعد وأي رسم خدمة لـ Booke يظهر عند الدفع. يعود المبلغ إلى وسيلة الدفع الأصلية أو المحفظة عندما يسمح المزوّد، وقد يستغرق عدة أيام عمل بعد موافقة المزوّد.</p>
<h2>6. الوثائق وإجراءات السفر</h2>
<p>يجب استكمال إجراءات تسجيل الوصول والتأشيرة والصحة ومتطلبات الدخول. لا تضمن Booke الصعود للطائرة أو دخول الفندق أو صرف التغطية أو تسجيل الشبكة إذا رفض المزوّد أو الجهة الرسمية الخدمة.</p>
<h2>7. الاستخدام المقبول</h2>
<p>لا يجوز استخراج بيانات التطبيق آلياً، أو الإضرار بالمستخدمين، أو تقديم حجوزات وهمية، أو استخدام Booke لتجربة وسائل دفع مسروقة. يجوز إلغاء الطلبات وحجز الرصيد غير المستخدم عند الاشتباه المعقول بالاحتيال.</p>
<h2>8. المسؤولية</h2>
<p>لا تتحمل Booke تأخير الرحلات أو تغيّر الجدول أو الحجز الزائد أو مستوى المنشأة أو النتائج الطبية أو تغطية الشبكة لدى المزوّدين. تقتصر مسؤولية منصة Booke على رسوم خدمة Booke المدفوعة للطلب المتأثر، باستثناء ما لا يجوز تحديده قانوناً (بما في ذلك الوفاة أو الإصابة الناتجة عن إهمال، أو الاحتيال).</p>
<h2>9. الملكية الفكرية</h2>
<p>اسم Booke والشعارات ومحتوى التطبيق ملك لـ Booke أو المرخّصين. تحصل على ترخيص محدود لاستخدام التطبيق لأغراض الحجز الشخصي.</p>
<h2>10. القانون الواجب التطبيق</h2>
<p>تخضع هذه البنود لقانون الدولة التي تأسست فيها Booke، دون المساس بالحماية الإلزامية للمستهلك في بلد إقامتك. إذا بطل بند تبقى بقية البنود سارية.</p>
<h2>11. التواصل</h2>
<p>للشكاوى المتعلقة بطلب Booke استخدم الدعم داخل التطبيق مع رقم الطلب. مسائل المزوّد (أمتعة، شكوى غرفة، ملف مطالبة، تفعيل الشريحة) تُرفع أيضاً إلى المزوّد المذكور.</p>
HTML;
    }

    public static function flightEn(): string
    {
        return <<<'HTML'
<h1>Flight booking policy</h1>
<p>This policy applies to flight orders placed through Booke. Airline fare rules for the selected offer remain binding and are shown beside this text at checkout.</p>
<h2>1. The offer</h2>
<p>Fares, baggage, seat maps, and schedule times come from the airline or aggregating partner. Availability can change until you receive a confirmed PNR / ticket number in the app.</p>
<h2>2. Passenger names</h2>
<p>Enter names exactly as they appear in the travel document that will be used at the airport. Name corrections after ticketing follow airline fees and may be impossible on some fares.</p>
<h2>3. Ticketing and confirmation</h2>
<p>A booking is confirmed only when payment succeeds and the app shows a confirmed status with a booking reference. Pending or failed payments do not hold the fare.</p>
<h2>4. Changes and cancellation</h2>
<p>Voluntary changes, no-show, and refunds follow the airline fare rules (including non-refundable and non-changeable tickets). Booke forwards your request to the supplier and adds any Booke handling fee displayed before you confirm.</p>
<h2>5. Schedule changes and disruption</h2>
<p>If the airline changes time, routing, or cancels a sector, your rights are those granted by the airline and applicable passenger regulations. Booke will surface the update in the order and notifications when the supplier sends it.</p>
<h2>6. Baggage and extras</h2>
<p>Included bags are those listed on the offer. Extra bags, seats, and meals bought later may be priced by the airline at check-in and are not always available via Booke.</p>
<h2>7. Travel documents</h2>
<p>You must hold valid passports, visas, and health documents for every country on the itinerary, including transits. Denied boarding for document issues is not refundable unless the airline fare rules say otherwise.</p>
HTML;
    }

    public static function flightAr(): string
    {
        return <<<'HTML'
<h1>سياسة حجز الطيران</h1>
<p>تنطبق هذه السياسة على طلبات الطيران عبر Booke. تبقى قواعد أجرة الناقل للعرض المختار ملزمة وتظهر بجانب هذا النص عند إتمام الحجز.</p>
<h2>1. العرض</h2>
<p>الأجرة والأمتعة وخريطة المقاعد وأوقات الجدول تأتي من شركة الطيران أو الشريك المجمّع. قد يتغيّر التوفر إلى أن يصلك رقم الحجز / التذكرة المؤكد داخل التطبيق.</p>
<h2>2. أسماء المسافرين</h2>
<p>أدخل الأسماء كما هي في وثيقة السفر التي ستُستخدم في المطار. تصحيح الاسم بعد إصدار التذكرة يخضع لرسوم الناقل وقد يتعذّر في بعض التعريفات.</p>
<h2>3. إصدار التذكرة والتأكيد</h2>
<p>يُعتبر الحجز مؤكداً فقط عند نجاح الدفع وظهور حالة مؤكدة مع مرجع حجز في التطبيق. الدفع المعلّق أو الفاشل لا يحجز الأجرة.</p>
<h2>4. التعديل والإلغاء</h2>
<p>التعديل الاختياري وعدم الحضور والاسترداد تخضع لقواعد أجرة الناقل (بما في ذلك التذاكر غير القابلة للاسترداد أو التعديل). تُمرّر Booke طلبك إلى المزوّد وتضيف أي رسم معالجة يظهر قبل التأكيد.</p>
<h2>5. تغيّر الجدول والاضطراب</h2>
<p>إذا غيّرت الشركة الوقت أو المسار أو ألغت قطاعاً، فحقوقك هي ما يمنحه الناقل وأنظمة حماية المسافر المعمول بها. تعرض Booke التحديث في الطلب والإشعارات عندما يصل من المزوّد.</p>
<h2>6. الأمتعة والإضافات</h2>
<p>الحقائب المشمولة هي المذكورة في العرض. الحقائب الإضافية والمقاعد والوجبات التي تُشترى لاحقاً قد يسعرها الناقل عند تسجيل الوصول وقد لا تتوفر دائماً عبر Booke.</p>
<h2>7. وثائق السفر</h2>
<p>يجب أن تحمل جوازات وتأشيرات ووثائق صحية سارية لكل دولة في الرحلة بما فيها الترانزيت. رفض الصعود بسبب الوثائق لا يُسترد إلا إذا نصّت قواعد الأجرة على غير ذلك.</p>
HTML;
    }

    public static function hotelEn(): string
    {
        return <<<'HTML'
<h1>Hotel booking policy</h1>
<p>This policy applies to hotel orders placed through Booke. Property rules (check-in age, extras, and cancellation window) for the selected rate remain binding.</p>
<h2>1. The rate</h2>
<p>Room type, meal plan, and cancellation deadline are those shown on the offer. Photos and amenities are supplied by the property or partner and may differ slightly from the room assigned at check-in.</p>
<h2>2. Guest names</h2>
<p>The lead guest must match the name on the booking and present a valid ID at the desk. Additional guests must not exceed the occupancy of the Booked room.</p>
<h2>3. Confirmation</h2>
<p>The stay is confirmed when payment (or the stated guarantee) succeeds and the app shows a confirmed voucher or booking ID.</p>
<h2>4. Cancellation and no-show</h2>
<p>Free cancellation applies only inside the window printed on the rate. After that deadline, or in case of no-show, the property may charge the first night or the full stay as stated on the offer. Booke cannot override a non-refundable rate.</p>
<h2>5. Check-in and special requests</h2>
<p>Early check-in, late check-out, cots, and view requests are subject to the property and are not guaranteed unless confirmed in writing on the voucher.</p>
<h2>6. Damages and extras</h2>
<p>Minibar, parking, resort fees, and damage deposits are usually collected by the hotel. Disputes about the room or billing at the property should be raised with the hotel and then with Booke support with your order number.</p>
<h2>7. Force majeure</h2>
<p>If the hotel cannot honour the reservation (overbooking or closure), the supplier must offer an alternative or a refund under its rules. Booke will help you claim that remedy.</p>
HTML;
    }

    public static function hotelAr(): string
    {
        return <<<'HTML'
<h1>سياسة حجز الفنادق</h1>
<p>تنطبق هذه السياسة على طلبات الفنادق عبر Booke. تبقى قواعد المنشأة (عمر تسجيل الوصول والإضافات ونافذة الإلغاء) الخاصة بالسعر المختار ملزمة.</p>
<h2>1. السعر</h2>
<p>نوع الغرفة وخطة الوجبات وموعد آخر إلغاء مجاني هي ما يظهر في العرض. الصور والخدمات يقدّمها الفندق أو الشريك وقد تختلف قليلاً عن الغرفة عند الوصول.</p>
<h2>2. أسماء النزلاء</h2>
<p>يجب أن يطابق النزيل الرئيسي الاسم على الحجز ويبرز هوية سارية في الاستقبال. لا يجوز أن يتجاوز عدد النزلاء إشغال الغرفة المحجوزة.</p>
<h2>3. التأكيد</h2>
<p>تُؤكد الإقامة عند نجاح الدفع (أو الضمان المذكور) وظهور قسيمة أو رقم حجز مؤكد في التطبيق.</p>
<h2>4. الإلغاء وعدم الحضور</h2>
<p>الإلغاء المجاني يسري فقط داخل النافذة المطبوعة على السعر. بعد ذلك الموعد أو في حال عدم الحضور قد يحصّل الفندق ليلة أولى أو كامل الإقامة كما هو مذكور في العرض. لا تستطيع Booke تجاوز سعر غير قابل للاسترداد.</p>
<h2>5. تسجيل الوصول والطلبات الخاصة</h2>
<p>الدخول المبكر والخروج المتأخر والسرير الإضافي وإطلالة معيّنة تخضع للفندق ولا تُضمن إلا إذا أُكدت كتابياً على القسيمة.</p>
<h2>6. التلفيات والإضافات</h2>
<p>الميني بار وموقف السيارات ورسوم المنتجع وتأمين التلفيات غالباً يحصّلها الفندق. نزاعات الغرفة أو الفاتورة في المنشأة تُرفع للفندق ثم لدعم Booke مع رقم الطلب.</p>
<h2>7. الظروف القاهرة</h2>
<p>إذا تعذّر على الفندق الوفاء بالحجز (حجز زائد أو إغلاق) فعلى المزوّد تقديم بديل أو استرداد وفق قواعده. تساعدكم Booke في المطالبة بذلك.</p>
HTML;
    }

    public static function insuranceEn(): string
    {
        return <<<'HTML'
<h1>Travel cover policy</h1>
<p>This policy describes how Booke sells travel cover. The insurer’s wording, exclusions, and claim process for the selected plan remain binding and are shown with the plan.</p>
<h2>1. Intermediary role</h2>
<p>Booke distributes plans from licensed insurers or underwriting partners. The insurance contract is between you and the insurer named on the certificate.</p>
<h2>2. Eligibility and disclosure</h2>
<p>Cover is valid only if the trip dates, destination, traveller ages, and pre-existing conditions match what you entered. False or incomplete answers can void a claim.</p>
<h2>3. When cover starts</h2>
<p>Cover starts at the time stated on the certificate after successful payment. Some benefits (for example cancellation) may start at purchase; others start when travel begins. Read the plan summary.</p>
<h2>4. Cooling-off and cancellation</h2>
<p>If the insurer offers a cooling-off period and no claim has been made, you may cancel through Booke within that window. After travel has started, unused premium is usually non-refundable unless the insurer’s wording says otherwise.</p>
<h2>5. Claims</h2>
<p>File claims with the insurer using the contacts on the certificate. Keep invoices, medical reports, and delay confirmations. Booke can share your order reference but does not decide claim outcomes.</p>
<h2>6. Limits</h2>
<p>Medical, baggage, delay, and liability limits are those printed on the plan. Destinations under travel advisories may be excluded. Sports and high-risk activities need the add-ons listed in the wording.</p>
HTML;
    }

    public static function insuranceAr(): string
    {
        return <<<'HTML'
<h1>سياسة التأمين</h1>
<p>تصف هذه السياسة كيف تبيع Booke تغطية السفر. يبقى نص شركة التأمين والاستثناءات وإجراءات المطالبة الخاصة بالخطة المختارة ملزماً ويظهر مع الخطة.</p>
<h2>1. دور الوسيط</h2>
<p>توزّع Booke خططاً من شركات تأمين مرخّصة أو شركاء اكتتاب. عقد التأمين بينك وبين الشركة المذكورة على الشهادة.</p>
<h2>2. الأهلية والإفصاح</h2>
<p>التغطية سارية فقط إذا طابقت تواريخ الرحلة والوجهة وأعمار المسافرين والحالات السابقة ما أدخلته. الإجابات الناقصة أو غير الصحيحة قد تُسقط المطالبة.</p>
<h2>3. بدء التغطية</h2>
<p>تبدأ التغطية في الوقت المذكور على الشهادة بعد نجاح الدفع. بعض المنافع (مثل إلغاء الرحلة) قد تبدأ من الشراء وأخرى من بداية السفر. اقرأ ملخص الخطة.</p>
<h2>4. التراجع والإلغاء</h2>
<p>إذا منحت الشركة فترة تراجع ولم تُقدَّم مطالبة، يمكنك الإلغاء عبر Booke داخل تلك النافذة. بعد بدء السفر غالباً لا يُرد القسط غير المستخدم إلا إذا نصّت الوثيقة على غير ذلك.</p>
<h2>5. المطالبات</h2>
<p>قدّم المطالبة لشركة التأمين عبر بيانات التواصل على الشهادة. احتفظ بالفواتير والتقارير الطبية وإثباتات التأخير. تستطيع Booke مشاركة رقم الطلب ولا تقرر نتيجة المطالبة.</p>
<h2>6. الحدود</h2>
<p>حدود العلاج والأمتعة والتأخير والمسؤولية هي المطبوعة على الخطة. الوجهات الخاضعة لتحذيرات سفر قد تُستثنى. الرياضات والأنشطة عالية الخطورة تحتاج الإضافات المذكورة في النص.</p>
HTML;
    }

    public static function esimEn(): string
    {
        return <<<'HTML'
<h1>eSIM policy</h1>
<p>This policy applies to eSIM data plans bought through Booke. The connectivity provider’s fair-use and coverage map for the selected plan remain binding.</p>
<h2>1. Compatibility</h2>
<p>Your device must be eSIM-capable and carrier-unlocked. Booke is not responsible if the device rejects the profile or if the local network requires a physical SIM.</p>
<h2>2. Delivery</h2>
<p>After payment the QR code or activation details appear in the order. Install the profile before travel when the provider recommends it. Do not delete the profile until you no longer need the plan.</p>
<h2>3. Activation and validity</h2>
<p>Validity usually starts at first connection in a covered country or at the time printed on the plan, whichever the provider specifies. Unused data expires at the end of the validity period and is not rolled over unless the plan says so.</p>
<h2>4. Coverage and speed</h2>
<p>Coverage is that of the partner networks listed for the plan. Speeds vary with congestion, device, and local regulation. Voice and SMS are included only if the plan description says so.</p>
<h2>5. Refunds</h2>
<p>If the QR was never redeemed and the provider allows a refund window, Booke will request a refund. After successful installation or first use, data plans are generally non-refundable except where the profile cannot be attached for a proven provider fault.</p>
<h2>6. Acceptable use</h2>
<p>Tethering, torrenting, and commercial resale may be blocked under the provider’s fair-use policy. Fraudulent or duplicated activations can be cancelled without credit.</p>
HTML;
    }

    public static function esimAr(): string
    {
        return <<<'HTML'
<h1>سياسة eSIM</h1>
<p>تنطبق هذه السياسة على باقات بيانات eSIM المشتراة عبر Booke. تبقى سياسة الاستخدام العادل وخريطة التغطية الخاصة بالخطة المختارة ملزمة.</p>
<h2>1. التوافق</h2>
<p>يجب أن يدعم جهازك eSIM وأن يكون غير مقفول على شبكة معيّنة. لا تتحمل Booke رفض الجهاز للملف الشخصي أو إذا كانت الشبكة المحلية تتطلب شريحة فعلية.</p>
<h2>2. التسليم</h2>
<p>بعد الدفع يظهر رمز QR أو بيانات التفعيل في الطلب. ثبّت الملف قبل السفر إذا أوصى المزوّد بذلك. لا تحذف الملف قبل انتهاء حاجتك للباقة.</p>
<h2>3. التفعيل والصلاحية</h2>
<p>تبدأ الصلاحية عادة عند أول اتصال في دولة مغطاة أو في الوقت المطبوع على الخطة وفق ما يحدّده المزوّد. البيانات غير المستخدمة تنتهي بانتهاء المدة ولا تُرحَّل إلا إذا نصّت الخطة على ذلك.</p>
<h2>4. التغطية والسرعة</h2>
<p>التغطية هي شبكات الشريك المذكورة للخطة. تتفاوت السرعة حسب الازدحام والجهاز والتنظيم المحلي. المكالمات والرسائل مشمولة فقط إذا ذكر وصف الخطة ذلك.</p>
<h2>5. الاسترداد</h2>
<p>إذا لم يُستخدم رمز QR وسمح المزوّد بنافذة استرداد، تطلب Booke الاسترداد. بعد التثبيت الناجح أو أول استخدام غالباً لا تُرد الباقة إلا إذا تعذّر ربط الملف بسبب عطل مثبت لدى المزوّد.</p>
<h2>6. الاستخدام المقبول</h2>
<p>قد يُحظر تقاسم الإنترنت والتحميل المكثف وإعادة البيع التجاري وفق سياسة الاستخدام العادل. يمكن إلغاء التفعيل المكرر أو الاحتيالي دون رصيد.</p>
HTML;
    }
}
