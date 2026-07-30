<?php

namespace App\Modules\Api\SavedAddresses\Http\Requests;

use App\Modules\Api\DTO\CreateSavedAddressDTO;

class StoreSavedAddressRequest extends SavedAddressFormRequest
{
    public function toDto(): CreateSavedAddressDTO
    {
        return CreateSavedAddressDTO::fromArray($this->validated());
    }
}
