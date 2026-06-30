<?php

namespace App\Modules\Admin\Airports\Http\Requests;

use App\Models\FeaturedAirport;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFeaturedAirportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'airports' => ['required', 'array', 'max:'.FeaturedAirport::MAX_COUNT],
            'airports.*' => ['required', 'string', 'max:32'],
        ];
    }
}
