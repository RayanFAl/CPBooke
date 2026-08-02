<?php

namespace App\Modules\Admin\Users\Http\Requests;

use App\Modules\Admin\Access\Services\AccessControlService;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('users.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $assignableRoles = app(AccessControlService::class)
            ->availableRolesFor($this->user())
            ->pluck('name')
            ->all();

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in($assignableRoles)],
            'permissions' => [
                Rule::requiredIf(fn (): bool => $this->input('role') !== RbacRegistry::ROLE_SUPER_ADMIN),
                'nullable',
                'array',
                Rule::when(
                    $this->input('role') !== RbacRegistry::ROLE_SUPER_ADMIN,
                    ['min:1']
                ),
            ],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }
}
