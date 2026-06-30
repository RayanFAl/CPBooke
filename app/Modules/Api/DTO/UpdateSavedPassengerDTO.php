<?php

namespace App\Modules\Api\DTO;

final readonly class UpdateSavedPassengerDTO
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
        $dto = CreateSavedPassengerDTO::fromArray($data);

        return new self(
            type: $dto->type,
            title: $dto->title,
            firstName: $dto->firstName,
            lastName: $dto->lastName,
            dateOfBirth: $dto->dateOfBirth,
            gender: $dto->gender,
            nationality: $dto->nationality,
            countryOfResidence: $dto->countryOfResidence,
            documentType: $dto->documentType,
            passportNumber: $dto->passportNumber,
            passportIssueCountry: $dto->passportIssueCountry,
            passportIssueDate: $dto->passportIssueDate,
            passportExpiry: $dto->passportExpiry,
            email: $dto->email,
            phone: $dto->phone,
            seatPreference: $dto->seatPreference,
            mealPreference: $dto->mealPreference,
            isDefault: $dto->isDefault,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
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
