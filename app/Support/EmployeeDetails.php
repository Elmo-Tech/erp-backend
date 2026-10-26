<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class EmployeeDetails
{
    public const FIELDS = [
        'jobTitle' => 'job_title', 'workArrangement' => 'work_arrangement',
        'birthDate' => 'birth_date', 'appointmentDate' => 'appointment_date',
        'residence' => 'residence', 'nationalId' => 'national_id',
        'mobile' => 'mobile', 'otherPhone' => 'other_phone', 'education' => 'education',
    ];

    public static function rules(): array
    {
        return [
            'jobTitle' => ['nullable', 'string', 'max:255'],
            'workArrangement' => ['nullable', Rule::in(['on_site', 'freelance', 'remote'])],
            'birthDate' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'appointmentDate' => ['nullable', 'date_format:Y-m-d'],
            'residence' => ['nullable', 'string', 'max:500'],
            'nationalId' => ['nullable', 'string', 'regex:/^[0-9]{14}$/'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'otherPhone' => ['nullable', 'string', 'max:50'],
            'education' => ['nullable', 'string', 'max:500'],
        ];
    }

    public static function attributes(array $data): array
    {
        $result = [];
        foreach (self::FIELDS as $key => $column) {
            if (array_key_exists($key, $data)) {
                $result[$column] = $data[$key];
            }
        }

        return $result;
    }
}
