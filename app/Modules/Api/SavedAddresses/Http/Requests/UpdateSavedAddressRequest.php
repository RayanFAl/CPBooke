<?php

namespace App\Modules\Api\SavedAddresses\Http\Requests;

use App\Modules\Api\DTO\UpdateSavedAddressDTO;

class UpdateSavedAddressRequest extends SavedAddressFormRequest
{
    public function toDto(): UpdateSavedAddressDTO
    {
        return UpdateSavedAddressDTO::fromArray($this->validated());
    }
}
