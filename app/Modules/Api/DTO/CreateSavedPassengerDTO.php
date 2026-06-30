<?php

namespace App\Modules\Api\DTO;

final readonly class CreateSavedPassengerDTO
{
    public function __construct(
        public string $type,
        public ?string $title,
        public string $firstName,
        public string $lastName,
        public string $dateOfBirth,
        public string $gender,
        public string $nationality,
        public ?string $countryOfResidence,
        public string $documentType,
        public string $passportNumber,
        public ?string $passportIssueCountry,
        public ?string $passportIssueDate,
        public string $passportExpiry,
        public ?string $email,
        public ?string $phone,
        public ?string $seatPreference,
        public ?string $mealPreference,
        public bool $isDefault,
    ) {
    }

    /**
     * Create a DTO from validated request data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'] ?? 'ADT',
            title: $data['title'] ?? null,
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            dateOfBirth: $data['date_of_birth'],
            gender: $data['gender'],
            nationality: strtoupper($data['nationality']),
            countryOfResidence: isset($data['country_of_residence'])
                ? strtoupper($data['country_of_residence'])
                : null,
            documentType: $data['document_type'] ?? 'passport',
            passportNumber: strtoupper($data['passport_number']),
            passportIssueCountry: isset($data['passport_issue_country'])
                ? strtoupper($data['passport_issue_country'])
                : null,
            passportIssueDate: $data['passport_issue_date'] ?? null,
            passportExpiry: $data['passport_expiry'],
            email: isset($data['email']) ? strtolower($data['email']) : null,
            phone: $data['phone'] ?? null,
            seatPreference: $data['seat_preference'] ?? null,
            mealPreference: $data['meal_preference'] ?? null,
            isDefault: (bool) ($data['is_default'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(int $userId): array
    {
        return [
            'user_id' => $userId,
            'type' => $this->type,
            'title' => $this->title,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'date_of_birth' => $this->dateOfBirth,
            'gender' => $this->gender,
            'nationality' => $this->nationality,
            'country_of_residence' => $this->countryOfResidence,
            'document_type' => $this->documentType,
            'passport_number' => $this->passportNumber,
            'passport_issue_country' => $this->passportIssueCountry,
            'passport_issue_date' => $this->passportIssueDate,
            'passport_expiry' => $this->passportExpiry,
            'email' => $this->email,
            'phone' => $this->phone,
            'seat_preference' => $this->seatPreference,
            'meal_preference' => $this->mealPreference,
            'is_default' => $this->isDefault,
        ];
    }
}
