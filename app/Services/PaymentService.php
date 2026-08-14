<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Semester;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Str;

class PaymentService
{
    public function generateInvoice(User $user, Semester $semester, UserSubscription $subscription): Invoice
    {
        $existingInvoice = Invoice::where('user_id', $user->id)
            ->where('semester_id', $semester->id)
            ->first();

        if ($existingInvoice) {
            return $existingInvoice;
        }

        $amount = $this->calculateAmount($subscription, $user);
        $graceDays = $subscription->grace_days ?? 7;

        return Invoice::create([
            'user_id' => $user->id,
            'semester_id' => $semester->id,
            'subscription_id' => $subscription->id,
            'amount' => $amount,
            'status' => InvoiceStatus::PENDING->value,
            'due_date' => $semester->start_date->addDays($graceDays),
            'transaction_ref' => $this->generateTransactionRef(),
        ]);
    }

    public function generateInvoicesForSemester(Semester $semester): int
    {
        $subscription = $semester->academicSession->university->userSubscriptions()
            ->where('status', 'active')
            ->where('is_active', true)
            ->whereHas('plan', fn ($q) => $q->where('plan_type', '!=', 'b2c'))
            ->first();

        if (! $subscription) {
            return 0;
        }

        $students = $semester->enrollments()
            ->where('status', 'enrolled')
            ->with('user')
            ->get()
            ->pluck('user');

        $count = 0;
        foreach ($students as $student) {
            $this->generateInvoice($student, $semester, $subscription);
            $count++;
        }

        return $count;
    }

    public function initiatePayment(Invoice $invoice, string $paymentMethod): Payment
    {
        $reference = $this->generatePaymentReference();

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'user_id' => $invoice->user_id,
            'amount' => $invoice->amount,
            'payment_method' => $paymentMethod,
            'transaction_ref' => $this->generateTransactionRef(),
            'reference' => $reference,
            'status' => PaymentStatus::PENDING->value,
        ]);

        return $payment;
    }

    public function verifyPayment(Payment $payment, User $verifiedBy): Payment
    {
        $payment->update([
            'status' => PaymentStatus::VERIFIED->value,
            'verified_at' => now(),
            'verified_by' => $verifiedBy->id,
        ]);

        $payment->invoice->update([
            'status' => InvoiceStatus::PAID->value,
            'paid_at' => now(),
            'payment_method' => (string) $payment->payment_method,
            'transaction_ref' => $payment->transaction_ref,
        ]);

        return $payment;
    }

    public function verifyPaymentByReference(string $reference): ?Payment
    {
        $payment = Payment::where('reference', $reference)->first();

        if (! $payment || $payment->status === PaymentStatus::VERIFIED->value) {
            return $payment;
        }

        return $this->verifyPayment($payment, $payment->user);
    }

    public function canSubmit(User $user, Semester $semester): bool
    {
        $invoice = Invoice::where('user_id', $user->id)
            ->where('semester_id', $semester->id)
            ->first();

        if (! $invoice) {
            return false;
        }

        if ($invoice->status === InvoiceStatus::PAID->value) {
            return true;
        }

        if ($invoice->status === InvoiceStatus::PENDING->value) {
            $graceDays = 7;
            $daysSinceStart = $semester->start_date->diffInDays(now());

            return $daysSinceStart <= $graceDays;
        }

        return false;
    }

    public function getPaymentStats(UserSubscription $subscription): array
    {
        $invoices = Invoice::where('subscription_id', $subscription->id);

        return [
            'total_invoices' => $invoices->count(),
            'total_amount' => $invoices->sum('amount'),
            'paid' => $invoices->where('status', InvoiceStatus::PAID->value)->count(),
            'pending' => $invoices->where('status', InvoiceStatus::PENDING->value)->count(),
            'overdue' => $invoices->where('status', InvoiceStatus::OVERDUE->value)->count(),
            'waived' => $invoices->where('status', InvoiceStatus::WAIVED->value)->count(),
            'collection_rate' => $this->calculateCollectionRate($subscription),
        ];
    }

    protected function calculateAmount(UserSubscription $subscription, User $user): float
    {
        $billingModel = $subscription->billing_model;

        return match ($billingModel) {
            'institution' => 0,
            'student' => (float) $subscription->price_per_student,
            'hybrid' => (float) $subscription->price_per_student * 0.5,
            default => (float) $subscription->price_per_student,
        };
    }

    protected function generateTransactionRef(): string
    {
        return 'TXN-'.strtoupper(Str::random(16));
    }

    protected function generatePaymentReference(): string
    {
        return 'PAY-'.strtoupper(Str::random(12));
    }

    protected function calculateCollectionRate(UserSubscription $subscription): float
    {
        $invoices = Invoice::where('subscription_id', $subscription->id);
        $total = $invoices->count();

        if ($total === 0) {
            return 0;
        }

        $paid = $invoices->where('status', InvoiceStatus::PAID->value)->count();

        return round(($paid / $total) * 100, 2);
    }
}
