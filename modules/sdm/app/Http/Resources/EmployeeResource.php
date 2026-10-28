<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Dicek langsung dari user yang sedang login lewat token API - tidak perlu koordinasi
        // tambahan dari controller, dan selalu konsisten dengan permission saat itu juga.
        $canSeeSalary = (bool) $request->user()?->can('employees.manage');

        return [
            'id' => $this->id,
            'nip' => $this->nip,
            'full_name' => $this->full_name,
            'gender' => $this->gender,
            'phone' => $this->phone,
            'personal_email' => $this->personal_email,
            'department' => $this->whenLoaded('department', fn () => $this->department ? ['id' => $this->department->id, 'name' => $this->department->name] : null),
            'position' => $this->whenLoaded('position', fn () => $this->position ? ['id' => $this->position->id, 'title' => $this->position->title] : null),
            'employment_type' => $this->employment_type,
            'employment_type_label' => $this->employment_type_label,
            'employment_status' => $this->employment_status,
            'employment_status_label' => $this->employment_status_label,
            'join_date' => $this->join_date?->toDateString(),
            'photo_url' => $this->photo_url,
            // Data gaji hanya disertakan bila pengguna API punya izin employees.manage.
            $this->mergeWhen($canSeeSalary, [
                'base_salary' => $this->base_salary,
                'ptkp_status' => $this->ptkp_status,
            ]),
        ];
    }
}
