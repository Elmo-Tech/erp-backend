<?php

namespace App\Http\Requests\V1\HR;

use App\Helpers\ApiResponse;
use App\Policies\UserAdministrationPolicy;
use App\Services\HR\EmployeeService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if ($actor === null) {
            return false;
        }

        $employee = app(EmployeeService::class)->getEmployee((int) $this->route('id'));

        if ($employee === null || $actor->is($employee)) {
            return true;
        }

        app(UserAdministrationPolicy::class)->authorizeEmployeeMutation($actor, $employee);

        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'documentType' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv', 'max:10240'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(__('validation.failed'), 422, $validator->errors()->toArray())
        );
    }
}
