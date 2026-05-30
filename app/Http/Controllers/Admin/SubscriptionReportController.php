<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserSubscription;
use App\Models\Transaction;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionReportController extends Controller
{
    /**
     * Subscription analytics dashboard
     */
    public function dashboard(Request $request)
    {
        $this->authorize('viewAny', \App\Models\SubscriptionPlan::class);

        $user = $request->user();
        
        // Overall stats
        $activeSubscriptions = UserSubscription::where('status', 'active')->count();
        $expiredSubscriptions = UserSubscription::where('status', 'expired')->count();
        $cancelledSubscriptions = UserSubscription::where('status', 'cancelled')->count();
        
        // Revenue stats (last 30 days)
        $revenue30Days = Transaction::where('type', 'payment')
            ->where('status', 'completed')
            ->where('processed_at', '>=', now()->subDays(30))
            ->sum('amount');
        
        $revenueAllTime = Transaction::where('type', 'payment')
            ->where('status', 'completed')
            ->sum('amount');
        
        // Failed payments
        $failedPayments = Transaction::where('type', 'payment')
            ->where('status', 'failed')
            ->where('processed_at', '>=', now()->subDays(30))
            ->count();
        
        // Active plans breakdown
        $planStats = SubscriptionPlan::withCount(['subscribers' => function ($query) {
            $query->where('status', 'active');
        }])
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->get()
        ->map(function ($plan) {
            return [
                'name' => $plan->display_name,
                'type' => $plan->plan_type,
                'active_subscribers' => $plan->subscribers_count,
                'price' => $plan->price_per_month,
                'revenue' => $plan->price_per_month * $plan->subscribers_count,
            ];
        });
        
        // Monthly recurring revenue
        $mrr = UserSubscription::where('status', 'active')
            ->whereHas('plan', function ($query) {
                $query->where('is_active', true);
            })
            ->whereNotNull('amount')
            ->sum('amount');
        
        // Recent transactions
        $recentTransactions = Transaction::with(['user', 'paymentGateway'])
            ->where('type', 'payment')
            ->latest()
            ->take(20)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'user' => $transaction->user ? $transaction->user->full_name : 'N/A',
                    'amount' => $transaction->amount,
                    'status' => $transaction->status,
                    'gateway' => $transaction->paymentGateway ? $transaction->paymentGateway->name : 'N/A',
                    'created_at' => $transaction->created_at->format('Y-m-d H:i'),
                ];
            });
        
        // Subscription growth by month
        $growthData = UserSubscription::select(
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
            DB::raw('COUNT(*) as count')
        )
        ->where('created_at', '>=', now()->subMonths(6))
        ->groupBy('month')
        ->orderBy('month')
        ->get();
        
        // Top plans
        $topPlans = UserSubscription::where('status', 'active')
            ->join('subscription_plans', 'user_subscriptions.plan_id', '=', 'subscription_plans.id')
            ->select(
                'subscription_plans.display_name',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('subscription_plans.display_name')
            ->orderBy('count', 'desc')
            ->take(5)
            ->get();
        
        // Expiring soon
        $expiringSoon = UserSubscription::where('status', 'active')
            ->whereBetween('ends_at', [now(), now()->addDays(7)])
            ->with('user', 'plan')
            ->take(10)
            ->get();
        
        return view('admin.subscriptions.dashboard', compact(
            'activeSubscriptions',
            'expiredSubscriptions',
            'cancelledSubscriptions',
            'revenue30Days',
            'revenueAllTime',
            'failedPayments',
            'planStats',
            'mrr',
            'recentTransactions',
            'growthData',
            'topPlans',
            'expiringSoon'
        ));
    }
    
    /**
     * Export subscription report
     */
    public function export(Request $request, $format = 'csv')
    {
        $this->authorize('viewAny', \App\Models\SubscriptionPlan::class);
        
        $subscriptions = UserSubscription::with(['user', 'plan'])
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->plan_id, function ($query, $planId) {
                $query->where('plan_id', $planId);
            })
            ->latest()
            ->get();
        
        if ($format === 'csv') {
            $filename = 'subscriptions_report_' . now()->format('Y-m-d') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
            ];
            
            $callback = function () use ($subscriptions) {
                $file = fopen('php://output', 'w');
                
                // Header
                fputcsv($file, [
                    'ID', 'User', 'Email', 'Plan', 'Status', 'Amount', 'Currency',
                    'Start Date', 'End Date', 'Billing Cycle', 'Auto Renew'
                ]);
                
                // Data
                foreach ($subscriptions as $subscription) {
                    fputcsv($file, [
                        $subscription->id,
                        $subscription->user->full_name ?? 'N/A',
                        $subscription->user->email ?? 'N/A',
                        $subscription->plan->display_name ?? 'N/A',
                        ucfirst($subscription->status),
                        $subscription->amount,
                        $subscription->currency,
                        $subscription->started_at->format('Y-m-d'),
                        $subscription->ends_at->format('Y-m-d'),
                        $subscription->billing_cycle,
                        $subscription->auto_renew ? 'Yes' : 'No',
                    ]);
                }
                
                fclose($file);
            };
            
            return response()->stream($callback, 200, $headers);
        }
        
        return redirect()->back()->with('error', 'Unsupported format');
    }
}
