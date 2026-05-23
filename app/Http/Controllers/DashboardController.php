<?php

namespace App\Http\Controllers;

use App\Http\Resources\CurrencyResource;
use App\Http\Resources\TransactionResource;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\CurrencyConversionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly CurrencyConversionService $currencyConversionService,
    ) {
    }

    /**
     * @group Dashboard
     * @authenticated
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('reportingCurrency');
        $transactions = $user->transactions()->get();
        $wallets = $user->wallets()->with('currency')->get();
        $walletBalance = $this->sumWalletBalances($wallets, $user);
        $recentTransactions = $user->transactions()
            ->with(['category', 'wallet.currency', 'currency', 'reportingCurrency'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(4)
            ->get();

        return response()->json([
            'reporting_currency' => new CurrencyResource($user->reportingCurrency),
            'combined_balance' => $walletBalance,
            'combined_income' => $this->sumConverted($transactions->where('type', 'incoming')),
            'combined_expense' => $this->normalizeNumber(abs((float) $this->sumConverted($transactions->where('type', 'outgoing')))),
            'daily_spending' => $this->dailySpending($user, $walletBalance, $transactions),
            'totals_by_currency' => $this->totalsByCurrency($transactions),
            'recent_transactions' => TransactionResource::collection($recentTransactions),
        ]);
    }

    /**
     * @group Analytics
     * @authenticated
     */
    public function analytics(Request $request): JsonResponse
    {
        $user = $request->user()->load('reportingCurrency');
        $transactions = $this->filteredTransactions($request)->get();

        return response()->json([
            'reporting_currency' => new CurrencyResource($user->reportingCurrency),
            'filters' => [
                'from' => $request->query('from'),
                'to' => $request->query('to'),
                'category_id' => $request->query('category_id'),
                'type' => $request->query('type'),
                'currency_id' => $request->query('currency_id'),
            ],
            'combined_totals' => [
                'balance' => $this->sumConverted($transactions),
                'income' => $this->sumConverted($transactions->where('type', 'incoming')),
                'expense' => $this->normalizeNumber(abs((float) $this->sumConverted($transactions->where('type', 'outgoing')))),
            ],
            'totals_by_currency' => $this->totalsByCurrency($transactions),
            'totals_by_category' => $transactions
                ->loadMissing('category')
                ->groupBy('category_id')
                ->map(fn ($group) => [
                    'category_id' => $group->first()->category_id,
                    'category_name' => $group->first()->category?->name,
                    'balance' => $this->sumConverted($group),
                    'income' => $this->sumConverted($group->where('type', 'incoming')),
                    'expense' => $this->normalizeNumber(abs((float) $this->sumConverted($group->where('type', 'outgoing')))),
                ])
                ->values(),
            'monthly_trend' => $transactions
                ->groupBy(fn ($transaction) => $transaction->occurred_on->format('Y-m'))
                ->map(fn ($group, $month) => [
                    'month' => $month,
                    'balance' => $this->sumConverted($group),
                    'income' => $this->sumConverted($group->where('type', 'incoming')),
                    'expense' => $this->normalizeNumber(abs((float) $this->sumConverted($group->where('type', 'outgoing')))),
                ])
                ->values(),
        ]);
    }

    private function filteredTransactions(Request $request)
    {
        $query = $request->user()->transactions()->with(['category', 'wallet.currency', 'currency']);

        if ($request->filled('from')) {
            $query->where('occurred_on', '>=', Carbon::parse((string) $request->query('from'))->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('occurred_on', '<=', Carbon::parse((string) $request->query('to'))->endOfDay());
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->query('category_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', (string) $request->query('type'));
        }

        if ($request->filled('currency_id')) {
            $query->where('currency_id', (int) $request->query('currency_id'));
        }

        return $query->orderBy('occurred_on')->orderBy('id');
    }

    private function sumConverted(iterable $transactions): string
    {
        $total = 0.0;

        foreach ($transactions as $transaction) {
            $total += (float) $transaction->converted_amount;
        }

        return $this->normalizeNumber($total);
    }

    /**
     * @param \Illuminate\Support\Collection<int, Wallet> $wallets
     */
    private function sumWalletBalances($wallets, $user): string
    {
        $total = 0.0;

        foreach ($wallets as $wallet) {
            if ($wallet->currency_id === $user->reporting_currency_id) {
                $total += (float) $wallet->balance;

                continue;
            }

            $snapshot = $this->currencyConversionService->snapshot(
                $user,
                $wallet->currency_id,
                'incoming',
                (string) $wallet->balance,
                Carbon::now(),
            );
            $total += (float) $snapshot['converted_amount'];
        }

        return $this->normalizeNumber($total);
    }

    /**
     * @param \Illuminate\Support\Collection<int, Transaction> $transactions
     * @return array<string, string|int>
     */
    private function dailySpending($user, string $walletBalance, $transactions): array
    {
        $today = Carbon::today();
        $daysUntilMonthEnd = max(1, $today->daysInMonth - $today->day);
        $spentToday = abs((float) $this->sumConverted(
            $transactions
                ->where('type', 'outgoing')
                ->filter(fn (Transaction $transaction) => $transaction->occurred_on->isSameDay($today))
        ));
        $startingBalanceToday = (float) $walletBalance + $spentToday;
        $budgetToday = $startingBalanceToday / $daysUntilMonthEnd;
        $secondaryBudgetToday = $this->secondaryDailyBudget($user, $budgetToday, $today);

        return [
            'days_until_month_end' => $daysUntilMonthEnd,
            'budget_today' => $this->normalizeNumber($budgetToday),
            'budget_today_secondary' => $secondaryBudgetToday,
            'spent_today' => $this->normalizeNumber($spentToday),
            'remaining_today' => $this->normalizeNumber($budgetToday - $spentToday),
        ];
    }

    private function secondaryDailyBudget($user, float $budgetToday, Carbon $today): ?array
    {
        if ($user->reporting_currency_id === null) {
            return null;
        }

        $currency = Currency::query()
            ->active()
            ->whereKeyNot($user->reporting_currency_id)
            ->orderBy('code')
            ->first();

        if ($currency === null) {
            return null;
        }

        $rate = $this->currencyConversionService->resolveRate(
            $user->id,
            $user->reporting_currency_id,
            $currency->id,
            $today,
        );

        if ($rate === null) {
            return null;
        }

        return [
            'amount' => $this->normalizeNumber($budgetToday * (float) $rate),
            'currency' => new CurrencyResource($currency),
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, Transaction> $transactions
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function totalsByCurrency($transactions)
    {
        return $transactions
            ->loadMissing('currency')
            ->groupBy('currency_id')
            ->map(function ($group) {
                $income = $group
                    ->where('type', 'incoming')
                    ->sum(fn ($transaction) => (float) $transaction->amount);

                $expense = $group
                    ->where('type', 'outgoing')
                    ->sum(fn ($transaction) => (float) $transaction->amount);

                return [
                    'currency_id' => $group->first()->currency_id,
                    'currency_code' => $group->first()->currency?->code,
                    'currency' => new CurrencyResource($group->first()->currency),
                    'balance' => $this->normalizeNumber($income - $expense),
                    'income' => $this->normalizeNumber($income),
                    'expense' => $this->normalizeNumber($expense),
                ];
            })
            ->sortBy('currency_code')
            ->map(function (array $item) {
                unset($item['currency_code']);

                return $item;
            })
            ->values();
    }

    private function normalizeNumber(float $value): string
    {
        return number_format($value, 4, '.', '');
    }
}
