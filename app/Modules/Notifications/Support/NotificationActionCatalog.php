<?php

namespace App\Modules\Notifications\Support;

final class NotificationActionCatalog
{
    /**
     * New actionable templates (copy + channels). Inbox metadata lives in NotificationInboxContract.
     *
     * @return list<array{code: string, name: string, category: string, description: string, subject: string, body: string, ar_subject: string, ar_body: string, channels: list<string>, variables: list<string>}>
     */
    public static function templates(): array
    {
        $order = ['user_name', 'order_id', 'order_reference', 'route', 'destination', 'deep_link'];
        $amount = ['user_name', 'amount', 'currency', 'deep_link'];

        return [
            self::t('PASSPORT_EXPIRY_REMINDER', 'Passport expiry reminder', NotificationTemplateCategories::REMINDERS, 'Check passport validity before an international trip.', 'Check your passport before travelling to {destination}', 'Your passport expires on {expiry_date}. Renew it before your trip to {destination}.', 'تأكد من صلاحية جواز سفرك', 'جواز سفرك ينتهي في {expiry_date}. جدّده قبل رحلتك إلى {destination}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'destination', 'expiry_date', 'deep_link']),
            self::t('DOCUMENT_REQUIRED', 'Document required', NotificationTemplateCategories::ORDERS, 'A document is required to complete the booking.', 'A document is required for {order_reference}', 'Upload the missing travel document to complete booking #{order_reference}.', 'مطلوب مستند لإكمال الحجز', 'ارفع المستند المطلوب لإكمال الحجز #{order_reference}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),
            self::t('DOCUMENT_VERIFICATION_REQUIRED', 'Document verification required', NotificationTemplateCategories::ORDERS, 'The uploaded document needs verification.', 'Verify your travel document', 'We need to verify a document for booking #{order_reference}.', 'مطلوب التحقق من المستند', 'نحتاج التحقق من مستند لحجز #{order_reference}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),
            self::t('VISA_REMINDER', 'Visa reminder', NotificationTemplateCategories::REMINDERS, 'Visa may be required for the destination.', 'Check visa requirements for {destination}', 'A visa may be required for your trip to {destination}.', 'تذكير بالتأشيرة', 'قد تحتاج تأشيرة لرحلتك إلى {destination}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'destination', 'deep_link']),
            self::t('VISA_DOCUMENT_MISSING', 'Visa document missing', NotificationTemplateCategories::ORDERS, 'Visa document is missing for the booking.', 'Visa document missing for {order_reference}', 'Add your visa document to complete booking #{order_reference}.', 'مستند التأشيرة ناقص', 'أضف مستند التأشيرة لإكمال الحجز #{order_reference}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),

            self::t('ONLINE_CHECKIN_OPEN', 'Online check-in open', NotificationTemplateCategories::FLIGHTS, 'Online check-in is now available.', 'Check-in is open for {destination}', 'You can now complete check-in and get your boarding pass for {route}.', 'فتح تسجيل الوصول', 'يمكنك الآن إكمال Check-in والحصول على بطاقة الصعود لرحلة {route}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),
            self::t('CHECKIN_REMINDER_24H', 'Check-in reminder 24h', NotificationTemplateCategories::REMINDERS, 'Reminder to check in 24 hours before departure.', 'Check in for your flight to {destination}', 'Online check-in closes soon. Complete it now for {route}.', 'تذكير Check-in', 'سجّل وصولك الآن لرحلة {route}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),
            self::t('CHECKIN_REMINDER_3H', 'Check-in reminder 3h', NotificationTemplateCategories::REMINDERS, 'Last reminder to check in.', 'Last call to check in for {destination}', 'Check-in is closing. Complete it before you go to the airport.', 'آخر تذكير Check-in', 'تسجيل الوصول يوشك أن يُغلق. أكمله قبل التوجه إلى المطار.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),
            self::t('BOARDING_PASS_AVAILABLE', 'Boarding pass available', NotificationTemplateCategories::FLIGHTS, 'Boarding pass is ready.', 'Your boarding pass is ready', 'Open your boarding pass for {route}.', 'بطاقة الصعود جاهزة', 'بطاقة الصعود لرحلة {route} جاهزة.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),

            self::t('GATE_ASSIGNED', 'Gate assigned', NotificationTemplateCategories::FLIGHTS, 'Critical. Departure gate assigned.', 'Your gate is {to_value}', 'Go to gate {to_value} for {route}.', 'بوابة رحلتك', 'بوابة رحلتك: {to_value}. التوجه إلى البوابة الآن.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'route', 'to_value', 'deep_link']),
            self::t('GATE_CHANGED', 'Gate changed', NotificationTemplateCategories::FLIGHTS, 'Critical. Departure gate changed.', 'Gate changed to {to_value}', 'Gate changed from {from_value} to {to_value} for {route}.', 'تم تغيير البوابة', 'تغيّرت البوابة من {from_value} إلى {to_value}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'route', 'from_value', 'to_value', 'deep_link']),
            self::t('BOARDING_STARTED', 'Boarding started', NotificationTemplateCategories::FLIGHTS, 'Critical. Boarding has started.', 'Boarding has started', 'Boarding for {route} has started. Go to gate {to_value}.', 'بدأ الصعود للطائرة', 'بدأ الصعود لرحلة {route}. توجه إلى البوابة {to_value}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'route', 'to_value', 'deep_link']),
            self::t('BOARDING_FINAL_CALL', 'Boarding final call', NotificationTemplateCategories::FLIGHTS, 'Critical. Final boarding call.', 'Final call for {route}', 'This is the final call for {route}. Go to the gate now.', 'النداء الأخير للصعود', 'هذا النداء الأخير لرحلة {route}. توجه إلى البوابة الآن.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),
            self::t('BOARDING_CLOSED', 'Boarding closed', NotificationTemplateCategories::FLIGHTS, 'Critical. Boarding closed.', 'Boarding is closed', 'Boarding for {route} is closed.', 'أُغلق باب الصعود', 'أُغلق باب الصعود لرحلة {route}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),

            self::t('BAGGAGE_ALLOWANCE_UPDATED', 'Baggage allowance updated', NotificationTemplateCategories::FLIGHTS, 'Included baggage changed.', 'Baggage allowance updated', 'Your trip now includes {to_value}. Add extra bags if you need them.', 'تم تحديث وزن الأمتعة', 'رحلتك تسمح الآن بـ {to_value}. أضف حقيبة إضافية إذا احتجت.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'to_value', 'deep_link']),
            self::t('BAGGAGE_ADDED', 'Extra baggage added', NotificationTemplateCategories::FLIGHTS, 'Extra baggage purchased.', 'Extra baggage added', 'Extra baggage was added to booking #{order_reference}.', 'تمت إضافة أمتعة', 'تمت إضافة أمتعة إضافية للحجز #{order_reference}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),
            self::t('BAGGAGE_PRICE_CHANGED', 'Baggage price changed', NotificationTemplateCategories::FLIGHTS, 'Paid baggage price changed.', 'Baggage price updated', 'The extra-bag price for your trip changed to {to_value} {currency}.', 'تغيّر سعر الأمتعة', 'سعر الحقيبة الإضافية أصبح {to_value} {currency}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'to_value', 'currency', 'deep_link']),
            self::t('BAGGAGE_REMINDER', 'Extra baggage reminder', NotificationTemplateCategories::OFFERS, 'Reminder to add extra baggage before departure.', 'Need an extra bag?', 'Your trip includes {to_value}. Add extra baggage before you fly.', 'تذكير الأمتعة', 'رحلتك تسمح بـ {to_value}. تحتاج حقيبة إضافية؟ أضفها قبل السفر.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'to_value', 'deep_link']),

            self::t('SEAT_ASSIGNED', 'Seat assigned', NotificationTemplateCategories::FLIGHTS, 'A seat was assigned.', 'Your seat is {to_value}', 'Seat {to_value} was assigned for {route}.', 'تم تعيين مقعدك', 'مقعدك هو {to_value} على رحلة {route}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'route', 'to_value', 'deep_link']),
            self::t('SEAT_CHANGED', 'Seat changed', NotificationTemplateCategories::FLIGHTS, 'Assigned seat changed.', 'Your seat changed to {to_value}', 'Your seat changed from {from_value} to {to_value}.', 'تم تغيير مقعدك', 'تغيّر مقعدك من {from_value} إلى {to_value}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'from_value', 'to_value', 'deep_link']),
            self::t('SEAT_UPGRADE_AVAILABLE', 'Seat upgrade available', NotificationTemplateCategories::OFFERS, 'A better seat is available. User must confirm; admin does not edit the seat in DB.', 'A better seat is available', 'Upgrade your seat for {route}.', 'مقعد أفضل متاح', 'أصبح مقعد أفضل متاحًا لرحلتك. [عرض المقاعد]', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),
            self::t('SEAT_UPGRADE_COMPLETED', 'Seat upgrade completed', NotificationTemplateCategories::FLIGHTS, 'Seat upgrade succeeded via provider API.', 'Seat upgrade confirmed', 'Your seat is now {to_value}.', 'تمت ترقية المقعد', 'مقعدك الآن {to_value}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'to_value', 'deep_link']),
            self::t('SEAT_UPGRADE_FAILED', 'Seat upgrade failed', NotificationTemplateCategories::FLIGHTS, 'Seat upgrade via provider API failed.', 'Seat upgrade could not be completed', 'We could not upgrade your seat. {reason}', 'تعذرت ترقية المقعد', 'تعذر ترقية المقعد. {reason}', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'reason', 'deep_link']),

            self::t('HOTEL_CHECKOUT_REMINDER', 'Hotel check-out reminder', NotificationTemplateCategories::HOTELS, 'Check-out is tomorrow.', 'Check-out is tomorrow', 'Check-out for hotel booking #{order_reference} is tomorrow.', 'تذكير المغادرة من الفندق', 'غداً موعد المغادرة لحجز #{order_reference}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),
            self::t('HOTEL_BOOKING_FAILED', 'Hotel booking failed', NotificationTemplateCategories::HOTELS, 'Hotel booking could not be confirmed.', 'Hotel booking failed', 'We could not confirm hotel booking #{order_reference}. {reason}', 'تعذر تأكيد حجز الفندق', 'تعذر تأكيد حجز الفندق #{order_reference}. {reason}', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'order_reference', 'reason', 'deep_link']),
            self::t('HOTEL_ROOM_UPGRADE_AVAILABLE', 'Hotel room upgrade available', NotificationTemplateCategories::OFFERS, 'A room upgrade is available.', 'A better room is available', 'Upgrade your room for booking #{order_reference}.', 'ترقية الغرفة متاحة', 'غرفة أفضل متاحة لحجزك #{order_reference}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),

            self::t('ESIM_PURCHASED', 'eSIM purchased', NotificationTemplateCategories::ORDERS, 'eSIM order paid.', 'Your eSIM was purchased', 'eSIM order #{order_reference} was purchased successfully.', 'تم شراء eSIM', 'تم شراء eSIM #{order_reference} بنجاح.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),
            self::t('ESIM_READY', 'eSIM ready', NotificationTemplateCategories::ORDERS, 'eSIM is ready to install.', 'Your eSIM is ready', 'Install your eSIM before you fly to {destination}.', 'شريحة eSIM جاهزة', 'ثبّت eSIM قبل السفر إلى {destination}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),
            self::t('ESIM_ACTIVATION_REMINDER', 'eSIM activation reminder', NotificationTemplateCategories::REMINDERS, 'Remind the traveler to activate eSIM before landing.', 'Activate your eSIM for {destination}', 'Activate your eSIM so you stay online when you land in {destination}.', 'تذكير تفعيل eSIM', 'فعّل eSIM لتبقى متصلاً فور وصولك إلى {destination}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),
            self::t('ESIM_ACTIVATED', 'eSIM activated', NotificationTemplateCategories::ORDERS, 'eSIM was activated.', 'Your eSIM is active', 'Your eSIM for {destination} is now active.', 'تم تفعيل eSIM', 'تم تفعيل eSIM لوجهة {destination}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),
            self::t('ESIM_LOW_DATA', 'eSIM low data', NotificationTemplateCategories::ORDERS, 'eSIM data pack is running low.', 'You used most of your eSIM data', 'You have used 80% of your data in {destination}.', 'وشكت باقة الإنترنت على الانتهاء', 'استهلكت 80% من باقة الإنترنت في {destination}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'destination', 'deep_link']),
            self::t('ESIM_EXPIRED', 'eSIM expired', NotificationTemplateCategories::ORDERS, 'eSIM pack expired.', 'Your eSIM has expired', 'Your eSIM for {destination} has expired.', 'انتهت صلاحية eSIM', 'انتهت صلاحية eSIM لوجهة {destination}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'destination', 'deep_link']),

            self::t('WALLET_TOPUP_SUCCESS', 'Wallet top-up success', NotificationTemplateCategories::PAYMENTS, 'Wallet credited.', '{amount} {currency} added to your wallet', 'Your BookNow wallet was credited with {amount} {currency}.', 'تمت إضافة رصيد للمحفظة', 'تمت إضافة {amount} {currency} إلى محفظتك.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $amount),
            self::t('WALLET_TOPUP_FAILED', 'Wallet top-up failed', NotificationTemplateCategories::PAYMENTS, 'Wallet top-up failed.', 'Wallet top-up failed', 'We could not add credit to your wallet. {reason}', 'فشل شحن المحفظة', 'تعذر شحن محفظتك. {reason}', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'reason', 'deep_link']),
            self::t('WALLET_DEBIT', 'Wallet debit', NotificationTemplateCategories::PAYMENTS, 'Wallet charged for a booking.', '{amount} {currency} was charged', '{amount} {currency} was taken from your wallet for #{order_reference}.', 'تم الخصم من المحفظة', 'تم خصم {amount} {currency} من محفظتك للحجز #{order_reference}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'amount', 'currency', 'order_reference', 'deep_link']),
            self::t('WALLET_REFUND', 'Wallet refund', NotificationTemplateCategories::PAYMENTS, 'Refund credited to wallet.', '{amount} {currency} refunded to your wallet', '{amount} {currency} was returned to your wallet.', 'استرداد إلى المحفظة', 'تم إرجاع {amount} {currency} إلى محفظتك.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $amount),
            self::t('WALLET_LOW_BALANCE', 'Wallet low balance', NotificationTemplateCategories::PAYMENTS, 'Wallet balance is low.', 'Your wallet balance is low', 'Your BookNow wallet balance is {amount} {currency}.', 'رصيد المحفظة منخفض', 'رصيد محفظتك {amount} {currency}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $amount),

            self::t('POINTS_EARNED', 'Loyalty points earned', NotificationTemplateCategories::LOYALTY, 'Points earned after a trip.', 'You earned {points} points', 'You earned {points} points from your latest booking.', 'حصلت على نقاط', 'حصلت على {points} نقطة من حجزك الأخير.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'points', 'deep_link']),
            self::t('POINTS_REDEEMED', 'Loyalty points redeemed', NotificationTemplateCategories::LOYALTY, 'Points were redeemed.', 'You redeemed {points} points', '{points} points were redeemed from your BookNow account.', 'تم استخدام النقاط', 'تم استخدام {points} نقطة.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'points', 'deep_link']),
            self::t('REWARD_AVAILABLE', 'Loyalty reward available', NotificationTemplateCategories::LOYALTY, 'A reward can be claimed.', 'A reward is waiting', 'You can claim your next BookNow reward.', 'مكافأة متاحة', 'يمكنك المطالبة بمكافأتك التالية.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'deep_link']),
            self::t('POINTS_EXPIRING', 'Loyalty points expiring', NotificationTemplateCategories::LOYALTY, 'Points will expire soon.', 'Points are expiring', '{points} points will expire on {expiry_date}.', 'نقاط قاربت على الانتهاء', '{points} نقطة ستنتهي في {expiry_date}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'points', 'expiry_date', 'deep_link']),
            self::t('TIER_UPGRADED', 'Loyalty tier upgraded', NotificationTemplateCategories::LOYALTY, 'Alias-quality copy for a tier upgrade. LOYALTY_TIER_CHANGED still fires today.', 'Your tier is now {tier_name}', 'Welcome to {tier_name}.', 'تمت ترقية مستواك', 'أصبحت في مستوى {tier_name}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'tier_name', 'deep_link']),

            self::t('LINK_REQUEST_RECEIVED', 'Account link request received', NotificationTemplateCategories::PAYMENTS, 'Someone asked to link for payment.', 'A payment link request', '{sender_name} asked to link with your BookNow account.', 'طلب ربط حساب', '{sender_name} يطلب الربط مع حسابك.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'sender_name', 'deep_link']),
            self::t('LINK_REQUEST_ACCEPTED', 'Account link accepted', NotificationTemplateCategories::PAYMENTS, 'The other party accepted the link.', 'Link request accepted', '{recipient_name} accepted your account link.', 'تم قبول طلب الربط', '{recipient_name} قبل طلب الربط.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'recipient_name', 'deep_link']),
            self::t('LINK_REQUEST_REJECTED', 'Account link rejected', NotificationTemplateCategories::PAYMENTS, 'The other party declined the link.', 'Link request declined', '{recipient_name} declined your account link.', 'تم رفض طلب الربط', '{recipient_name} رفض طلب الربط.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'recipient_name', 'deep_link']),
            self::t('PAYMENT_REQUEST_CREATED', 'Payment request created', NotificationTemplateCategories::PAYMENTS, 'You created a payment request.', 'Payment request created', 'You asked {recipient_name} to pay {amount} {currency}.', 'تم إنشاء طلب دفع', 'طلبت من {recipient_name} دفع {amount} {currency}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'recipient_name', 'amount', 'currency', 'deep_link']),
            self::t('PAYMENT_REQUEST_RECEIVED', 'Payment request received', NotificationTemplateCategories::PAYMENTS, 'Someone asked you to pay.', '{sender_name} asked you to pay', '{sender_name} requested {amount} {currency}.', 'وصلك طلب دفع', '{sender_name} يطلب منك دفع {amount} {currency}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'sender_name', 'amount', 'currency', 'deep_link']),
            self::t('PAYMENT_REQUEST_COMPLETED', 'Payment request completed', NotificationTemplateCategories::PAYMENTS, 'Linked payment completed.', 'Payment request paid', '{amount} {currency} was paid successfully.', 'اكتمل طلب الدفع', 'تم دفع {amount} {currency} بنجاح.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $amount),
            self::t('PAYMENT_REQUEST_EXPIRED', 'Payment request expired', NotificationTemplateCategories::PAYMENTS, 'Linked payment request expired.', 'Payment request expired', 'The payment request for {amount} {currency} expired.', 'انتهت صلاحية طلب الدفع', 'انتهت صلاحية طلب دفع {amount} {currency}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $amount),

            self::t('NEW_DEVICE_LOGIN', 'New device login', NotificationTemplateCategories::SECURITY, 'Login from a new device.', 'New device signed in', 'A new device signed in to your BookNow account: {device_name}.', 'تسجيل دخول من جهاز جديد', 'تم تسجيل الدخول إلى حسابك من جهاز جديد: {device_name}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP, NotificationChannels::EMAIL], ['user_name', 'device_name', 'ip', 'deep_link']),
            self::t('PASSWORD_CHANGED', 'Password changed', NotificationTemplateCategories::SECURITY, 'Account password changed.', 'Your password was changed', 'If this was not you, reset your password immediately.', 'تم تغيير كلمة المرور', 'إذا لم تكن أنت، أعد تعيين كلمة المرور فوراً.', [NotificationChannels::PUSH, NotificationChannels::IN_APP, NotificationChannels::EMAIL], ['user_name', 'deep_link']),
            self::t('EMAIL_CHANGED', 'Email changed', NotificationTemplateCategories::SECURITY, 'Account email changed.', 'Your email was updated', 'The email on your BookNow account was changed.', 'تم تغيير البريد', 'تم تحديث البريد الإلكتروني لحسابك.', [NotificationChannels::PUSH, NotificationChannels::IN_APP, NotificationChannels::EMAIL], ['user_name', 'deep_link']),
            self::t('PHONE_CHANGED', 'Phone changed', NotificationTemplateCategories::SECURITY, 'Account phone changed.', 'Your phone number was updated', 'The phone number on your BookNow account was changed.', 'تم تغيير رقم الهاتف', 'تم تحديث رقم هاتف حسابك.', [NotificationChannels::PUSH, NotificationChannels::IN_APP, NotificationChannels::EMAIL], ['user_name', 'deep_link']),
            self::t('ACCOUNT_SECURITY_ALERT', 'Account security alert', NotificationTemplateCategories::SECURITY, 'Generic security alert.', 'Security alert on your account', '{reason}', 'تنبيه أمني على حسابك', '{reason}', [NotificationChannels::PUSH, NotificationChannels::IN_APP, NotificationChannels::EMAIL], ['user_name', 'reason', 'deep_link']),

            self::t('PAYMENT_PENDING', 'Payment pending', NotificationTemplateCategories::PAYMENTS, 'Payment is waiting.', 'Payment pending for {order_reference}', 'Complete payment to confirm booking #{order_reference}.', 'الدفع قيد الانتظار', 'أكمل الدفع لتأكيد الحجز #{order_reference}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),
            self::t('PAYMENT_REQUIRES_ACTION', 'Payment requires action', NotificationTemplateCategories::PAYMENTS, '3-D Secure or similar action required.', 'Action needed to complete payment', 'Open the app to complete payment for #{order_reference}.', 'مطلوب إجراء لإكمال الدفع', 'افتح التطبيق لإكمال دفع الحجز #{order_reference}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),
            self::t('REFUND_REQUESTED', 'Refund requested', NotificationTemplateCategories::PAYMENTS, 'Customer requested a refund.', 'Refund requested', 'We received your refund request for #{order_reference}.', 'تم تقديم طلب الاسترداد', 'استلمنا طلب استرداد الحجز #{order_reference}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),
            self::t('REFUND_APPROVED', 'Refund approved', NotificationTemplateCategories::PAYMENTS, 'Refund approved, processing next.', 'Refund approved', 'Your refund for #{order_reference} was approved.', 'تمت الموافقة على الاسترداد', 'تمت الموافقة على استرداد الحجز #{order_reference}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], $order),
            self::t('REFUND_REJECTED', 'Refund rejected', NotificationTemplateCategories::PAYMENTS, 'Refund request rejected.', 'Refund request declined', 'Your refund request for #{order_reference} was declined. {reason}', 'رُفض طلب الاسترداد', 'رُفض طلب استرداد الحجز #{order_reference}. {reason}', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'order_reference', 'reason', 'deep_link']),
            self::t('REFUND_PROCESSING', 'Refund processing', NotificationTemplateCategories::PAYMENTS, 'Refund is being processed.', 'Refund is processing', 'We are processing {amount} {currency} for #{order_reference}.', 'الاسترداد قيد المعالجة', 'نعالج استرداد {amount} {currency} للحجز #{order_reference}.', [NotificationChannels::PUSH, NotificationChannels::IN_APP], ['user_name', 'amount', 'currency', 'order_reference', 'deep_link']),
        ];
    }

    /**
     * @param  list<string>  $channels
     * @param  list<string>  $variables
     * @return array<string, mixed>
     */
    private static function t(
        string $code,
        string $name,
        string $category,
        string $description,
        string $subject,
        string $body,
        string $arSubject,
        string $arBody,
        array $channels,
        array $variables,
    ): array {
        return [
            'code' => $code,
            'name' => $name,
            'category' => $category,
            'description' => $description,
            'subject' => $subject,
            'body' => $body,
            'ar_subject' => $arSubject,
            'ar_body' => $arBody,
            'channels' => $channels,
            'variables' => $variables,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $code): ?array
    {
        foreach (self::templates() as $template) {
            if ($template['code'] === $code) {
                return $template;
            }
        }

        return null;
    }
}
