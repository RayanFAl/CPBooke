<?php

namespace App\Support\Home;

use Illuminate\Validation\Validator;

class HomeOpenHotelPayload
{
    public static function validate(Validator $validator): void
    {
        $data = $validator->getData();
        $actionType = (string) ($data['action_type'] ?? '');

        if ($actionType !== 'open_hotel') {
            return;
        }

        $payload = self::decode($data['action_payload'] ?? null);
        $hotelId = self::first($payload, ['hotel_id', 'hotelId']);
        $cityId = self::first($payload, ['city_id', 'cityId']);

        if ($hotelId === '') {
            $validator->errors()->add(
                'action_payload',
                'Enter the hotel id so the offer opens that hotel.',
            );
        }

        if ($cityId === '') {
            $validator->errors()->add(
                'action_payload',
                'Enter the city id so the app can load the hotel and its offers.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private static function first(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($payload[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(mixed $payload): array
    {
        if (is_string($payload) && trim($payload) !== '') {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : null;
        }

        return is_array($payload) ? $payload : [];
    }
}
