<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Semester;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function myInvoices(Request $request): View
    {
        Gate::authorize('viewAny', Invoice::class);

        $invoices = Invoice::query()
            ->where('user_id', $request->user()->id)
            ->with('semester')
            ->latest()
            ->paginate(10);

        return view('billing.my', compact('invoices'));
    }

    public function showInvoice(Request $request, Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);
        abort_unless($invoice->user_id === $request->user()->id, 403);

        $invoice->load('semester', 'payments');

        return view('billing.show', compact('invoice'));
    }

    /**
     * Record a manual bank-transfer reference. Card and wallet checkout remain
     * owned by the existing gateway transaction service and are not simulated here.
     */
    public function pay(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('makePayment', $invoice);
        abort_unless($invoice->user_id === $request->user()->id, 403);

        if ($invoice->isPaid()) {
            return back()->with('error', 'Invoice already paid.');
        }

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(['bank_transfer'])],
            'amount' => ['required', 'numeric', 'min:'.$invoice->amount, 'max:'.$invoice->amount],
            'transaction_ref' => ['required', 'string', 'max:100', Rule::unique('payments', 'transaction_ref')],
        ]);

        DB::transaction(function () use ($invoice, $request, $validated): void {
            Payment::create([
                'invoice_id' => $invoice->id,
                'user_id' => $request->user()->id,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'transaction_ref' => trim($validated['transaction_ref']),
                'status' => 'pending',
            ]);

            $invoice->update([
                'payment_method' => $validated['payment_method'],
                'transaction_ref' => trim($validated['transaction_ref']),
            ]);
        });

        return redirect()->route('billing.show', $invoice)
            ->with('success', 'Payment reference submitted. An authorized administrator must verify it.');
    }

    public function adminIndex(Request $request): View
    {
        Gate::authorize('viewAny', Invoice::class);
        $query = Invoice::query()->with(['user', 'semester', 'payments']);
        $this->scopeInvoicesForAdmin($query, $request->user());

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('semester_id')) {
            $query->where('semester_id', $request->integer('semester_id'));
        }

        $invoices = $query->latest()->paginate(20)->withQueryString();

        return view('billing.admin', compact('invoices'));
    }

    public function verify(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('verify', $invoice);
        $this->ensureAdminScope($request->user(), $invoice);

        if ($invoice->isPaid()) {
            return back()->with('error', 'Invoice is already paid.');
        }

        $pendingPayment = $invoice->payments()->where('status', 'pending')->latest()->first();
        if (! $pendingPayment) {
            return back()->with('error', 'No pending payment reference exists for this invoice.');
        }

        DB::transaction(function () use ($invoice, $pendingPayment, $request): void {
            $pendingPayment->update([
                'status' => 'verified',
                'verified_at' => now(),
                'verified_by' => $request->user()->id,
            ]);

            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_method' => $pendingPayment->payment_method,
                'transaction_ref' => $pendingPayment->transaction_ref,
            ]);
        });

        return back()->with('success', 'Payment verified.');
    }

    public function waive(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('waive', $invoice);
        $this->ensureAdminScope($request->user(), $invoice);

        if ($invoice->isPaid()) {
            return back()->with('error', 'A paid invoice cannot be waived.');
        }

        $invoice->update(['status' => 'waived']);

        return back()->with('success', 'Invoice waived.');
    }

    public function subscriptions(Request $request): View
    {
        $user = $request->user();
        $query = UserSubscription::query()
            ->with(['university', 'department', 'plan'])
            ->whereHas('plan', fn ($plan) => $plan->where('plan_type', '!=', 'b2c'));

        if ($user->isDepartmentAdmin()) {
            $query->where('department_id', $user->department_id);
        } elseif ($user->isUniversityAdmin()) {
            $query->where('university_id', $user->university_id);
        }

        $subscriptions = $query->latest()->paginate(10);

        return view('billing.subscriptions', compact('subscriptions'));
    }

    public function generateInvoices(Request $request, Semester $semester): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isUniversityAdmin() || $user->isSuperAdmin(), 403);
        abort_unless(
            $user->isSuperAdmin() || $semester->academicSession?->university_id === $user->university_id,
            403,
            'The semester does not belong to your university.'
        );

        $universityId = $user->isSuperAdmin()
            ? $semester->academicSession?->university_id
            : $user->university_id;

        $subscription = UserSubscription::query()
            ->with('plan')
            ->where('university_id', $universityId)
            ->where('status', 'active')
            ->where('is_active', true)
            ->whereHas('plan', fn ($plan) => $plan->where('plan_type', '!=', 'b2c'))
            ->first();

        if (! $subscription || ! $subscription->isActive()) {
            return back()->with('error', 'No active institutional subscription was found.');
        }

        $amount = (float) ($subscription->price_per_student
            ?? $subscription->amount
            ?? $subscription->plan?->price_per_semester
            ?? 0);

        if ($amount <= 0) {
            return back()->with('error', 'The institutional subscription has no valid per-student price.');
        }

        $students = User::query()
            ->where('university_id', $universityId)
            ->where('role', 'student')
            ->where('is_active', true)
            ->get();

        $created = 0;
        foreach ($students as $student) {
            $invoice = Invoice::firstOrCreate(
                [
                    'user_id' => $student->id,
                    'semester_id' => $semester->id,
                ],
                [
                    'user_subscription_id' => $subscription->id,
                    'amount' => $amount,
                    'status' => 'pending',
                    'due_date' => $semester->end_date,
                ]
            );
            $created += $invoice->wasRecentlyCreated ? 1 : 0;
        }

        return back()->with('success', "{$created} new invoice(s) generated; existing semester invoices were preserved.");
    }

    private function ensureAdminScope(User $user, Invoice $invoice): void
    {
        $invoice->loadMissing('user');

        if ($user->isDepartmentAdmin()) {
            abort_unless($invoice->user?->department_id === $user->department_id, 403);
        } elseif ($user->isUniversityAdmin()) {
            abort_unless($invoice->user?->university_id === $user->university_id, 403);
        }
    }

    private function scopeInvoicesForAdmin($query, User $user): void
    {
        if ($user->isDepartmentAdmin()) {
            $query->whereHas('user', fn ($users) => $users->where('department_id', $user->department_id));
        } elseif ($user->isUniversityAdmin()) {
            $query->whereHas('user', fn ($users) => $users->where('university_id', $user->university_id));
        }
    }
}
