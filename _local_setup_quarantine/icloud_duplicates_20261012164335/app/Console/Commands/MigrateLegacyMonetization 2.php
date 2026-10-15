<?php

namespace App\Console\Commands;

use App\Models\CommercialAccount;
use App\Models\FeatureEntitlement;
use App\Support\Money;
use App\Services\SettingService;
use App\Services\Commerce\LedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateLegacyMonetization extends Command
{
    protected $signature = 'acadflow:monetization-migrate {--apply : Persist the migration; without this option the command is dry-run only}';
    protected $description = 'Safely backfill minor-unit balances and migrate active legacy plan capabilities to independent entitlements';

    public function handle(): int
    {
        if (! Schema::hasTable('feature_entitlements') || ! Schema::hasColumn('wallet_accounts', 'available_earnings_minor')) {
            $this->error('Run the database migrations first. The monetization foundation schema is not installed.');
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $stats = [
            'wallets' => 0,
            'wallet_opening_journals' => 0,
            'transactions' => 0,
            'invoices' => 0,
            'payments' => 0,
            'invoice_currencies' => 0,
            'payment_currencies' => 0,
            'orders' => 0,
            'order_items' => 0,
            'allocations' => 0,
            'refunds' => 0,
            'withdrawals' => 0,
            'entitlements' => 0,
            'commercial_accounts' => 0,
        ];

        $this->comment($apply ? 'APPLY mode: changes will be persisted.' : 'DRY RUN: no database rows will be changed. Re-run with --apply after reviewing this output.');

        $runner = function () use (&$stats, $apply): void {
            [$stats['wallets'],$stats['wallet_opening_journals']] = $this->backfillWallets($apply);
            $stats['transactions'] = $this->backfillColumn('transactions', 'amount', 'amount_minor', $apply);
            $stats['invoices'] = $this->backfillColumn('invoices', 'amount', 'amount_minor', $apply);
            $stats['payments'] = $this->backfillColumn('payments', 'amount', 'amount_minor', $apply);
            $stats['invoice_currencies'] = $this->backfillBillingCurrencies('invoices', $apply);
            $stats['payment_currencies'] = $this->backfillBillingCurrencies('payments', $apply);
            $stats['orders'] = $this->backfillOrders($apply);
            $stats['order_items'] = $this->backfillPairTable('commerce_order_items', ['unit_price' => 'unit_price_minor', 'total_price' => 'total_price_minor'], $apply);
            $stats['allocations'] = $this->backfillColumn('commerce_revenue_allocations', 'amount', 'amount_minor', $apply);
            $stats['refunds'] = $this->backfillColumn('commerce_refunds', 'amount', 'amount_minor', $apply);
            $stats['withdrawals'] = $this->backfillPairTable('withdrawal_requests', ['amount' => 'amount_minor', 'fee' => 'fee_minor'], $apply);
            [$stats['entitlements'], $stats['commercial_accounts']] = $this->migrateSubscriptions($apply);
        };

        if ($apply) {
            DB::transaction($runner, 3);
        } else {
            $runner();
        }

        foreach ($stats as $key => $value) {
            $this->line(sprintf('%-24s %d', str_replace('_', ' ', ucfirst($key)), $value));
        }

        $this->newLine();
        $this->info($apply
            ? 'Legacy monetization compatibility migration completed. No subscription, payment, invoice, or transaction history was deleted.'
            : 'Dry run complete. Review the counts, back up the database, then run with --apply during the approved migration window.');

        return self::SUCCESS;
    }

    /** @return array{0:int,1:int} */
    private function backfillWallets(bool $apply): array
    {
        if (! Schema::hasTable('wallet_accounts')) return [0,0];
        $count = 0; $openings=0;
        DB::table('wallet_accounts')->orderBy('id')->chunkById(500, function ($rows) use (&$count,&$openings,$apply): void {
            foreach ($rows as $row) {
                $available = Money::toMinor((string) ($row->available_balance ?? '0'));
                $pending = Money::toMinor((string) ($row->pending_balance ?? '0'));
                $lifetime = Money::toMinor((string) ($row->lifetime_earnings ?? '0'));
                $needsBackfill=(int)($row->available_earnings_minor??0)!==$available || (int)($row->pending_earnings_minor??0)!==$pending || (int)($row->lifetime_earnings_minor??0)!==$lifetime;
                if($needsBackfill){
                    $count++;
                    if ($apply) DB::table('wallet_accounts')->where('id', $row->id)->update([
                        // Historical wallet balances represented creator earnings, not prepaid spending.
                        'available_earnings_minor' => $available,
                        'pending_earnings_minor' => $pending,
                        'lifetime_earnings_minor' => $lifetime,
                    ]);
                }

                $openingTotal=max(0,$available)+max(0,$pending);
                if($openingTotal<=0) continue;
                $reference='legacy-wallet-opening:'.$row->id;
                $exists=Schema::hasTable('ledger_journals') && DB::table('ledger_journals')->where('reference',$reference)->exists();
                if($exists) continue;
                $openings++;
                if($apply){
                    $postings=[['wallet_account_id'=>(int)$row->id,'account_code'=>'legacy_wallet_migration_equity','direction'=>'debit','amount_minor'=>$openingTotal]];
                    if($available>0)$postings[]=['wallet_account_id'=>(int)$row->id,'account_code'=>'user_available_earnings_liability','direction'=>'credit','amount_minor'=>$available];
                    if($pending>0)$postings[]=['wallet_account_id'=>(int)$row->id,'account_code'=>'user_pending_earnings_liability','direction'=>'credit','amount_minor'=>$pending];
                    app(LedgerService::class)->post($reference,'legacy_wallet_opening',strtoupper((string)($row->currency??'NGN')),$postings,null,['migration'=>'2026_monetization_rebuild','wallet_account_id'=>(int)$row->id]);
                }
            }
        });
        return [$count,$openings];
    }

    private function backfillColumn(string $table, string $decimal, string $minor, bool $apply): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $minor)) return 0;
        $count = 0;
        DB::table($table)->whereNull($minor)->orderBy('id')->chunkById(500, function ($rows) use (&$count, $apply, $table, $decimal, $minor): void {
            foreach ($rows as $row) {
                $count++;
                if ($apply) DB::table($table)->where('id', $row->id)->update([$minor => Money::toMinor((string) ($row->{$decimal} ?? '0'))]);
            }
        });
        return $count;
    }

    private function backfillBillingCurrencies(string $table, bool $apply): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'currency')) return 0;
        $count = 0;
        $query = DB::table($table.' as b')
            ->leftJoin('users as u', 'u.id', '=', 'b.user_id')
            ->whereNull('b.currency')
            ->select('b.id', 'u.university_id')
            ->orderBy('b.id');
        $query->chunkById(500, function ($rows) use (&$count, $apply, $table): void {
            foreach ($rows as $row) {
                $count++;
                if (! $apply) continue;
                $currency = strtoupper((string) SettingService::get('currency', 'NGN', $row->university_id ? (int) $row->university_id : null));
                DB::table($table)->where('id', $row->id)->update(['currency' => $currency]);
            }
        }, 'b.id', 'id');
        return $count;
    }

    private function backfillPairTable(string $table, array $pairs, bool $apply): int
    {
        if (! Schema::hasTable($table)) return 0;
        $count = 0;
        DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use (&$count, $apply, $table, $pairs): void {
            foreach ($rows as $row) {
                $updates = [];
                foreach ($pairs as $decimal => $minor) {
                    if (Schema::hasColumn($table, $minor) && $row->{$minor} === null) $updates[$minor] = Money::toMinor((string) ($row->{$decimal} ?? '0'));
                }
                if ($updates === []) continue;
                $count++;
                if ($apply) DB::table($table)->where('id', $row->id)->update($updates);
            }
        });
        return $count;
    }

    private function backfillOrders(bool $apply): int
    {
        return $this->backfillPairTable('commerce_orders', [
            'subtotal' => 'subtotal_minor',
            'discount_amount' => 'discount_amount_minor',
            'tax_amount' => 'tax_amount_minor',
            'total_amount' => 'total_amount_minor',
        ], $apply);
    }

    /** @return array{0:int,1:int} */
    private function migrateSubscriptions(bool $apply): array
    {
        if (! Schema::hasTable('user_subscriptions') || ! Schema::hasTable('subscription_plans')) return [0, 0];

        $entitlements = 0;
        $commercial = 0;
        $booleanMap = [
            'allow_group_submissions' => 'group_submissions',
            'allow_rubrics' => 'rubrics',
            'allow_attendance_tracking' => 'attendance_tracking',
            'allow_document_generation' => 'document_generation',
            'allow_api_access' => 'api_access',
            'allow_white_label' => 'white_label',
        ];

        $subscriptions = DB::table('user_subscriptions as us')
            ->join('subscription_plans as sp', 'sp.id', '=', 'us.plan_id')
            ->leftJoin('users as u', 'u.id', '=', 'us.user_id')
            ->where(function ($q): void {
                $q->where('us.status', 'active')->orWhere('us.is_active', true);
            })
            ->where(function ($q): void {
                $q->whereNull('us.ends_at')->orWhere('us.ends_at', '>', now());
            })
            ->select('us.*', 'u.university_id as user_university_id', 'sp.features', 'sp.max_administrators', ...array_map(fn ($k) => "sp.{$k}", array_keys($booleanMap)))
            ->get();

        foreach ($subscriptions as $subscription) {
            $features = [];
            foreach ($booleanMap as $column => $feature) if ((bool) ($subscription->{$column} ?? false)) $features[] = $feature;
            $stored = json_decode((string) ($subscription->features ?? '[]'), true);
            if (is_array($stored)) foreach ($stored as $feature) if (is_string($feature) && $feature !== '') $features[] = $feature;
            $features = array_values(array_unique($features));

            foreach ($features as $feature) {
                $exists = FeatureEntitlement::query()->where('user_id', $subscription->user_id)->where('feature', $feature)
                    ->where('source_type', 'legacy_subscription')->where('source_id', $subscription->id)->exists();
                if ($exists) continue;
                $entitlements++;
                if ($apply) FeatureEntitlement::query()->create([
                    'user_id' => $subscription->user_id,
                    'feature' => $feature,
                    'access_type' => 'granted',
                    'status' => 'active',
                    'source_type' => 'legacy_subscription',
                    'source_id' => $subscription->id,
                    'starts_at' => $subscription->started_at ?? now(),
                    'expires_at' => $subscription->ends_at,
                    'metadata' => ['migration' => '2026_monetization_rebuild', 'legacy_plan_id' => $subscription->plan_id],
                ]);
            }

            $universityId = $subscription->university_id ?? $subscription->user_university_id ?? null;
            if ($universityId && ! CommercialAccount::query()->where('university_id', $universityId)->exists()) {
                $commercial++;
                if ($apply) CommercialAccount::query()->create([
                    'university_id' => $universityId,
                    'name' => 'Migrated institutional account',
                    'currency' => strtoupper((string) SettingService::get('currency', 'NGN', (int) $universityId)),
                    'prepaid_balance_minor' => 0,
                    'status' => 'active',
                    'metadata' => [
                        'max_administrators' => $subscription->max_administrators,
                        'source' => 'legacy_subscription',
                        'legacy_subscription_id' => $subscription->id,
                    ],
                ]);
            }
        }

        return [$entitlements, $commercial];
    }
}
