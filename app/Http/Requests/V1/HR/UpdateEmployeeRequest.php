<?php

namespace App\Http\Requests\V1\HR;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="UpdateEmployeeRequest",
 *
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="password", type="string", minLength=8),
 *     @OA\Property(property="role", type="string", example="developer"),
 *     @OA\Property(property="specialization", type="string", enum={"backend", "frontend", "fullstack", "ui_ux", "mobile", "devops", "qa"}, example="backend"),
 *     @OA\Property(property="isActive", type="boolean", example=true),
 *     @OA\Property(property="salary", type="number", format="float", example=5000.00),
 *     @OA\Property(property="workHoursPerDay", type="integer", example=8),
 *     @OA\Property(property="workStartTime", type="string", example="10:00"),
 *     @OA\Property(property="graceDaysAllowed", type="integer", example=5),
 *     @OA\Property(property="internalHourlyCost", type="number", format="float", example=25.00),
 *     @OA\Property(property="skills", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="avatar", type="string")
 * )
 */
class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage_employees');
    }

    public function rules(): array
    {
        $employeeId = $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($employeeId)],
            'password' => ['sometimes', 'string', 'min:8'],
            'role' => ['nullable', 'string', 'exists:roles,name'],
            'specialization' => ['nullable', Rule::exists('lookup_options', 'value')->where(fn ($query) => $query->where('type', 'specialization')->where('is_active', true))],
            'isActive' => ['sometimes', 'boolean'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'hourlyRate' => ['nullable', 'numeric', 'min:0'],
            'workHoursPerDay' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'workStartTime' => ['sometimes', 'date_format:H:i'],
            'graceDaysAllowed' => ['sometimes', 'integer', 'min:0', 'max:31'],
            'internalHourlyCost' => ['nullable', 'numeric', 'min:0'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string'],
            'avatar' => ['nullable', 'string'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(__('validation.failed'), 422, $validator->errors()->toArray())
        );
    }
}
