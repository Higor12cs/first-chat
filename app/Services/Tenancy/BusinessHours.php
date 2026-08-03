<?php

namespace App\Services\Tenancy;

use App\Models\Card;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

class BusinessHours
{
    public function isOpen(?Tenant $tenant, ?Carbon $moment = null): bool
    {
        if ($this->hoursOf($tenant) === []) {
            return true;
        }

        $moment = $this->localise($tenant, $moment);

        if ($this->exceptionAt($tenant, $moment) !== null) {
            return false;
        }

        return $this->covers($this->intervalsFor($tenant, $moment), $moment->format('H:i'));
    }

    public function holdsOutsideHours(?Tenant $tenant, ?Carbon $moment = null): bool
    {
        if ($tenant === null || $this->hoursOf($tenant) === []) {
            return false;
        }

        return (bool) data_get($tenant->settings, 'after_hours_enabled', false)
            && ! $this->isOpen($tenant, $moment);
    }

    public function cardFor(?Tenant $tenant, ?Carbon $moment = null): ?Card
    {
        if ($tenant === null) {
            return null;
        }

        $exception = $this->exceptionAt($tenant, $this->localise($tenant, $moment));

        $cardId = data_get($exception, 'card_id') ?? data_get($tenant->settings, 'after_hours_card_id');

        return blank($cardId) ? null : Card::query()->active()->find($cardId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function exceptionAt(?Tenant $tenant, Carbon $moment): ?array
    {
        $date = $moment->format('Y-m-d');
        $time = $moment->format('H:i');

        foreach ($this->exceptionsOf($tenant) as $exception) {
            $starts = (string) data_get($exception, 'starts_on');
            $ends = (string) (data_get($exception, 'ends_on') ?: $starts);

            if (blank($starts) || $date < $starts || $date > $ends) {
                continue;
            }

            if (data_get($exception, 'type') === 'holiday') {
                return $exception;
            }

            if ($this->covers([$exception], $time)) {
                return $exception;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $intervals
     */
    private function covers(array $intervals, string $time): bool
    {
        foreach ($intervals as $interval) {
            $start = data_get($interval, 'start');
            $end = data_get($interval, 'end');

            if (blank($start) || blank($end)) {
                continue;
            }

            if ($time >= $start && $time <= $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function intervalsFor(?Tenant $tenant, Carbon $moment): array
    {
        $today = data_get($this->hoursOf($tenant), (string) $moment->dayOfWeek);

        if (! is_array($today)) {
            return [];
        }

        return array_values(array_filter(
            array_key_exists('start', $today) ? [$today] : $today,
            'is_array',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function hoursOf(?Tenant $tenant): array
    {
        $hours = data_get($tenant?->settings, 'business_hours');

        return is_array($hours) ? array_filter($hours, 'is_array') : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exceptionsOf(?Tenant $tenant): array
    {
        $exceptions = data_get($tenant?->settings, 'business_exceptions');

        return is_array($exceptions) ? array_values(array_filter($exceptions, 'is_array')) : [];
    }

    private function localise(?Tenant $tenant, ?Carbon $moment): Carbon
    {
        return ($moment ?? now())->copy()->setTimezone($tenant?->timezone ?? config('app.timezone'));
    }
}
