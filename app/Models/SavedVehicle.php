<?php

namespace App\Models;

use Database\Factories\SavedVehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'type',
    'label',
    'is_default',
    'beneficiary_name',
    'beneficiary_phone',
    'beneficiary_phone_hash',
    'email',
    'vehicle_type_id',
    'vehicle_color_id',
    'vehicle_licensing_authority_id',
    'vehicle_manufacture_year',
    'vehicle_chassis_number',
    'vehicle_chassis_number_hash',
    'vehicle_plate_number',
    'vehicle_plate_number_hash',
    'payload',
    'document_type_id',
    'vehicle_nationality',
    'address',
])]
#[Hidden(['beneficiary_phone_hash', 'vehicle_chassis_number_hash', 'vehicle_plate_number_hash'])]
class SavedVehicle extends Model
{
    /** @use HasFactory<SavedVehicleFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    public const TYPE_COMPULSORY = 'compulsory';

    public const TYPE_ORANGE = 'orange';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'beneficiary_phone' => 'encrypted',
            'email' => 'encrypted',
            'vehicle_type_id' => 'integer',
            'vehicle_color_id' => 'integer',
            'vehicle_licensing_authority_id' => 'integer',
            'vehicle_manufacture_year' => 'integer',
            'payload' => 'decimal:2',
            'document_type_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SavedVehicle $vehicle): void {
            if ($vehicle->isDirty('vehicle_chassis_number')) {
                $vehicle->vehicle_chassis_number = self::normalizeChassis(
                    (string) $vehicle->vehicle_chassis_number,
                );
                $vehicle->vehicle_chassis_number_hash = self::hashChassis(
                    (string) $vehicle->vehicle_chassis_number,
                );
            }

            if ($vehicle->isDirty('vehicle_plate_number')) {
                $vehicle->vehicle_plate_number = self::normalizePlate(
                    (string) $vehicle->vehicle_plate_number,
                );
                $vehicle->vehicle_plate_number_hash = self::hashPlate(
                    (string) $vehicle->vehicle_plate_number,
                );
            }

            if ($vehicle->isDirty('beneficiary_phone')) {
                $vehicle->beneficiary_phone_hash = self::hashPhone($vehicle->beneficiary_phone);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_COMPULSORY,
            self::TYPE_ORANGE,
        ];
    }

    public static function normalizeChassis(string $value): string
    {
        return strtoupper(trim($value));
    }

    public static function normalizePlate(string $value): string
    {
        return strtoupper(trim($value));
    }

    public static function hashChassis(string $value): string
    {
        return hash('sha256', self::normalizeChassis($value));
    }

    public static function hashPlate(string $value): string
    {
        return hash('sha256', self::normalizePlate($value));
    }

    public static function hashPhone(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return hash('sha256', trim($value));
    }
}
