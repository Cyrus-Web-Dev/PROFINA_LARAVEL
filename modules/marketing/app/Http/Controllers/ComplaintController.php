<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\DocumentNumber;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $complaints = Complaint::query()->with(['customer:id,name,owner_id', 'assignee:id,name', 'order:id,number'])
            ->when(! $user->can('scope.all'), fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('owner_id', $user->id)))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('subject', 'ilike', '%'.$request->q.'%')->orWhere('number', 'ilike', '%'.$request->q.'%')))
            ->latest()->paginate(15)->withQueryString()
            ->through(fn (Complaint $c) => [
                'id' => $c->id, 'number' => $c->number, 'customer' => $c->customer?->name, 'customer_id' => $c->customer_id, 'order' => $c->order?->number, 'sales_order_id' => $c->sales_order_id,
                'category' => $c->category, 'priority' => $c->priority, 'subject' => $c->subject, 'description' => $c->description, 'status' => $c->status,
                'assigned_to' => $c->assigned_to, 'assignee' => $c->assignee?->name, 'resolution' => $c->resolution, 'created_at' => $c->created_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Complaints/Index', [
            'complaints' => $complaints,
            'filters' => $request->only('q', 'status'),
            'customers' => Customer::visibleTo($user)->orderBy('name')->get(['id', 'name']),
            'orders' => SalesOrder::visibleTo($user)->latest()->limit(200)->get(['id', 'number', 'customer_id']),
            'staff' => User::role(['sales', 'sales-admin', 'marketing-manager'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'sales_order_id' => 'nullable|exists:sales_orders,id',
            'category' => ['required', Rule::in(['kualitas', 'pengiriman', 'harga', 'layanan', 'lainnya'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'subject' => 'required|string|max:180',
            'description' => 'required|string|max:4000',
        ]);
        Customer::visibleTo($request->user())->findOrFail($data['customer_id']);

        $c = Complaint::create($data + ['number' => DocumentNumber::next('CP'), 'status' => 'open', 'created_by' => $request->user()->id]);
        ActivityLogger::log('complaint.created', "Komplain {$c->number}: {$c->subject}", $c);

        return back()->with('success', "Komplain {$c->number} dicatat.");
    }

    public function update(Request $request, Complaint $complaint)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'resolved', 'closed'])],
            'assigned_to' => 'nullable|exists:users,id',
            'resolution' => 'nullable|string|max:4000',
        ]);
        abort_if(in_array($data['status'], ['resolved', 'closed'], true) && blank($data['resolution'] ?? $complaint->resolution), 422, 'Isi penyelesaian sebelum menutup komplain.');

        $complaint->update($data + ['resolved_at' => in_array($data['status'], ['resolved', 'closed'], true) ? ($complaint->resolved_at ?? now()) : null]);
        ActivityLogger::log('complaint.updated', "Komplain {$complaint->number} → {$complaint->status}", $complaint);

        return back()->with('success', 'Komplain diperbarui.');
    }
}
