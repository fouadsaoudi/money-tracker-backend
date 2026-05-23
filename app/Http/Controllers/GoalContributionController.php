<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGoalContributionRequest;
use App\Http\Resources\GoalResource;
use App\Models\Category;
use App\Models\Goal;
use App\Models\Wallet;
use App\Services\CurrencyConversionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GoalContributionController extends Controller
{
    public function __construct(
        private readonly CurrencyConversionService $currencyConversionService,
    ) {
    }

    /**
     * @group Goals
     * @authenticated
     */
    public function store(StoreGoalContributionRequest $request, Goal $goal): JsonResponse
    {
        abort_unless($goal->user_id === $request->user()->id, 404);

        $payload = $request->validated();
        $invoiceImages = $this->invoiceImageFiles($request);

        $goal = DB::transaction(function () use ($request, $goal, $payload, $invoiceImages): Goal {
            $wallet = Wallet::query()
                ->where('user_id', $request->user()->id)
                ->whereKey((int) $payload['wallet_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($wallet->currency_id !== $goal->currency_id) {
                throw ValidationException::withMessages([
                    'wallet_id' => ['Choose a wallet that uses the same currency as this goal.'],
                ]);
            }

            $category = Category::query()->firstOrCreate(
                ['user_id' => $request->user()->id, 'name' => 'Goals'],
                ['color' => '#2563eb', 'icon' => 'flag', 'is_archived' => false]
            );

            if ($category->is_archived) {
                $category->forceFill(['is_archived' => false])->save();
            }

            $occurredOn = Carbon::parse((string) $payload['occurred_on']);
            $note = trim((string) ($payload['note'] ?? ''));
            $transactionNote = $note === ''
                ? 'Goal contribution: '.$goal->name
                : 'Goal contribution: '.$goal->name.' - '.$note;
            $snapshot = $this->currencyConversionService->snapshot(
                $request->user()->loadMissing('reportingCurrency'),
                $wallet->currency_id,
                'outgoing',
                (string) $payload['amount'],
                $occurredOn,
            );

            $transaction = $request->user()->transactions()->create([
                'category_id' => $category->id,
                'wallet_id' => $wallet->id,
                'currency_id' => $wallet->currency_id,
                'type' => 'outgoing',
                'amount' => $payload['amount'],
                'note' => $transactionNote,
                'occurred_on' => $occurredOn,
                'reporting_currency_id' => $request->user()->reporting_currency_id,
                'exchange_rate_snapshot' => $snapshot['rate'],
                'converted_amount' => $snapshot['converted_amount'],
            ]);

            $this->storeInvoiceImages($invoiceImages, $transaction);

            $wallet->forceFill([
                'balance' => bcadd((string) $wallet->balance, bcmul((string) $payload['amount'], '-1', 4), 4),
            ])->save();

            $goal->contributions()->create([
                'user_id' => $request->user()->id,
                'transaction_id' => $transaction->id,
                'amount' => $payload['amount'],
                'occurred_on' => $occurredOn,
            ]);

            $this->syncCompletion($goal);

            return $goal->refresh();
        });

        return (new GoalResource(
            $goal->load([
                'currency',
                'contributions' => fn ($query) => $query
                    ->with(['transaction.category', 'transaction.wallet.currency', 'transaction.currency', 'transaction.reportingCurrency'])
                    ->with(['transaction.invoiceImages'])
                    ->orderByDesc('occurred_on')
                    ->orderByDesc('id'),
            ])
                ->loadSum('contributions as current_amount', 'amount')
                ->loadCount('contributions')
        ))->response()->setStatusCode(201);
    }

    private function syncCompletion(Goal $goal): void
    {
        $current = (string) $goal->contributions()->sum('amount');

        $goal->forceFill([
            'completed_at' => bccomp($current, (string) $goal->target_amount, 4) >= 0
                ? now()
                : null,
        ])->save();
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function invoiceImageFiles(StoreGoalContributionRequest $request): array
    {
        $files = [];
        $invoiceImages = $request->file('invoice_images', []);

        if ($invoiceImages instanceof UploadedFile) {
            $files[] = $invoiceImages;
        } elseif (is_array($invoiceImages)) {
            foreach ($invoiceImages as $image) {
                if ($image instanceof UploadedFile) {
                    $files[] = $image;
                }
            }
        }

        return $files;
    }

    /**
     * @param array<int, UploadedFile> $images
     */
    private function storeInvoiceImages(array $images, $transaction): void
    {
        foreach ($images as $image) {
            $transaction->invoiceImages()->create([
                'path' => $this->storeInvoiceImage($image, $transaction),
            ]);
        }
    }

    private function storeInvoiceImage(UploadedFile $image, $transaction): string
    {
        $directory = "transaction-invoices/{$transaction->user_id}";
        $extension = $image->extension();
        $timestamp = strtolower(now()->format('F_j_Y_h_i_s_a'));
        $filename = "{$timestamp}.{$extension}";
        $counter = 2;

        while (Storage::disk('public')->exists("{$directory}/{$filename}")) {
            $filename = "{$timestamp}_{$counter}.{$extension}";
            $counter++;
        }

        return $image->storeAs($directory, $filename, 'public');
    }
}
