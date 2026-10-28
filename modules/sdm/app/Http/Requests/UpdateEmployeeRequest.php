<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('employees.manage');
    }

    public function rules(): array
    {
        $employeeId = $this->route('employee')->id;

        return [
            'nip' => ['required', 'string', 'max:30', Rule::unique('employees', 'nip')->ignore($employeeId)],
            'full_name' => ['required', 'string', 'max:150'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'place_of_birth' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'personal_email' => ['nullable', 'email', 'max:150'],
            'marital_status' => ['required', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'dependents_count' => ['nullable', 'integer', 'min:0', 'max:20'],

            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'supervisor_id' => ['nullable', 'exists:employees,id', Rule::notIn([$employeeId])],

            'employment_type' => ['required', Rule::in(['tetap', 'kontrak', 'magang', 'harian'])],
            'join_date' => ['required', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:join_date'],
            'resign_date' => ['nullable', 'date'],
            'employment_status' => ['required', Rule::in(['active', 'resigned', 'terminated', 'retired'])],

            'base_salary' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_name' => ['nullable', 'string', 'max:150'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'bpjs_kesehatan_number' => ['nullable', 'string', 'max:30'],
            'bpjs_tk_number' => ['nullable', 'string', 'max:30'],
            'ptkp_status' => ['required', Rule::in(['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3'])],

            'photo' => ['nullable', 'image', 'max:2048'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nip' => 'NIP',
            'full_name' => 'nama lengkap',
            'department_id' => 'departemen',
            'position_id' => 'jabatan',
            'base_salary' => 'gaji pokok',
        ];
    }
}
