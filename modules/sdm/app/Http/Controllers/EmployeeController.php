<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\NotificationMailer;
use App\Support\ImageProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:employees.view')->only(['index', 'show', 'photo']);
        $this->middleware('permission:employees.manage')->only(['create', 'store', 'edit', 'update', 'destroy']);
    }

    public function index(Request $request)
    {
        $query = Employee::query()->with(['department', 'position']);

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'ilike', "%{$search}%")
                    ->orWhere('nip', 'ilike', "%{$search}%");
            });
        }

        if ($departmentId = $request->input('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($status = $request->input('employment_status')) {
            $query->where('employment_status', $status);
        }

        $employees = $query->orderBy('full_name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Employee $e) => [
                'id' => $e->id,
                'nip' => $e->nip,
                'full_name' => $e->full_name,
                'photo_url' => $e->photo_url,
                'department' => $e->department?->name,
                'position' => $e->position?->title,
                'employment_type' => $e->employment_type_label,
                'employment_status' => $e->employment_status,
                'employment_status_label' => $e->employment_status_label,
                'join_date' => $e->join_date?->format('d/m/Y'),
            ]);

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'department_id', 'employment_status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Employees/Form', [
            'employee' => null,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'positions' => Position::orderBy('title')->get(['id', 'title', 'department_id']),
            'supervisors' => Employee::where('employment_status', 'active')->orderBy('full_name')->get(['id', 'full_name']),
        ]);
    }

    public function store(StoreEmployeeRequest $request)
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $data) {
            $createAccount = $data['create_account'] ?? false;
            unset($data['create_account'], $data['account_email'], $data['photo']);

            $user = null;
            $tempPassword = null;

            if ($createAccount) {
                $tempPassword = Str::password(12);
                $user = User::create([
                    'name' => $request->input('full_name'),
                    'email' => $request->input('account_email'),
                    'password' => $tempPassword,
                    'status' => 'active',
                    'must_change_password' => true,
                    'email_verified_at' => now(),
                ]);
                $user->assignRole('karyawan');
                $data['user_id'] = $user->id;
            }

            if ($request->hasFile('photo')) {
                $binary = ImageProcessor::squareJpeg($request->file('photo')->getRealPath())
                    ?? file_get_contents($request->file('photo')->getRealPath());
                $path = 'employee-photos/'.Str::random(20).'.jpg';
                Storage::disk('local')->put($path, $binary);
                $data['photo_path'] = $path;
            }

            $employee = Employee::create($data);

            ActivityLogger::log('employee.created', "Menambahkan karyawan baru: {$employee->full_name} ({$employee->nip})", $employee, auth()->id());

            if ($user && $tempPassword) {
                app(NotificationMailer::class)->accountCreated($user->email, $user->name, $tempPassword);
            }
        });

        return redirect()->route('employees.index')->with('success', 'Data karyawan berhasil ditambahkan.');
    }

    public function show(Employee $employee)
    {
        $user = auth()->user();
        $relations = ['department', 'position', 'supervisor'];
        if ($user->can('documents.view')) {
            $relations[] = 'documents';
        }
        $employee->load($relations);

        // KEAMANAN: data finansial & catatan internal HANYA untuk HR (employees.manage). Tanpa ini, data tsb
        // tetap ikut terkirim di JSON halaman (walau disembunyikan di tampilan) dan bisa dibaca lewat DevTools
        // oleh role lain yang punya izin employees.view (mis. manajer).
        if (! $user->can('employees.manage')) {
            $employee->makeHidden([
                'base_salary', 'bank_name', 'bank_account_number', 'bank_account_name', 'npwp',
                'bpjs_kesehatan_number', 'bpjs_tk_number', 'ptkp_status', 'notes', 'user_id',
            ]);
        }

        return Inertia::render('Employees/Show', [
            'employee' => $employee,
        ]);
    }

    public function edit(Employee $employee)
    {
        return Inertia::render('Employees/Form', [
            'employee' => $employee,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'positions' => Position::orderBy('title')->get(['id', 'title', 'department_id']),
            'supervisors' => Employee::where('employment_status', 'active')->where('id', '!=', $employee->id)->orderBy('full_name')->get(['id', 'full_name']),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $data = $request->validated();
        unset($data['photo']);

        if ($request->hasFile('photo')) {
            $binary = ImageProcessor::squareJpeg($request->file('photo')->getRealPath())
                ?? file_get_contents($request->file('photo')->getRealPath());
            $path = 'employee-photos/'.Str::random(20).'.jpg';
            Storage::disk('local')->put($path, $binary);
            $data['photo_path'] = $path;

            if ($employee->photo_path) {
                Storage::disk('local')->delete($employee->photo_path);
            }
        }

        $employee->update($data);

        ActivityLogger::log('employee.updated', "Memperbarui data karyawan: {$employee->full_name} ({$employee->nip})", $employee, auth()->id());

        return redirect()->route('employees.index')->with('success', 'Data karyawan berhasil diperbarui.');
    }

    /** Soft delete - data tidak hilang permanen, bisa dipulihkan lewat database bila diperlukan. */
    public function destroy(Employee $employee)
    {
        $name = $employee->full_name;
        $employee->delete();

        ActivityLogger::log('employee.deleted', "Menghapus (arsip) karyawan: {$name}", null, auth()->id());

        return back()->with('success', 'Data karyawan diarsipkan.');
    }

    /** Menyajikan foto lewat aplikasi (private disk, bukan symlink publik). */
    public function photo(Employee $employee)
    {
        abort_unless($employee->photo_path && Storage::disk('local')->exists($employee->photo_path), 404);

        return Storage::disk('local')->response($employee->photo_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    public function export()
    {
        abort_unless(auth()->user()->can('employees.export'), 403);

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\EmployeesExport, 'data-karyawan-'.now()->format('Y-m-d').'.xlsx');
    }
}
