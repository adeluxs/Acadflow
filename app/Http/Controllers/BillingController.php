<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CommercialAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Semester;
use App\Models\User;
use App\Services\PaymentService;
use App\Support\Money;
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
        $invoices = Invoice::query()->where('user_id', $request->user()->id)->with('semester')->latest()->paginate(10);
        return view('billing.my', compact('invoices'));
    }

    public function showInvoice(Request $request, Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);
        abort_unless($invoice->user_id === $request->user()->id, 403);
        $invoice->load('semester', 'payments');
        return view('billing.show', compact('invoice'));
    }

    /** Record a manual bank-transfer reference without trusting a client amount. */
    public function pay(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('makePayment', $invoice);
        abort_unless($invoice->user_id === $request->user()->id, 403);
        if ($invoice->isPaid()) return back()->with('error', 'Invoice already paid.');

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(['bank_transfer'])],
            'transaction_ref' => ['required', 'string', 'max:100', Rule::unique('payments', 'transaction_ref')],
        ]);
        $invoiceMinor = $invoice->amount_minor !== null ? (int) $invoice->amount_minor : Money::toMinor((string) $invoice->amount);
        $invoice->loadMissing('user');
        $universityId = $invoice->user?->university_id;
        $account = $universityId ? CommercialAccount::query()->where('university_id', $universityId)->first() : null;
        $currency = strtoupper((string) ($invoice->currency ?: $account?->currency ?: \App\Services\SettingService::get('currency', 'NGN', $universityId)));

        DB::transaction(function () use ($invoice, $request, $validated, $invoiceMinor, $currency): void {
            Payment::query()->create([
                'invoice_id' => $invoice->id,
                'user_id' => $request->user()->id,
                'amount_minor' => $invoiceMinor,
                'amount' => Money::fromMinor($invoiceMinor),
                'currency' => $currency,
                'payment_method' => $validated['payment_method'],
                'transaction_ref' => trim($validated['transaction_ref']),
                'status' => 'pending',
            ]);
            $invoice->update(['currency' => $currency, 'payment_method' => $validated['payment_method'], 'transaction_ref' => trim($validated['transaction_ref'])]);
        });

        return redirect()->route('billing.show', $invoice)->with('success', 'Payment reference submitted. An authorized administrator must verify it.');
    }

    public function adminIndex(Request $request): View
    {
        Gate::authorize('viewAny', Invoice::class);
        $query = Invoice::query()->with(['user', 'semester', 'payments']);
        $this->scopeInvoicesForAdmin($query, $request->user());
        if ($request->filled('status')) $query->where('status', $request->string('status')->toString());
        if ($request->filled('semester_id')) $query->where('semester_id', $request->integer('semester_id'));
        $invoices = $query->latest()->paginate(20)->withQueryString();
        return view('billing.admin', compact('invoices'));
    }

    public function verify(Request $request, Invoice $invoice, PaymentService $payments): RedirectResponse
    {
        Gate::authorize('verify', $invoice);
        $this->ensureAdminScope($request->user(), $invoice);
        if ($invoice->isPaid()) return back()->with('error', 'Invoice is already paid.');
        $pendingPayment = $invoice->payments()->where('status', 'pending')->latest()->first();
        if (! $pendingPayment) return back()->with('error', 'No pending payment reference exists for this invoice.');

        $payments->verifyPayment($pendingPayment, $request->user());
        return back()->with('success', 'Payment verified.');
    }

    public function waive(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('waive', $invoice);
        $this->ensureAdminScope($request->user(), $invoice);
        if ($invoice->isPaid()) return back()->with('error', 'A paid invoice cannot be waived.');
        $invoice->update(['status' => 'waived']);
        return back()->with('success', 'Invoice waived.');
    }

    public function generateInvoices(Request $request, Semester $semester, PaymentService $payments): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isUniversityAdmin() || $user->isSuperAdmin(), 403);
        abort_unless($user->isSuperAdmin() || $semester->academicSession?->university_id === $user->university_id, 403, 'The semester does not belong to your university.');

        $universityId = $semester->academicSession?->university_id;
        $account = $universityId ? CommercialAccount::query()->where('university_id', $universityId)->where('status', 'active')->first() : null;
        if (! $account || (int) data_get($account->metadata, 'student_semester_fee_minor', 0) <= 0) {
            return back()->with('error', 'No student semester fee is configured in this institution’s commercial account.');
        }

        $students = User::query()->where('university_id', $universityId)->where('role', 'student')->where('is_active', true)->get();
        $created = 0;
        foreach ($students as $student) {
            $invoice = $payments->generateInvoice($student, $semester, $account);
            if ($invoice->wasRecentlyCreated) $created++;
        }
        return back()->with('success', "{$created} new invoice(s) generated; existing semester invoices were preserved.");
    }

    private function ensureAdminScope(User $user, Invoice $invoice): void
    {
        $invoice->loadMissing('user');
        if ($user->isDepartmentAdmin()) abort_unless($invoice->user?->department_id === $user->department_id, 403);
        elseif ($user->isUniversityAdmin()) abort_unless($invoice->user?->university_id === $user->university_id, 403);
    }

    private function scopeInvoicesForAdmin($query, User $user): void
    {
        if ($user->isDepartmentAdmin()) $query->whereHas('user', fn ($users) => $users->where('department_id', $user->department_id));
        elseif ($user->isUniversityAdmin()) $query->whereHas('user', fn ($users) => $users->where('university_id', $user->university_id));
    }
}
