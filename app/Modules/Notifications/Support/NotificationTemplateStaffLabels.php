<?php

namespace App\Modules\Notifications\Support;

/**
 * Employee-facing titles for the admin template list (not customer push copy).
 */
final class NotificationTemplateStaffLabels
{
    /**
     * @return array{en: string, ar: string}
     */
    public static function for(string $code): array
    {
        return self::all()[$code] ?? [
            'en' => self::fallback($code),
            'ar' => self::fallback($code),
        ];
    }

    public static function english(string $code): string
    {
        return self::for($code)['en'];
    }

    public static function arabic(string $code): string
    {
        return self::for($code)['ar'];
    }

    /**
     * @return array<string, array{en: string, ar: string}>
     */
    public static function all(): array
    {
        return [
            'ORDER_CREATED' => ['en' => 'Booking created — waiting for payment', 'ar' => 'تم إنشاء الحجز — بانتظار الدفع'],
            'ORDER_CONFIRMED' => ['en' => 'Booking confirmed', 'ar' => 'تم تأكيد الحجز'],
            'FLIGHT_TICKET_ISSUED' => ['en' => 'Flight ticket issued', 'ar' => 'تم إصدار تذكرة الطيران'],
            'HOTEL_BOOKING_CONFIRMED' => ['en' => 'Hotel booking confirmed', 'ar' => 'تم تأكيد حجز الفندق'],
            'INSURANCE_POLICY_ISSUED' => ['en' => 'Insurance policy issued', 'ar' => 'تم إصدار وثيقة التأمين'],
            'ESIM_ORDER_CONFIRMED' => ['en' => 'eSIM order confirmed', 'ar' => 'تم تأكيد طلب الشريحة الإلكترونية'],
            'PAYMENT_SUCCEEDED' => ['en' => 'Payment successful', 'ar' => 'تم الدفع بنجاح'],
            'PAYMENT_FAILED' => ['en' => 'Payment failed', 'ar' => 'فشل الدفع'],
            'PAYMENT_REMINDER' => ['en' => 'Reminder: complete payment', 'ar' => 'تذكير: أكمل الدفع'],
            'PAYMENT_EXPIRED' => ['en' => 'Payment window expired', 'ar' => 'انتهت مهلة الدفع'],
            'PAYMENT_PENDING' => ['en' => 'Payment is pending', 'ar' => 'الدفع قيد الانتظار'],
            'PAYMENT_REQUIRES_ACTION' => ['en' => 'Payment needs customer action', 'ar' => 'الدفع يحتاج إجراء من العميل'],
            'REFUND_ISSUED' => ['en' => 'Refund paid to customer', 'ar' => 'تم إرجاع المبلغ للعميل'],
            'REFUND_INITIATED' => ['en' => 'Refund started', 'ar' => 'بدأ طلب الاسترداد'],
            'REFUND_COMPLETED' => ['en' => 'Refund completed', 'ar' => 'اكتمل الاسترداد'],
            'REFUND_FAILED' => ['en' => 'Refund failed', 'ar' => 'فشل الاسترداد'],
            'REFUND_REQUESTED' => ['en' => 'Customer requested a refund', 'ar' => 'العميل طلب استرداد المبلغ'],
            'REFUND_APPROVED' => ['en' => 'Refund approved', 'ar' => 'تمت الموافقة على الاسترداد'],
            'REFUND_REJECTED' => ['en' => 'Refund rejected', 'ar' => 'رُفض طلب الاسترداد'],
            'REFUND_PROCESSING' => ['en' => 'Refund is processing', 'ar' => 'الاسترداد قيد المعالجة'],
            'FLIGHT_STATUS_UPDATED' => ['en' => 'Flight status updated', 'ar' => 'تحديث حالة الرحلة'],
            'FLIGHT_TIME_CHANGED' => ['en' => 'Departure time changed', 'ar' => 'تغيّر موعد الإقلاع'],
            'FLIGHT_ARRIVAL_CHANGED' => ['en' => 'Arrival time changed', 'ar' => 'تغيّر موعد الوصول'],
            'FLIGHT_CHANGED' => ['en' => 'Flight details changed', 'ar' => 'تغيّرت تفاصيل الرحلة'],
            'FLIGHT_GATE_CHANGED' => ['en' => 'Departure gate changed', 'ar' => 'تغيّرت بوابة الصعود'],
            'FLIGHT_TERMINAL_CHANGED' => ['en' => 'Terminal changed', 'ar' => 'تغيّر صالة المطار'],
            'FLIGHT_SEAT_CHANGED' => ['en' => 'Seat changed (legacy)', 'ar' => 'تغيّر المقعد (قديم)'],
            'FLIGHT_CLASS_CHANGED' => ['en' => 'Travel class changed', 'ar' => 'تغيّرت درجة السفر'],
            'FLIGHT_DELAYED' => ['en' => 'Flight delayed', 'ar' => 'تأخّرت الرحلة'],
            'FLIGHT_CANCELLED' => ['en' => 'Airline cancelled the flight', 'ar' => 'شركة الطيران ألغت الرحلة'],
            'BOOKING_CANCELLED' => ['en' => 'Customer cancelled the booking', 'ar' => 'العميل ألغى الحجز'],
            'BOOKING_FAILED' => ['en' => 'Booking failed', 'ar' => 'فشل الحجز'],
            'HOTEL_BOOKING_CANCELLED' => ['en' => 'Hotel booking cancelled', 'ar' => 'أُلغي حجز الفندق'],
            'HOTEL_BOOKING_MODIFIED' => ['en' => 'Hotel booking modified', 'ar' => 'تعديل حجز الفندق'],
            'HOTEL_CHECKIN_CHANGED' => ['en' => 'Hotel check-in date changed', 'ar' => 'تغيّر تاريخ دخول الفندق'],
            'HOTEL_CHECKOUT_CHANGED' => ['en' => 'Hotel check-out date changed', 'ar' => 'تغيّر تاريخ مغادرة الفندق'],
            'HOTEL_CANCELLATION_DEADLINE_REMINDER' => ['en' => 'Last day for free hotel cancellation', 'ar' => 'آخر يوم للإلغاء المجاني للفندق'],
            'HOTEL_CHECKIN_REMINDER_24H' => ['en' => 'Hotel check-in tomorrow', 'ar' => 'تذكير: دخول الفندق غداً'],
            'HOTEL_CHECKOUT_REMINDER' => ['en' => 'Hotel check-out tomorrow', 'ar' => 'تذكير: مغادرة الفندق غداً'],
            'HOTEL_BOOKING_FAILED' => ['en' => 'Hotel booking failed', 'ar' => 'فشل حجز الفندق'],
            'HOTEL_ROOM_UPGRADE_AVAILABLE' => ['en' => 'Better hotel room available', 'ar' => 'غرفة فندق أفضل متاحة'],
            'FLIGHT_REMINDER_24H' => ['en' => 'Flight reminder — 24 hours', 'ar' => 'تذكير بالرحلة — قبل 24 ساعة'],
            'FLIGHT_REMINDER_3H' => ['en' => 'Flight reminder — 3 hours', 'ar' => 'تذكير بالرحلة — قبل 3 ساعات'],
            'FLIGHT_REMINDER_1H' => ['en' => 'Flight reminder — 1 hour', 'ar' => 'تذكير بالرحلة — قبل ساعة'],
            'DESTINATION_ARRIVAL' => ['en' => 'Arrived at destination', 'ar' => 'وصل إلى الوجهة'],
            'POST_TRIP_THANKS' => ['en' => 'Thanks after the trip', 'ar' => 'رسالة شكر بعد الرحلة'],
            'POST_TRIP_NEXT' => ['en' => 'Offer the next trip', 'ar' => 'عرض الرحلة التالية'],
            'OFFER_ESIM' => ['en' => 'Offer: buy eSIM', 'ar' => 'عرض: شراء شريحة إنترنت'],
            'OFFER_INSURANCE' => ['en' => 'Offer: buy insurance', 'ar' => 'عرض: شراء تأمين'],
            'OFFER_ESIM_FOR_TRIP' => ['en' => 'Offer: eSIM for this trip', 'ar' => 'عرض: شريحة إنترنت لهذه الرحلة'],
            'OFFER_INSURANCE_FOR_TRIP' => ['en' => 'Offer: insurance for this trip', 'ar' => 'عرض: تأمين لهذه الرحلة'],
            'OFFER_HOTELS_AT_DESTINATION' => ['en' => 'Offer: hotel at destination', 'ar' => 'عرض: فندق في الوجهة'],
            'OFFER_CARS_AT_DESTINATION' => ['en' => 'Offer: car at destination', 'ar' => 'عرض: سيارة في الوجهة'],
            'OFFER_RETURN_FLIGHT' => ['en' => 'Offer: return flight', 'ar' => 'عرض: رحلة العودة'],
            'LOYALTY_NEAR_REWARD' => ['en' => 'Close to the next loyalty reward', 'ar' => 'قريب من مكافأة الولاء التالية'],
            'LOYALTY_TIER_CHANGED' => ['en' => 'Loyalty tier changed', 'ar' => 'تغيّر مستوى الولاء'],
            'ABANDONED_FLIGHT_SEARCH' => ['en' => 'Customer searched and did not book', 'ar' => 'العميل بحث ولم يكمل الحجز'],
            'PRICE_ALERT_HIT' => ['en' => 'Watched flight price dropped', 'ar' => 'انخفض سعر الرحلة التي يراقبها'],
            'LOGIN_ALERT' => ['en' => 'New login to the account', 'ar' => 'تسجيل دخول جديد للحساب'],
            'SUPPORT_TICKET_CREATED_CUSTOMER' => ['en' => 'Support ticket opened', 'ar' => 'فُتحت تذكرة دعم'],
            'SUPPORT_TICKET_REPLIED_CUSTOMER' => ['en' => 'Support replied to the customer', 'ar' => 'الدعم رد على العميل'],
            'PASSPORT_EXPIRY_REMINDER' => ['en' => 'Passport will expire soon', 'ar' => 'جواز السفر قرب ينتهي'],
            'DOCUMENT_REQUIRED' => ['en' => 'A document is required to complete booking', 'ar' => 'مطلوب مستند لإكمال الحجز'],
            'DOCUMENT_VERIFICATION_REQUIRED' => ['en' => 'Document needs verification', 'ar' => 'مطلوب التحقق من المستند'],
            'VISA_REMINDER' => ['en' => 'Visa may be required', 'ar' => 'تذكير بالتأشيرة'],
            'VISA_DOCUMENT_MISSING' => ['en' => 'Visa document is missing', 'ar' => 'مستند التأشيرة ناقص'],
            'ONLINE_CHECKIN_OPEN' => ['en' => 'Online check-in is open', 'ar' => 'فتح تسجيل الوصول الإلكتروني'],
            'CHECKIN_REMINDER_24H' => ['en' => 'Check-in reminder — 24 hours', 'ar' => 'تذكير Check-in — قبل 24 ساعة'],
            'CHECKIN_REMINDER_3H' => ['en' => 'Check-in reminder — 3 hours', 'ar' => 'تذكير Check-in — قبل 3 ساعات'],
            'BOARDING_PASS_AVAILABLE' => ['en' => 'Boarding pass is ready', 'ar' => 'بطاقة الصعود جاهزة'],
            'GATE_ASSIGNED' => ['en' => 'Departure gate assigned', 'ar' => 'تم تحديد بوابة الصعود'],
            'GATE_CHANGED' => ['en' => 'Departure gate changed', 'ar' => 'تغيّرت بوابة الصعود'],
            'BOARDING_STARTED' => ['en' => 'Boarding started', 'ar' => 'بدأ الصعود للطائرة'],
            'BOARDING_FINAL_CALL' => ['en' => 'Final boarding call', 'ar' => 'النداء الأخير للصعود'],
            'BOARDING_CLOSED' => ['en' => 'Boarding closed', 'ar' => 'أُغلق باب الصعود'],
            'BAGGAGE_ALLOWANCE_UPDATED' => ['en' => 'Baggage allowance updated', 'ar' => 'تم تحديث وزن الأمتعة المسموح'],
            'BAGGAGE_ADDED' => ['en' => 'Extra baggage added', 'ar' => 'تمت إضافة أمتعة إضافية'],
            'BAGGAGE_PRICE_CHANGED' => ['en' => 'Extra baggage price changed', 'ar' => 'تغيّر سعر الأمتعة الإضافية'],
            'BAGGAGE_REMINDER' => ['en' => 'Reminder to add extra baggage', 'ar' => 'تذكير بإضافة حقيبة إضافية'],
            'SEAT_ASSIGNED' => ['en' => 'Seat assigned', 'ar' => 'تم تعيين المقعد'],
            'SEAT_CHANGED' => ['en' => 'Seat changed', 'ar' => 'تغيّر المقعد'],
            'SEAT_UPGRADE_AVAILABLE' => ['en' => 'Better seat available', 'ar' => 'مقعد أفضل أصبح متاحاً'],
            'SEAT_UPGRADE_COMPLETED' => ['en' => 'Seat upgrade completed', 'ar' => 'تمت ترقية المقعد'],
            'SEAT_UPGRADE_FAILED' => ['en' => 'Seat upgrade failed', 'ar' => 'تعذرت ترقية المقعد'],
            'ESIM_PURCHASED' => ['en' => 'eSIM purchased', 'ar' => 'تم شراء الشريحة الإلكترونية'],
            'ESIM_READY' => ['en' => 'eSIM is ready to install', 'ar' => 'الشريحة الإلكترونية جاهزة للتثبيت'],
            'ESIM_ACTIVATION_REMINDER' => ['en' => 'Remind to activate eSIM', 'ar' => 'تذكير بتفعيل الشريحة الإلكترونية'],
            'ESIM_ACTIVATED' => ['en' => 'eSIM activated', 'ar' => 'تم تفعيل الشريحة الإلكترونية'],
            'ESIM_LOW_DATA' => ['en' => 'eSIM data is running low', 'ar' => 'باقة الإنترنت أوشكت على الانتهاء'],
            'ESIM_EXPIRED' => ['en' => 'eSIM expired', 'ar' => 'انتهت صلاحية الشريحة الإلكترونية'],
            'WALLET_TOPUP_SUCCESS' => ['en' => 'Wallet topped up', 'ar' => 'تم شحن المحفظة'],
            'WALLET_TOPUP_FAILED' => ['en' => 'Wallet top-up failed', 'ar' => 'فشل شحن المحفظة'],
            'WALLET_DEBIT' => ['en' => 'Amount taken from wallet', 'ar' => 'تم الخصم من المحفظة'],
            'WALLET_REFUND' => ['en' => 'Refund credited to wallet', 'ar' => 'استرداد إلى المحفظة'],
            'WALLET_LOW_BALANCE' => ['en' => 'Wallet balance is low', 'ar' => 'رصيد المحفظة منخفض'],
            'POINTS_EARNED' => ['en' => 'Loyalty points earned', 'ar' => 'حصل على نقاط ولاء'],
            'POINTS_REDEEMED' => ['en' => 'Loyalty points used', 'ar' => 'تم استخدام نقاط الولاء'],
            'REWARD_AVAILABLE' => ['en' => 'Loyalty reward is available', 'ar' => 'مكافأة ولاء متاحة'],
            'POINTS_EXPIRING' => ['en' => 'Loyalty points expiring soon', 'ar' => 'نقاط الولاء قاربت على الانتهاء'],
            'TIER_UPGRADED' => ['en' => 'Loyalty tier upgraded', 'ar' => 'تمت ترقية مستوى الولاء'],
            'LINK_REQUEST_RECEIVED' => ['en' => 'Account-link request received', 'ar' => 'وصل طلب ربط حساب'],
            'LINK_REQUEST_ACCEPTED' => ['en' => 'Account-link request accepted', 'ar' => 'تم قبول طلب ربط الحساب'],
            'PAYMENT_REQUEST_CREATED' => ['en' => 'Payment request sent', 'ar' => 'تم إرسال طلب دفع'],
            'PAYMENT_REQUEST_RECEIVED' => ['en' => 'Payment request received', 'ar' => 'وصل طلب دفع'],
            'PAYMENT_REQUEST_COMPLETED' => ['en' => 'Linked payment completed', 'ar' => 'اكتمل الدفع من الحساب المرتبط'],
            'PAYMENT_REQUEST_EXPIRED' => ['en' => 'Payment request expired', 'ar' => 'انتهت صلاحية طلب الدفع'],
            'NEW_DEVICE_LOGIN' => ['en' => 'Login from a new device', 'ar' => 'تسجيل دخول من جهاز جديد'],
            'PASSWORD_CHANGED' => ['en' => 'Password changed', 'ar' => 'تم تغيير كلمة المرور'],
            'EMAIL_CHANGED' => ['en' => 'Email address changed', 'ar' => 'تم تغيير البريد الإلكتروني'],
            'PHONE_CHANGED' => ['en' => 'Phone number changed', 'ar' => 'تم تغيير رقم الهاتف'],
            'ACCOUNT_SECURITY_ALERT' => ['en' => 'Account security alert', 'ar' => 'تنبيه أمني على الحساب'],
        ];
    }

    private static function fallback(string $code): string
    {
        return str_replace('_', ' ', strtolower($code));
    }
}
