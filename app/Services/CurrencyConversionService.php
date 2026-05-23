<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class CurrencyConversionService
{
    /**
     * @return array{rate:string,converted_amount:string}
     */
    public function snapshot(User $user, int $currencyId, string $type, string $amount, CarbonInterface $occurredOn): array
    {
        $reportingCurrencyId = $user->reporting_currency_id;

        if ($reportingCurrencyId === null) {
            throw ValidationException::withMessages([
                'reporting_currency_id' => ['The user does not have a reporting currency configured.'],
            ]);
        }

        $signedAmount = $type === 'outgoing'
            ? bcmul($amount, '-1', 8)
            : bcadd($amount, '0', 8);

        if ($currencyId === $reportingCurrencyId) {
            return [
                'rate' => '1.00000000',
                'converted_amount' => $this->normalizeDecimal($signedAmount, 4),
            ];
        }

        $rate = $this->resolveRate($user->id, $currencyId, $reportingCurrencyId, $occurredOn);

        if ($rate === null) {
            $currency = Currency::query()->find($currencyId);
            $reportingCurrency = Currency::query()->find($reportingCurrencyId);

            throw ValidationException::withMessages([
                'currency_id' => [sprintf(
                    'No exchange rate found to convert %s to %s for the selected date.',
                    $currency?->code ?? 'the source currency',
                    $reportingCurrency?->code ?? 'the reporting currency',
                )],
            ]);
        }

        return [
            'rate' => $rate,
            'converted_amount' => $this->normalizeDecimal(bcmul($signedAmount, $rate, 8), 4),
        ];
    }

    public function resolveRate(int $userId, int $fromCurrencyId, int $toCurrencyId, CarbonInterface $occurredOn): ?string
    {
        $directRate = ExchangeRate::query()
            ->where('user_id', $userId)
            ->where('from_currency_id', $fromCurrencyId)
            ->where('to_currency_id', $toCurrencyId)
            ->where('effective_at', '<=', $occurredOn)
            ->latest('effective_at')
            ->value('rate');

        if ($directRate !== null) {
            return $this->normalizeDecimal((string) $directRate, 8);
        }

        $inverseRate = ExchangeRate::query()
            ->where('user_id', $userId)
            ->where('from_currency_id', $toCurrencyId)
            ->where('to_currency_id', $fromCurrencyId)
            ->where('effective_at', '<=', $occurredOn)
            ->latest('effective_at')
            ->value('rate');

        if ($inverseRate === null || bccomp((string) $inverseRate, '0', 8) === 0) {
            return null;
        }

        return $this->normalizeDecimal(bcdiv('1', (string) $inverseRate, 8), 8);
    }

    private function normalizeDecimal(string $value, int $scale): string
    {
        return bcadd($value, '0', $scale);
    }
}
