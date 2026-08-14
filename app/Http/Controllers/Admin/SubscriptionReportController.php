<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SubscriptionReportController extends Controller
{
    public function dashboard(Request $request)
    {
        $this->authorize('viewAny', SubscriptionPlan::class);
        $user = $request->user();

        $subscriptions = $this->scopeSubscriptions(UserSubscription::query(), $user);
        $transactions = $this->scopeTransactions(Transaction::query(), $user);

        $activeSubscriptions = (clone $subscriptions)->where('status', 'active')->count();
        $expiredSubscriptions = (clone $subscriptions)->where('status', 'expired')->count();
        $cancelledSubscriptions = (clone $subscriptions)->where('status', 'cancelled')->count();

        $completedPayments = (clone $transactions)->where('type', 'payment')->where('status', 'completed');
        $revenue30Days = (clone $completedPayments)->where('processed_at', '>=', now()->subDays(30))->sum('amount');
        $revenueAllTime = $completedPayments->sum('amount');
        $failedPayments = (clone $transactions)->where('type', 'payment')->where('status', 'failed')->where('processed_at', '>=', now()->subDays(30))->count();

        $planStats = SubscriptionPlan::query()
            ->where('is_active', true)
            ->withCount(['subscribers as active_subscribers_count' => function (Builder $query) use ($user): void {
                $this->applySubscriptionScope($query->where('status', 'active'), $user);
            }])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (SubscriptionPlan $plan) => [
                'name' => $plan->display_name,
                'type' => $plan->plan_type,
                'active_subscribers' => $plan->active_subscribers_count,
                'price' => $plan->price_per_month,
                'revenue' => (float) $plan->price_per_month * (int) $plan->active_subscribers_count,
            ]);

        $mrr = (clone $subscriptions)
            ->where('status', 'active')
            ->whereHas('plan', fn (Builder $query) => $query->where('is_active', true))
            ->whereNotNull('amount')
            ->sum('amount');

        $recentTransactions = (clone $transactions)
            ->with(['user', 'paymentGateway'])
            ->where('type', 'payment')
            ->latest()
            ->take(20)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'user' => $transaction->user?->full_name ?? 'N/A',
                'amount' => $transaction->amount,
                'status' => $transaction->status,
                'gateway' => $transaction->paymentGateway?->name ?? 'N/A',
                'created_at' => $transaction->created_at?->format('Y-m-d H:i'),
            ]);

        $growthData = (clone $subscriptions)
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->get(['created_at'])
            ->groupBy(fn (UserSubscription $subscription) => $subscription->created_at->format('Y-m'))
            ->map(fn ($items, string $month) => (object) ['month' => $month, 'count' => $items->count()])
            ->sortBy('month')
            ->values();

        $topPlanCounts = (clone $subscriptions)
            ->where('status', 'active')
            ->selectRaw('plan_id, COUNT(*) as aggregate')
            ->groupBy('plan_id')
            ->orderByDesc('aggregate')
            ->take(5)
            ->get();
        $planNames = SubscriptionPlan::query()->whereIn('id', $topPlanCounts->pluck('plan_id'))->pluck('display_name', 'id');
        $topPlans = $topPlanCounts->map(fn ($row) => (object) [
            'display_name' => $planNames[$row->plan_id] ?? 'Unknown plan',
            'count' => (int) $row->aggregate,
        ]);

        $expiringSoon = (clone $subscriptions)
            ->where('status', 'active')
            ->whereBetween('ends_at', [now(), now()->addDays(7)])
            ->with('user', 'plan')
            ->take(10)
            ->get();

        return view('admin.subscriptions.dashboard', compact(
            'activeSubscriptions', 'expiredSubscriptions', 'cancelledSubscriptions',
            'revenue30Days', 'revenueAllTime', 'failedPayments', 'planStats', 'mrr',
            'recentTransactions', 'growthData', 'topPlans', 'expiringSoon'
        ));
    }

    public function export(Request $request, string $format = 'csv')
    {
        $this->authorize('viewAny', SubscriptionPlan::class);
        if ($format !== 'csv') return redirect()->back()->with('error', 'Unsupported format');

        $subscriptions = $this->scopeSubscriptions(UserSubscription::query(), $request->user())
            ->with(['user', 'plan'])
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('plan_id'), fn (Builder $query) => $query->where('plan_id', $request->integer('plan_id')))
            ->latest()
            ->get();

        $filename = 'subscriptions_report_'.now()->format('Y-m-d').'.csv';

        return response()->stream(function () use ($subscriptions): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'User', 'Email', 'Plan', 'Status', 'Amount', 'Currency', 'Start Date', 'End Date', 'Billing Cycle', 'Auto Renew']);
            foreach ($subscriptions as $subscription) {
                fputcsv($file, [
                    $subscription->id,
                    $subscription->user?->full_name ?? 'N/A',
                    $subscription->user?->email ?? 'N/A',
                    $subscription->plan?->display_name ?? 'N/A',
                    ucfirst((string) $subscription->status),
                    $subscription->amount,
                    $subscription->currency,
                    $subscription->started_at?->format('Y-m-d') ?? $subscription->start_date?->format('Y-m-d'),
                    $subscription->ends_at?->format('Y-m-d') ?? $subscription->end_date?->format('Y-m-d'),
                    $subscription->billing_cycle,
                    $subscription->auto_renew ? 'Yes' : 'No',
                ]);
            }
            fclose($file);
        }, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"{$filename}\""]);
    }

    private function scopeSubscriptions(Builder $query, User $user): Builder
    {
        return $this->applySubscriptionScope($query, $user);
    }

    private function applySubscriptionScope(Builder $query, User $user): Builder
    {
        if ($user->isDepartmentAdmin()) {
            return $query->where(function (Builder $scope) use ($user): void {
                $scope->where(fn (Builder $direct) => $direct->where('university_id', $user->university_id)->where('department_id', $user->department_id))
                    ->orWhereHas('user', fn (Builder $users) => $users->where('university_id', $user->university_id)->where('department_id', $user->department_id));
            });
        }
        if ($user->isUniversityAdmin()) {
            return $query->where(function (Builder $scope) use ($user): void {
                $scope->where('university_id', $user->university_id)
                    ->orWhereHas('user', fn (Builder $users) => $users->where('university_id', $user->university_id));
            });
        }
        return $query;
    }

    private function scopeTransactions(Builder $query, User $user): Builder
    {
        if ($user->isDepartmentAdmin()) return $query->whereHas('user', fn (Builder $users) => $users->where('university_id', $user->university_id)->where('department_id', $user->department_id));
        if ($user->isUniversityAdmin()) return $query->whereHas('user', fn (Builder $users) => $users->where('university_id', $user->university_id));
        return $query;
    }
}
