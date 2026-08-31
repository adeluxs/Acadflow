<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\CommercialAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Semester;
use App\Models\User;
use App\Support\Money;
use App\Services\Commerce\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Generic institutional invoice/payment service.
 *
 * Academic semester invoices are intentionally independent of the retired SaaS
 * subscription model. CommercialAccount metadata supplies optional institution
 * billing terms while historical subscription foreign keys remain read-only.
 */
class PaymentService
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function generateInvoice(User $user, Semester $semester, CommercialAccount $account): Invoice
    {
        $existing = Invoice::query()->where('user_id', $user->id)->where('semester_id', $semester->id)->first();
        if ($existing) return $existing;

        $feeMinor = (int) data_get($account->metadata, 'student_semester_fee_minor', 0);
        if ($feeMinor <= 0) {
            throw ValidationException::withMessages(['billing' => 'No student semester fee is configured for this commercial account.']);
        }
        $graceDays = max(0, min(90, (int) data_get($account->metadata, 'invoice_grace_days', 7)));

        return Invoice::query()->create([
            'user_id' => $user->id,
            'semester_id' => $semester->id,
            'subscription_id' => null,
            'user_subscription_id' => null,
            'amount_minor' => $feeMinor,
            'amount' => Money::fromMinor($feeMinor),
            'currency' => strtoupper((string) $account->currency),
            'status' => InvoiceStatus::PENDING->value,
            'due_date' => now()->addDays($graceDays)->toDateString(),
            'transaction_ref' => $this->generateTransactionRef(),
        ]);
    }

    public function generateInvoicesForSemester(Semester $semester): int
    {
        $universityId = $semester->academicSession?->university_id;
        if (! $universityId) return 0;

        $account = CommercialAccount::query()->where('university_id', $universityId)->where('status', 'active')->first();
        if (! $account || (int) data_get($account->metadata, 'student_semester_fee_minor', 0) <= 0) return 0;

        $students = $semester->enrollments()->where('status', 'enrolled')->with('user')->get()->pluck('user')->filter();
        $count = 0;
        foreach ($students as $student) {
            $invoice = $this->generateInvoice($student, $semester, $account);
            if ($invoice->wasRecentlyCreated) $count++;
        }
        return $count;
    }

    public function initiatePayment(Invoice $invoice, string $paymentMethod): Payment
    {
        $existing=Payment::query()->where('invoice_id',$invoice->id)->where('payment_method',$paymentMethod)->where('status',PaymentStatus::PENDING->value)
            ->where('created_at','>=',now()->subMinutes(30))->latest()->first();
        if($existing) return $existing;

        $reference = $this->generatePaymentReference();
        $amountMinor = $invoice->amount_minor !== null ? (int) $invoice->amount_minor : Money::toMinor((string) $invoice->amount);
        $invoice->loadMissing('user');
        $universityId = $invoice->user?->university_id;
        $account = $universityId ? CommercialAccount::query()->where('university_id', $universityId)->first() : null;
        $currency = strtoupper((string) ($invoice->currency ?: $account?->currency ?: SettingService::get('currency', 'NGN', $universityId)));

        return Payment::query()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $invoice->user_id,
            'amount_minor' => $amountMinor,
            'amount' => Money::fromMinor($amountMinor),
            'currency' => $currency,
            'payment_method' => $paymentMethod,
            'transaction_ref' => $this->generateTransactionRef(),
            'reference' => $reference,
            'status' => PaymentStatus::PENDING->value,
        ]);
    }

    public function verifyPayment(Payment $payment, User $verifiedBy): Payment
    {
        return DB::transaction(function () use ($payment,$verifiedBy): Payment {
            $locked=Payment::query()->with('invoice.user')->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if($locked->status===PaymentStatus::VERIFIED->value) return $locked;
            $invoice=Invoice::query()->whereKey($locked->invoice_id)->lockForUpdate()->firstOrFail();
            $amountMinor=$locked->amount_minor!==null?(int)$locked->amount_minor:Money::toMinor((string)$locked->amount);
            if($amountMinor<=0) throw ValidationException::withMessages(['payment'=>'Verified payment amount must be greater than zero.']);
            $universityId=$locked->invoice?->user?->university_id;
            $account=$universityId?CommercialAccount::query()->where('university_id',$universityId)->first():null;
            $currency=strtoupper((string)($locked->currency ?: $invoice->currency ?: $account?->currency ?: SettingService::get('currency','NGN',$universityId)));
            if ($locked->currency && strtoupper((string) $locked->currency) !== $currency) {
                throw ValidationException::withMessages(['payment' => 'Payment currency does not match the invoice currency.']);
            }
            if ($invoice->currency && strtoupper((string) $invoice->currency) !== $currency) {
                throw ValidationException::withMessages(['payment' => 'Invoice currency does not match the payment currency.']);
            }

            $locked->update([
                'currency' => $currency,
                'status' => PaymentStatus::VERIFIED->value,
                'verified_at' => now(),
                'verified_by' => $verifiedBy->id,
            ]);

            $invoice->update([
                'currency' => $currency,
                'status' => InvoiceStatus::PAID->value,
                'paid_at' => now(),
                'payment_method' => (string) $locked->payment_method,
                'transaction_ref' => $locked->transaction_ref,
            ]);

            $this->ledger->post('institution-invoice-payment:'.$locked->uuid,'institution_invoice_payment',$currency,[
                ['account_code'=>'institution_payment_clearing','direction'=>'debit','amount_minor'=>$amountMinor],
                ['account_code'=>'institution_billing_payable:'.($universityId?:'unassigned'),'direction'=>'credit','amount_minor'=>$amountMinor],
            ],$locked->user,[
                'payment_uuid'=>$locked->uuid,'invoice_uuid'=>$invoice->uuid,'university_id'=>$universityId,'verified_by'=>$verifiedBy->id,
            ]);

            return $locked->fresh(['invoice']);
        },3);
    }

    public function verifyPaymentByReference(string $reference): ?Payment
    {
        $payment = Payment::query()->where('reference', $reference)->first();
        if (! $payment || $payment->status === PaymentStatus::VERIFIED->value) return $payment;

        return $this->verifyPayment($payment, $payment->user);
    }

    public function canSubmit(User $user, Semester $semester): bool
    {
        $invoice = Invoice::query()->where('user_id', $user->id)->where('semester_id', $semester->id)->first();
        if (! $invoice) return true; // No institution invoice means academic access is not commercially blocked.
        if (in_array($invoice->status, [InvoiceStatus::PAID->value, InvoiceStatus::WAIVED->value], true)) return true;

        if ($invoice->status === InvoiceStatus::PENDING->value) {
            $universityId = $semester->academicSession?->university_id;
            $account = $universityId ? CommercialAccount::query()->where('university_id', $universityId)->first() : null;
            $graceDays = max(0, min(90, (int) data_get($account?->metadata, 'invoice_grace_days', 7)));
            return $semester->start_date->diffInDays(now()) <= $graceDays;
        }

        return false;
    }

    /** @return array<string,int|string> */
    public function getPaymentStats(CommercialAccount $account): array
    {
        $query = Invoice::query()->whereHas('user', fn ($q) => $q->where('university_id', $account->university_id));
        $total = (clone $query)->count();
        $paid = (clone $query)->where('status', InvoiceStatus::PAID->value)->count();
        $verifiedMinor = (int) (clone $query)->where('status', InvoiceStatus::PAID->value)->sum('amount_minor');

        return [
            'total_invoices' => $total,
            'total_amount_minor' => (int) (clone $query)->sum('amount_minor'),
            'total_amount' => Money::fromMinor((int) (clone $query)->sum('amount_minor')),
            'paid' => $paid,
            'paid_amount_minor' => $verifiedMinor,
            'pending' => (clone $query)->where('status', InvoiceStatus::PENDING->value)->count(),
            'overdue' => (clone $query)->where('status', InvoiceStatus::OVERDUE->value)->count(),
            'waived' => (clone $query)->where('status', InvoiceStatus::WAIVED->value)->count(),
            'collection_rate_basis_points' => $total === 0 ? 0 : intdiv(($paid * 10_000) + intdiv($total, 2), $total),
        ];
    }

    private function generateTransactionRef(): string { return 'TXN-'.strtoupper(Str::random(16)); }
    private function generatePaymentReference(): string { return 'PAY-'.strtoupper(Str::random(12)); }
}
