<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seed Booke+ mobile codes, bilingual names, and display benefits for loyalty tiers.
     */
    public function up(): void
    {
        $catalog = [
            'level_1' => [
                'mobile_code' => 'welcome',
                'name_en' => 'Welcome',
                'name_ar' => 'مرحباً',
                'display_name' => 'Welcome',
                'mobile_benefits' => [],
                'discount_labels' => [
                    'label_en' => '3% off bookings',
                    'label_ar' => 'خصم 3% على الحجوزات',
                ],
            ],
            'level_2' => [
                'mobile_code' => 'explorer',
                'name_en' => 'Explorer',
                'name_ar' => 'مستكشف',
                'display_name' => 'Explorer',
                'mobile_benefits' => [
                    ['label_en' => 'Member offers', 'label_ar' => 'عروض الأعضاء'],
                ],
                'discount_labels' => [
                    'label_en' => '5% off bookings',
                    'label_ar' => 'خصم 5% على الحجوزات',
                ],
            ],
            'level_3' => [
                'mobile_code' => 'gold',
                'name_en' => 'Gold',
                'name_ar' => 'ذهبي',
                'display_name' => 'Gold',
                'mobile_benefits' => [
                    ['label_en' => 'Exclusive offers', 'label_ar' => 'عروض حصرية'],
                ],
                'discount_labels' => [
                    'label_en' => '7% off bookings',
                    'label_ar' => 'خصم 7% على الحجوزات',
                ],
            ],
            'vip' => [
                'mobile_code' => 'platinum',
                'name_en' => 'Platinum',
                'name_ar' => 'بلاتيني',
                'display_name' => 'Platinum',
                'mobile_benefits' => [
                    ['label_en' => 'Exclusive Booke perks', 'label_ar' => 'مزايا Booke الحصرية'],
                ],
                'discount_labels' => [
                    'label_en' => '10% off bookings',
                    'label_ar' => 'خصم 10% على الحجوزات',
                ],
            ],
        ];

        foreach ($catalog as $code => $config) {
            $tier = DB::table('loyalty_tiers')->where('code', $code)->first();

            if ($tier === null) {
                continue;
            }

            $metadata = json_decode((string) ($tier->metadata ?: '{}'), true);
            $metadata = is_array($metadata) ? $metadata : [];
            $metadata['mobile_code'] = $config['mobile_code'];
            $metadata['name_en'] = $config['name_en'];
            $metadata['name_ar'] = $config['name_ar'];
            $metadata['mobile_benefits'] = $config['mobile_benefits'];

            DB::table('loyalty_tiers')->where('id', $tier->id)->update([
                'name' => $config['display_name'],
                'badge_label' => $config['display_name'],
                'metadata' => json_encode($metadata),
                'updated_at' => now(),
            ]);

            $discountBenefit = DB::table('loyalty_benefits')
                ->where('tier_id', $tier->id)
                ->where('benefit_type', 'discount')
                ->where('is_active', true)
                ->orderBy('display_order')
                ->orderBy('id')
                ->first();

            if ($discountBenefit === null) {
                continue;
            }

            $benefitMeta = json_decode((string) ($discountBenefit->metadata ?: '{}'), true);
            $benefitMeta = is_array($benefitMeta) ? $benefitMeta : [];
            $benefitMeta['label_en'] = $config['discount_labels']['label_en'];
            $benefitMeta['label_ar'] = $config['discount_labels']['label_ar'];

            DB::table('loyalty_benefits')->where('id', $discountBenefit->id)->update([
                'metadata' => json_encode($benefitMeta),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Non-destructive: keep localized labels.
    }
};
