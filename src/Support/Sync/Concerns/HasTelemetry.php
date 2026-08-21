<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Sync\Concerns;

use function intdiv;
use function microtime;

/**
 * @api
 */
trait HasTelemetry
{
    /**
     * @var array<string, float>
     */
    private array $timers = [];

    private int $minimumCountForEstimate = 3;

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }

        $minutes = intdiv($seconds, num2: 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return $minutes . 'm ' . $remainingSeconds . 's';
        }

        $hours = intdiv($minutes, num2: 60);
        $remainingMinutes = $minutes % 60;

        return $hours . 'h ' . $remainingMinutes . 'm';
    }

    private function getCountDownEstimate(int $remaining, int $total, string $name = 'default'): ?string
    {
        $completed = $total - $remaining;

        return $this->getCountUpEstimate($completed, $total, $name);
    }

    private function getCountUpEstimate(int $completed, int $total, string $name = 'default'): ?string
    {
        $this->timers[$name] ??= $this->startTimer($name);
        $startedAt = $this->timers[$name];

        if ($completed <= $this->minimumCountForEstimate) {
            return null;
        }

        $elapsed = microtime(true) - $startedAt;

        if ($elapsed <= 0) {
            return null;
        }

        $remaining = $total - ($completed + 1);

        if ($remaining <= 0) {
            return null;
        }

        $seconds = (int) (($elapsed / $completed) * $remaining);

        if ($seconds <= 0) {
            return null;
        }

        return $this->formatDuration($seconds);
    }

    private function startTimer(string $name = 'default'): float
    {
        return $this->timers[$name] = microtime(true);
    }
}
