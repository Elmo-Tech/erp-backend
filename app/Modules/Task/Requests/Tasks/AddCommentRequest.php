<?php

namespace App\Modules\Task\Requests\Tasks;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AddCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'max:5000'],
            'isInternal' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'comment.required' => __('validation.required', ['attribute' => 'comment']),
            'comment.max' => __('validation.max.string', ['attribute' => 'comment', 'max' => 5000]),
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                __('validation.failed'),
                422,
                $validator->errors()->toArray()
            )
        );
    }
}
