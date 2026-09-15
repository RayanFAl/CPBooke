<?php

namespace App\Models;

use Database\Factories\SavedPassengerFactory;
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
    'title',
    'first_name',
    'last_name',
    'date_of_birth',
    'gender',
    'nationality',
    'country_of_residence',
    'document_type',
    'passport_number',
    'passport_number_hash',
    'passport_issue_country',
    'passport_issue_date',
    'passport_expiry',
    'email',
    'phone',
    'phone_2',
    'phone_3',
    'phone_hash',
    'seat_preference',
    'meal_preference',
    'is_default',
    'passport_image_disk',
    'passport_image_path',
    'passport_image_mime',
    'passport_image_size',
    'passport_image_uploaded_at',
])]
#[Hidden(['passport_number_hash', 'phone_hash', 'passport_image_disk', 'passport_image_path', 'passport_image_mime', 'passport_image_size'])]
class SavedPassenger extends Model
{
    /** @use HasFactory<SavedPassengerFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    public const TYPE_ADT = 'ADT';

    public const TYPE_CHD = 'CHD';

    public const TYPE_INF = 'INF';

    public const GENDER_MALE = 'M';

    public const GENDER_FEMALE = 'F';

    public const DOCUMENT_PASSPORT = 'passport';

    public const DOCUMENT_NATIONAL_ID = 'national_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'passport_issue_date' => 'date',
            'passport_expiry' => 'date',
            'passport_number' => 'encrypted',
            'email' => 'encrypted',
            'phone' => 'encrypted',
            'phone_2' => 'encrypted',
            'phone_3' => 'encrypted',
            'is_default' => 'boolean',
            'passport_image_size' => 'integer',
            'passport_image_uploaded_at' => 'datetime',
        ];
    }

    /**
     * Primary + optional secondary phones for API clients.
     *
     * @return list<string>
     */
    public function phonesList(): array
    {
        return array_values(array_filter(
            [$this->phone, $this->phone_2, $this->phone_3],
            static fn (mixed $phone): bool => is_string($phone) && trim($phone) !== '',
        ));
    }

    /**
     * Sync searchable hash columns whenever sensitive values change.
     */
    protected static function booted(): void
    {
        static::saving(function (SavedPassenger $passenger): void {
            if ($passenger->isDirty('passport_number')) {
                $passenger->passport_number_hash = self::hashPassportNumber(
                    (string) $passenger->passport_number,
                );
            }

            if ($passenger->isDirty('phone')) {
                $passenger->phone_hash = self::hashPhone($passenger->phone);
            }
        });

        static::deleting(function (SavedPassenger $passenger): void {
            app(\App\Modules\Api\SavedPassengers\Storage\PassportImageStorage::class)
                ->deleteStoredFile($passenger);

            $passenger->passport_image_disk = null;
            $passenger->passport_image_path = null;
            $passenger->passport_image_mime = null;
            $passenger->passport_image_size = null;
            $passenger->passport_image_uploaded_at = null;
        });
    }

    public function hasPassportImage(): bool
    {
        return is_string($this->passport_image_path)
            && $this->passport_image_path !== ''
            && $this->passport_image_uploaded_at !== null;
    }

    /**
     * Use the ULID primary key for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'id';
    }

    /**
     * Build a deterministic hash for passport number lookups.
     */
    public static function hashPassportNumber(string $value): string
    {
        return hash('sha256', strtoupper(trim($value)));
    }

    /**
     * Build a deterministic hash for phone lookups.
     */
    public static function hashPhone(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return hash('sha256', trim($value));
    }

    /**
     * Get the user that owns the saved passenger.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<int, string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_ADT,
            self::TYPE_CHD,
            self::TYPE_INF,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function genders(): array
    {
        return [
            self::GENDER_MALE,
            self::GENDER_FEMALE,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function documentTypes(): array
    {
        return [
            self::DOCUMENT_PASSPORT,
            self::DOCUMENT_NATIONAL_ID,
        ];
    }
}
