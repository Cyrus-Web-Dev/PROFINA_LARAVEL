<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:departments.view')->only(['index']);
        $this->middleware('permission:departments.manage')->except(['index']);
    }

    public function index()
    {
        return Inertia::render('Departments/Index', [
            'departments' => Department::withCount('employees')
                ->with('manager:id,full_name')
                ->orderBy('name')
                ->get(),
            'positions' => \App\Models\Position::with('department:id,name')->orderBy('title')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('departments', 'code')],
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'exists:departments,id'],
            'manager_employee_id' => ['nullable', 'exists:employees,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $department = Department::create($data);
        ActivityLogger::log('department.created', "Menambahkan departemen: {$department->name}", $department, auth()->id());

        return back()->with('success', 'Departemen berhasil ditambahkan.');
    }

    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($department->id)],
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'exists:departments,id', Rule::notIn([$department->id])],
            'manager_employee_id' => ['nullable', 'exists:employees,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $department->update($data);
        ActivityLogger::log('department.updated', "Memperbarui departemen: {$department->name}", $department, auth()->id());

        return back()->with('success', 'Departemen berhasil diperbarui.');
    }

    public function destroy(Department $department)
    {
        if ($department->employees()->exists()) {
            return back()->with('error', 'Tidak bisa menghapus departemen yang masih memiliki karyawan.');
        }

        $name = $department->name;
        $department->delete();
        ActivityLogger::log('department.deleted', "Menghapus departemen: {$name}", null, auth()->id());

        return back()->with('success', 'Departemen berhasil dihapus.');
    }

    public function storePosition(Request $request)
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'title' => ['required', 'string', 'max:100'],
            'level' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        \App\Models\Position::create($data);

        return back()->with('success', 'Jabatan berhasil ditambahkan.');
    }
}
