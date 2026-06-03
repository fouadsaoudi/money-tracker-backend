<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Transaction;
use App\Models\User;
use App\Services\UserSetupService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MoneyTrackerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_currencies_and_profile_preferences(): void
    {
        $user = $this->signInFinancialUser();

        $this->getJson('/api/currencies')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'LBP');

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.reporting_currency.code', 'USD');
    }

    public function test_user_has_default_usd_wallet_and_can_create_wallet_for_another_currency(): void
    {
        $user = $this->signInFinancialUser();
        $lbp = Currency::query()->where('code', 'LBP')->firstOrFail();

        $this->getJson('/api/wallets')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'USD Wallet')
            ->assertJsonPath('data.0.currency.code', 'USD')
            ->assertJsonPath('data.0.is_default', true);

        $this->postJson('/api/wallets', [
            'currency_id' => $lbp->id,
            'name' => 'Cash LBP',
            'balance' => '150000',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Cash LBP')
            ->assertJsonPath('data.currency.code', 'LBP')
            ->assertJsonPath('data.balance', '150000.0000')
            ->assertJsonPath('data.is_default', false);

        $this->getJson('/api/wallets')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_cannot_create_two_wallets_for_the_same_currency(): void
    {
        $this->signInFinancialUser();
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();

        $this->postJson('/api/wallets', [
            'currency_id' => $usd->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency_id']);
    }

    public function test_wallet_balance_update_creates_adjustment_transaction(): void
    {
        Carbon::setTestNow('2026-05-20 12:40:00');

        $user = $this->signInFinancialUser();
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();
        $wallet = $user->wallets()->where('currency_id', $usd->id)->firstOrFail();

        $this->patchJson("/api/wallets/{$wallet->id}", [
            'name' => 'Cash',
            'balance' => '125',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Cash')
            ->assertJsonPath('data.balance', '125.0000');

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Wallet adjustment',
        ]);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'incoming',
            'amount' => '125.0000',
            'note' => 'Wallet adjustment',
            'converted_amount' => '125.0000',
        ]);

        $this->patchJson("/api/wallets/{$wallet->id}", [
            'balance' => '100',
        ])->assertOk();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'outgoing',
            'amount' => '25.0000',
            'note' => 'Wallet adjustment',
            'converted_amount' => '-25.0000',
        ]);

        Carbon::setTestNow();
    }

    public function test_user_can_create_goal_and_contribution_creates_linked_transaction(): void
    {
        Storage::fake('public');

        $user = $this->signInFinancialUser();
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();
        $wallet = $user->wallets()->where('currency_id', $usd->id)->firstOrFail();
        $wallet->forceFill(['balance' => '5000.0000'])->save();

        $goalResponse = $this->postJson('/api/goals', [
            'name' => 'House payments',
            'currency_id' => $usd->id,
            'target_amount' => '94000',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'House payments')
            ->assertJsonPath('data.target_amount', '94000.0000')
            ->assertJsonPath('data.current_amount', '0.0000');

        $goalId = $goalResponse->json('data.id');

        $this->post('/api/goals/'.$goalId.'/contributions', [
            'wallet_id' => $wallet->id,
            'amount' => '1200',
            'occurred_on' => '2026-05-02 09:00:00',
            'note' => 'First payment',
            'invoice_images' => [
                UploadedFile::fake()->image('goal-invoice.jpg'),
                UploadedFile::fake()->image('goal-invoice-2.jpg'),
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.current_amount', '1200.0000')
            ->assertJsonPath('data.remaining_amount', '92800.0000')
            ->assertJsonCount(2, 'data.recent_contributions.0.transaction.invoice_images');

        $this->assertDatabaseHas('wallets', [
            'id' => $wallet->id,
            'balance' => '3800.0000',
        ]);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'outgoing',
            'amount' => '1200.0000',
        ]);
        $transaction = Transaction::query()
            ->where('user_id', $user->id)
            ->where('wallet_id', $wallet->id)
            ->where('type', 'outgoing')
            ->where('amount', '1200.0000')
            ->firstOrFail();
        $this->assertCount(2, $transaction->invoiceImages()->get());
        $this->assertDatabaseHas('goal_contributions', [
            'goal_id' => $goalId,
            'amount' => '1200.0000',
        ]);
    }

    public function test_user_can_convert_money_between_wallets(): void
    {
        $user = $this->signInFinancialUser();
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();
        $lbp = Currency::query()->where('code', 'LBP')->firstOrFail();
        $usdWallet = $user->wallets()->where('currency_id', $usd->id)->firstOrFail();
        $usdWallet->forceFill(['balance' => '500.0000'])->save();
        $lbpWallet = $user->wallets()->create([
            'currency_id' => $lbp->id,
            'name' => 'Cash LBP',
            'balance' => '0.0000',
        ]);

        $this->postJson('/api/wallet-conversions', [
            'source_wallet_id' => $usdWallet->id,
            'destination_wallet_id' => $lbpWallet->id,
            'source_amount' => '100',
            'destination_amount' => '8900000',
            'occurred_on' => '2026-05-18 10:00:00',
            'note' => 'Local exchange shop',
        ])
            ->assertCreated()
            ->assertJsonPath('data.source_transaction.type', 'outgoing')
            ->assertJsonPath('data.destination_transaction.type', 'incoming');

        $this->assertDatabaseHas('wallets', [
            'id' => $usdWallet->id,
            'balance' => '400.0000',
        ]);
        $this->assertDatabaseHas('wallets', [
            'id' => $lbpWallet->id,
            'balance' => '8900000.0000',
        ]);
        $this->assertDatabaseHas('wallet_conversions', [
            'source_amount' => '100.0000',
            'destination_amount' => '8900000.0000',
        ]);
    }

    public function test_same_category_can_store_transactions_in_multiple_currencies_and_dashboard_combines_totals(): void
    {
        $user = $this->signInFinancialUser();
        $category = $user->categories()->where('name', 'Food')->firstOrFail();
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();
        $lbp = Currency::query()->where('code', 'LBP')->firstOrFail();
        $usdWallet = $user->wallets()->where('currency_id', $usd->id)->firstOrFail();
        $lbpWallet = $user->wallets()->create([
            'currency_id' => $lbp->id,
            'name' => 'Cash LBP',
            'balance' => '100000.0000',
        ]);

        ExchangeRate::query()->create([
            'user_id' => $user->id,
            'from_currency_id' => $usd->id,
            'to_currency_id' => $lbp->id,
            'rate' => '100000.00000000',
            'effective_at' => '2026-05-01 00:00:00',
        ]);

        $this->postJson('/api/transactions', [
            'category_id' => $category->id,
            'wallet_id' => $usdWallet->id,
            'type' => 'incoming',
            'amount' => '100',
            'note' => 'Salary part',
            'occurred_on' => '2026-05-02 09:00:00',
        ])->assertCreated();

        $this->postJson('/api/transactions', [
            'category_id' => $category->id,
            'wallet_id' => $lbpWallet->id,
            'type' => 'outgoing',
            'amount' => '50000',
            'note' => 'Lunch',
            'occurred_on' => '2026-05-02 11:00:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.exchange_rate_snapshot', '0.00001000')
            ->assertJsonPath('data.converted_amount', '-0.5000');

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('combined_balance', '100.5000')
            ->assertJsonPath('combined_income', '100.0000')
            ->assertJsonPath('combined_expense', '0.5000')
            ->assertJsonCount(2, 'totals_by_currency');

        $this->assertDatabaseHas('wallets', [
            'id' => $usdWallet->id,
            'balance' => '100.0000',
        ]);
        $this->assertDatabaseHas('wallets', [
            'id' => $lbpWallet->id,
            'balance' => '50000.0000',
        ]);
    }

    public function test_dashboard_returns_daily_spending_budget(): void
    {
        Carbon::setTestNow('2026-05-20 12:00:00');

        $user = $this->signInFinancialUser();
        $category = $user->categories()->where('name', 'Food')->firstOrFail();
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();
        $lbp = Currency::query()->where('code', 'LBP')->firstOrFail();
        $user->wallets()->where('currency_id', $usd->id)->firstOrFail()
            ->forceFill(['balance' => '500.0000'])
            ->save();

        ExchangeRate::query()->create([
            'user_id' => $user->id,
            'from_currency_id' => $usd->id,
            'to_currency_id' => $lbp->id,
            'rate' => '100000.00000000',
            'effective_at' => '2026-05-01 00:00:00',
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'currency_id' => $usd->id,
            'type' => 'incoming',
            'amount' => '530.0000',
            'occurred_on' => '2026-05-01 08:00:00',
            'reporting_currency_id' => $usd->id,
            'exchange_rate_snapshot' => '1.00000000',
            'converted_amount' => '530.0000',
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'currency_id' => $usd->id,
            'type' => 'outgoing',
            'amount' => '30.0000',
            'occurred_on' => '2026-05-20 09:00:00',
            'reporting_currency_id' => $usd->id,
            'exchange_rate_snapshot' => '1.00000000',
            'converted_amount' => '-30.0000',
        ]);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('combined_balance', '500.0000')
            ->assertJsonPath('daily_spending.days_until_month_end', 12)
            ->assertJsonPath('daily_spending.budget_today', '44.1667')
            ->assertJsonPath('daily_spending.budget_today_secondary.amount', '4416666.6667')
            ->assertJsonPath('daily_spending.budget_today_secondary.currency.code', 'LBP')
            ->assertJsonPath('daily_spending.spent_today', '30.0000')
            ->assertJsonPath('daily_spending.remaining_today', '14.1667');

        Carbon::setTestNow();
    }

    public function test_dashboard_balance_uses_wallet_balances_when_there_are_no_transactions(): void
    {
        Carbon::setTestNow('2026-05-20 12:00:00');

        $user = $this->signInFinancialUser();
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();
        $user->wallets()->where('currency_id', $usd->id)->firstOrFail()
            ->forceFill(['balance' => '2400.0000'])
            ->save();

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('combined_balance', '2400.0000')
            ->assertJsonPath('combined_income', '0.0000')
            ->assertJsonPath('combined_expense', '0.0000')
            ->assertJsonPath('daily_spending.days_until_month_end', 12)
            ->assertJsonPath('daily_spending.budget_today', '200.0000')
            ->assertJsonPath('daily_spending.spent_today', '0.0000')
            ->assertJsonPath('daily_spending.remaining_today', '200.0000');

        Carbon::setTestNow();
    }

    public function test_dashboard_rolls_daily_spending_over_at_beirut_midnight(): void
    {
        Carbon::setTestNow('2026-06-01 21:30:00 UTC');

        $user = $this->signInFinancialUser();
        $category = $user->categories()->where('name', 'Food')->firstOrFail();
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();
        $user->wallets()->where('currency_id', $usd->id)->firstOrFail()
            ->forceFill(['balance' => '290.0000'])
            ->save();

        Transaction::query()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'currency_id' => $usd->id,
            'type' => 'outgoing',
            'amount' => '10.0000',
            'occurred_on' => '2026-06-01 20:00:00',
            'reporting_currency_id' => $usd->id,
            'exchange_rate_snapshot' => '1.00000000',
            'converted_amount' => '-10.0000',
        ]);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('daily_spending.as_of_date', '2026-06-02')
            ->assertJsonPath('daily_spending.days_until_month_end', 29)
            ->assertJsonPath('daily_spending.budget_today', '10.0000')
            ->assertJsonPath('daily_spending.spent_today', '0.0000')
            ->assertJsonPath('daily_spending.remaining_today', '10.0000');

        Carbon::setTestNow();
    }

    public function test_dashboard_ignores_missing_rate_for_zero_balance_wallets(): void
    {
        $user = $this->signInFinancialUser();
        $lbp = Currency::query()->where('code', 'LBP')->firstOrFail();

        $user->wallets()->create([
            'currency_id' => $lbp->id,
            'name' => 'Cash LBP',
            'balance' => '0.0000',
        ]);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('combined_balance', '0.0000');
    }

    public function test_dashboard_ignores_missing_rate_for_non_reporting_wallet_balances(): void
    {
        $user = $this->signInFinancialUser();
        $lbp = Currency::query()->where('code', 'LBP')->firstOrFail();

        $user->wallets()->create([
            'currency_id' => $lbp->id,
            'name' => 'Cash LBP',
            'balance' => '150000.0000',
        ]);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('combined_balance', '0.0000');
    }

    public function test_transaction_can_store_and_remove_invoice_images(): void
    {
        Storage::fake('public');
        Carbon::setTestNow('2026-05-19 00:00:00');

        $user = $this->signInFinancialUser();
        $category = $user->categories()->where('name', 'Food')->firstOrFail();
        $wallet = $user->wallets()->firstOrFail();

        $response = $this->post('/api/transactions', [
            'category_id' => $category->id,
            'wallet_id' => $wallet->id,
            'type' => 'outgoing',
            'amount' => '12.50',
            'occurred_on' => '2026-05-18 12:00:00',
            'invoice_images' => [
                UploadedFile::fake()->image('invoice.jpg'),
                UploadedFile::fake()->image('second-invoice.jpg'),
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '12.5000')
            ->assertJsonPath('data.invoice_image_url', fn (?string $url) => str_contains((string) $url, '/storage/transaction-invoices/'.$user->id.'/may_19_2026_12_00_00_am.jpg'))
            ->assertJsonCount(2, 'data.invoice_image_urls');

        $transaction = Transaction::query()->findOrFail($response->json('data.id'));
        $invoiceImages = $transaction->invoiceImages()->orderBy('id')->get();
        $invoiceImagePaths = $invoiceImages->pluck('path')->all();
        $this->assertCount(2, $invoiceImagePaths);
        $this->assertStringEndsWith('/may_19_2026_12_00_00_am.jpg', $invoiceImagePaths[0]);
        $this->assertStringEndsWith('/may_19_2026_12_00_00_am_2.jpg', $invoiceImagePaths[1]);
        Storage::disk('public')->assertExists($invoiceImagePaths[0]);
        Storage::disk('public')->assertExists($invoiceImagePaths[1]);

        $this->patchJson('/api/transactions/'.$transaction->id, [
            'remove_invoice_image_ids' => [$invoiceImages[1]->id],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.invoice_images')
            ->assertJsonCount(1, 'data.invoice_image_urls');

        Storage::disk('public')->assertExists($invoiceImagePaths[0]);
        Storage::disk('public')->assertMissing($invoiceImagePaths[1]);

        $this->patchJson('/api/transactions/'.$transaction->id, [
            'remove_invoice_images' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.invoice_image_url', null)
            ->assertJsonCount(0, 'data.invoice_image_urls');

        $transaction->refresh();
        $this->assertCount(0, $transaction->invoiceImages()->get());
        Storage::disk('public')->assertMissing($invoiceImagePaths[0]);
        Carbon::setTestNow();
    }

    public function test_transaction_creation_fails_when_rate_is_missing(): void
    {
        $user = $this->signInFinancialUser();
        $category = $user->categories()->where('name', 'Bills')->firstOrFail();
        $lbp = Currency::query()->where('code', 'LBP')->firstOrFail();
        $lbpWallet = $user->wallets()->create([
            'currency_id' => $lbp->id,
            'name' => 'Cash LBP',
            'balance' => '0.0000',
        ]);

        $this->postJson('/api/transactions', [
            'category_id' => $category->id,
            'wallet_id' => $lbpWallet->id,
            'type' => 'outgoing',
            'amount' => '120000',
            'occurred_on' => '2026-05-02 11:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency_id']);
    }

    public function test_historical_transactions_keep_their_original_conversion_snapshot(): void
    {
        $user = $this->signInFinancialUser();
        $category = $user->categories()->where('name', 'Shopping')->firstOrFail();
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();
        $lbp = Currency::query()->where('code', 'LBP')->firstOrFail();
        $lbpWallet = $user->wallets()->create([
            'currency_id' => $lbp->id,
            'name' => 'Cash LBP',
            'balance' => '200000.0000',
        ]);

        ExchangeRate::query()->create([
            'user_id' => $user->id,
            'from_currency_id' => $usd->id,
            'to_currency_id' => $lbp->id,
            'rate' => '100000.00000000',
            'effective_at' => '2026-05-01 00:00:00',
        ]);

        $firstResponse = $this->postJson('/api/transactions', [
            'category_id' => $category->id,
            'wallet_id' => $lbpWallet->id,
            'type' => 'outgoing',
            'amount' => '50000',
            'occurred_on' => '2026-05-02 11:00:00',
        ])->assertCreated();

        ExchangeRate::query()->create([
            'user_id' => $user->id,
            'from_currency_id' => $usd->id,
            'to_currency_id' => $lbp->id,
            'rate' => '50000.00000000',
            'effective_at' => '2026-05-03 00:00:00',
        ]);

        $secondResponse = $this->postJson('/api/transactions', [
            'category_id' => $category->id,
            'wallet_id' => $lbpWallet->id,
            'type' => 'outgoing',
            'amount' => '50000',
            'occurred_on' => '2026-05-04 11:00:00',
        ])->assertCreated();

        $this->assertSame('-0.5000', $firstResponse->json('data.converted_amount'));
        $this->assertSame('-1.0000', $secondResponse->json('data.converted_amount'));

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('combined_balance', '2.0000')
            ->assertJsonPath('combined_expense', '1.5000');
    }

    public function test_deleting_category_with_transactions_archives_it_and_blocks_new_transactions(): void
    {
        $user = $this->signInFinancialUser();
        $category = $user->categories()->where('name', 'Transport')->firstOrFail();
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();
        $usdWallet = $user->wallets()->where('currency_id', $usd->id)->firstOrFail();

        Transaction::query()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'currency_id' => $usd->id,
            'type' => 'outgoing',
            'amount' => '10.0000',
            'note' => 'Taxi',
            'occurred_on' => '2026-05-02 08:00:00',
            'reporting_currency_id' => $usd->id,
            'exchange_rate_snapshot' => '1.00000000',
            'converted_amount' => '-10.0000',
        ]);

        $this->deleteJson('/api/categories/'.$category->id)
            ->assertOk()
            ->assertJsonPath('message', 'Category archived because it has existing transactions.');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'is_archived' => true,
        ]);

        $this->postJson('/api/transactions', [
            'category_id' => $category->id,
            'wallet_id' => $usdWallet->id,
            'type' => 'outgoing',
            'amount' => '15',
            'occurred_on' => '2026-05-03 08:00:00',
        ])->assertUnprocessable()->assertJsonValidationErrors(['category_id']);
    }

    public function test_soft_deleted_transactions_are_excluded_from_dashboard_and_analytics(): void
    {
        $user = $this->signInFinancialUser();
        $category = $user->categories()->where('name', 'Savings')->firstOrFail();
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'currency_id' => $usd->id,
            'type' => 'incoming',
            'amount' => '25.0000',
            'note' => 'Saved cash',
            'occurred_on' => '2026-05-02 08:00:00',
            'reporting_currency_id' => $usd->id,
            'exchange_rate_snapshot' => '1.00000000',
            'converted_amount' => '25.0000',
        ]);

        $this->deleteJson('/api/transactions/'.$transaction->id)->assertOk();

        $dashboard = $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('combined_balance', '0.0000');

        $this->assertCount(0, $dashboard->json('recent_transactions'));

        $this->getJson('/api/analytics')
            ->assertOk()
            ->assertJsonPath('combined_totals.balance', '0.0000')
            ->assertJsonCount(0, 'totals_by_category');
    }

    public function test_analytics_filters_return_category_and_monthly_breakdowns(): void
    {
        $user = $this->signInFinancialUser();
        $food = $user->categories()->where('name', 'Food')->firstOrFail();
        $salary = $user->categories()->where('name', 'Salary')->firstOrFail();
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();

        Transaction::query()->create([
            'user_id' => $user->id,
            'category_id' => $salary->id,
            'currency_id' => $usd->id,
            'type' => 'incoming',
            'amount' => '200.0000',
            'note' => 'Main salary',
            'occurred_on' => '2026-05-01 08:00:00',
            'reporting_currency_id' => $usd->id,
            'exchange_rate_snapshot' => '1.00000000',
            'converted_amount' => '200.0000',
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'category_id' => $food->id,
            'currency_id' => $usd->id,
            'type' => 'outgoing',
            'amount' => '30.0000',
            'note' => 'Groceries',
            'occurred_on' => '2026-05-03 08:00:00',
            'reporting_currency_id' => $usd->id,
            'exchange_rate_snapshot' => '1.00000000',
            'converted_amount' => '-30.0000',
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'category_id' => $food->id,
            'currency_id' => $usd->id,
            'type' => 'outgoing',
            'amount' => '20.0000',
            'note' => 'Dinner',
            'occurred_on' => '2026-06-01 08:00:00',
            'reporting_currency_id' => $usd->id,
            'exchange_rate_snapshot' => '1.00000000',
            'converted_amount' => '-20.0000',
        ]);

        $this->getJson('/api/analytics?category_id='.$food->id.'&from=2026-05-01&to=2026-05-31')
            ->assertOk()
            ->assertJsonPath('combined_totals.balance', '-30.0000')
            ->assertJsonCount(1, 'totals_by_category')
            ->assertJsonPath('totals_by_category.0.category_name', 'Food')
            ->assertJsonPath('monthly_trend.0.month', '2026-05');
    }

    public function test_user_cannot_access_another_users_transaction(): void
    {
        $owner = $this->createFinancialUser();
        $otherUser = $this->createFinancialUser('second@example.com');
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();
        $category = $owner->categories()->where('name', 'Other')->firstOrFail();

        $transaction = Transaction::query()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'currency_id' => $usd->id,
            'type' => 'incoming',
            'amount' => '15.0000',
            'note' => 'Gift',
            'occurred_on' => '2026-05-02 08:00:00',
            'reporting_currency_id' => $usd->id,
            'exchange_rate_snapshot' => '1.00000000',
            'converted_amount' => '15.0000',
        ]);

        Sanctum::actingAs($otherUser);

        $this->getJson('/api/transactions/'.$transaction->id)->assertNotFound();
    }

    public function test_transactions_list_is_paginated(): void
    {
        $user = $this->signInFinancialUser();
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();
        $category = $user->categories()->where('name', 'Other')->firstOrFail();

        foreach (range(1, 3) as $index) {
            Transaction::query()->create([
                'user_id' => $user->id,
                'category_id' => $category->id,
                'currency_id' => $usd->id,
                'type' => 'incoming',
                'amount' => '15.0000',
                'note' => 'Gift '.$index,
                'occurred_on' => '2026-05-0'.$index.' 08:00:00',
                'reporting_currency_id' => $usd->id,
                'exchange_rate_snapshot' => '1.00000000',
                'converted_amount' => '15.0000',
            ]);
        }

        $this->getJson('/api/transactions?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 3);

        $this->getJson('/api/transactions?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 3);

        $this->getJson('/api/transactions?from=2026-05-02&to=2026-05-02')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.note', 'Gift 2')
            ->assertJsonPath('meta.total', 1);

        $giftTwo = Transaction::query()->where('note', 'Gift 2')->firstOrFail();

        $this->getJson('/api/transactions?search=Gift+2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $giftTwo->id);

        $this->getJson('/api/transactions?search=Other')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/transactions?search='.$giftTwo->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $giftTwo->id);
    }

    private function signInFinancialUser(?string $email = null): User
    {
        $user = $this->createFinancialUser($email);
        Sanctum::actingAs($user);

        return $user;
    }

    private function createFinancialUser(?string $email = null): User
    {
        $user = User::factory()->create([
            'email' => $email ?? 'finance@example.com',
            'reporting_currency_id' => Currency::query()->where('code', 'USD')->value('id'),
        ]);

        app(UserSetupService::class)->initialize($user);

        return $user->refresh();
    }
}
