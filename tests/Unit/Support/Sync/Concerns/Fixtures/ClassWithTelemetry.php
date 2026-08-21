<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Tests\Unit\Support\Sync\Concerns\Fixtures;

use Typdy\StarterKit\Laravel\Support\Sync\Concerns\HasTelemetry;

use function array_key_exists;

final class ClassWithTelemetry
{
    use HasTelemetry;

    public function formatDurationForTest(int $seconds): string
    {
        return $this->formatDuration($seconds);
    }

    public function getCountDownEstimateForTest(int $remaining, int $total, string $name = 'default'): ?string
    {
        return $this->getCountDownEstimate($remaining, $total, $name);
    }

    public function getCountUpEstimateForTest(int $completed, int $total, string $name = 'default'): ?string
    {
        return $this->getCountUpEstimate($completed, $total, $name);
    }

    public function hasTimerForTest(string $name = 'default'): bool
    {
        return array_key_exists($name, $this->timers);
    }

    public function setMinimumCountForEstimateForTest(int $count): void
    {
        $this->minimumCountForEstimate = $count;
    }

    public function setTimerForTest(string $name, float $value): void
    {
        $this->timers[$name] = $value;
    }

    public function startTimerForTest(string $name = 'default'): float
    {
        return $this->startTimer($name);
    }
}
