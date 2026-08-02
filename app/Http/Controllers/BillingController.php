<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Semester;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    // ==============================
    // INSTITUTIONAL BILLING (B2B)
    // University/department-level subscriptions and semester-based invoices
    // ==============================

    // Student: View my invoices (institutional billing)
    public function myInvoices()
    {
        $invoices = Invoice::where('user_id', Auth::id())
            ->with('semester')
            ->latest()
            ->paginate(10);

        return view('billing.my', compact('invoices'));
    }

    // Student: View invoice details
    public function showInvoice(Invoice $invoice)
    {
        if ($invoice->user_id !== Auth::id()) {
            abort(403);
        }

        $invoice->load('semester', 'payments');

        return view('billing.show', compact('invoice'));
    }

    // Student: Make payment
    public function pay(Request $request, Invoice $invoice)
    {
        if ($invoice->user_id !== Auth::id()) {
            abort(403);
        }

        if ($invoice->isPaid()) {
            return back()->with('error', 'Invoice already paid.');
        }

        $validated = $request->validate([
            'payment_method' => 'required|in:bank_transfer,card,wallet',
            'amount' => 'required|numeric|min:'.$invoice->amount,
            'transaction_ref' => 'required|string',
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'user_id' => Auth::id(),
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'transaction_ref' => $validated['transaction_ref'],
            'status' => 'pending',
        ]);

        // If bank transfer, notify admin for verification
        if ($validated['payment_method'] === 'bank_transfer') {
            // Notification handled via event/listener
        }

        return redirect()->route('billing.show', $invoice)
            ->with('success', 'Payment submitted. Awaiting verification.');
    }

    // Admin: View all invoices
    public function adminIndex(Request $request)
    {
        $user = Auth::user();
        $query = Invoice::with('user', 'semester');

        // Filter by scope based on role
        if ($user->isDepartmentAdmin()) {
            $query->whereHas('user', fn ($q) => $q->where('department_id', $user->department_id));
        } elseif ($user->isUniversityAdmin()) {
            $query->whereHas('user', fn ($q) => $q->where('university_id', $user->university_id));
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->semester_id) {
            $query->where('semester_id', $request->semester_id);
        }

        $invoices = $query->latest()->paginate(20);

        return view('billing.admin', compact('invoices'));
    }

    // Admin: Verify payment
    public function verify(Invoice $invoice)
    {
        $user = Auth::user();

        // Check scope
        if ($user->isDepartmentAdmin() && $invoice->user->department_id !== $user->department_id) {
            abort(403, 'You can only verify payments in your department.');
        }
        if ($user->isUniversityAdmin() && $invoice->user->university_id !== $user->university_id) {
            abort(403, 'You can only verify payments in your university.');
        }

        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $invoice->payments()->where('status', 'pending')
            ->update(['status' => 'verified', 'verified_at' => now(), 'verified_by' => Auth::id()]);

        return back()->with('success', 'Payment verified.');
    }

    // Admin: Waive payment
    public function waive(Invoice $invoice)
    {
        $user = Auth::user();

        // Check scope
        if ($user->isDepartmentAdmin() && $invoice->user->department_id !== $user->department_id) {
            abort(403, 'You can only waive payments in your department.');
        }
        if ($user->isUniversityAdmin() && $invoice->user->university_id !== $user->university_id) {
            abort(403, 'You can only waive payments in your university.');
        }

        $invoice->update(['status' => 'waived']);

        return back()->with('success', 'Payment waived.');
    }

    // Admin: Manage institutional subscriptions (university/department level)
    public function subscriptions()
    {
        $user = Auth::user();

        $query = UserSubscription::with(['university', 'department', 'plan'])
            ->whereHas('plan', fn ($q) => $q->where('plan_type', '!=', 'b2c'));

        if ($user->isUniversityAdmin()) {
            $query->where('university_id', $user->university_id);
        } elseif ($user->isDepartmentAdmin()) {
            $query->where('department_id', $user->department_id);
        }

        $subscriptions = $query->latest()->paginate(10);

        return view('billing.subscriptions', compact('subscriptions'));
    }

    // Admin: Generate invoices for semester (institutional billing)
    public function generateInvoices(Semester $semester)
    {
        $user = Auth::user();

        $subscription = UserSubscription::where('university_id', $user->university_id)
            ->where('status', 'active')
            ->where('is_active', true)
            ->whereHas('plan', fn ($q) => $q->where('plan_type', '!=', 'b2c'))
            ->first();

        if (! $subscription) {
            return back()->with('error', 'No active institutional subscription.');
        }

        $students = User::where('university_id', $user->university_id)
            ->where('role', 'student')
            ->where('is_active', true)
            ->get();

        foreach ($students as $student) {
            $exists = Invoice::where('user_id', $student->id)
                ->where('semester_id', $semester->id)
                ->exists();

            if (! $exists) {
                Invoice::create([
                    'uuid' => Str::uuid(),
                    'user_id' => $student->id,
                    'semester_id' => $semester->id,
                    'subscription_id' => $subscription->id,
                    'amount' => $subscription->price_per_student ?? $subscription->amount,
                    'status' => 'pending',
                    'due_date' => $semester->end_date,
                ]);
            }
        }

        return back()->with('success', 'Invoices generated for '.$students->count().' students.');
    }
}
