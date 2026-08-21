<?php

declare(strict_types=1);

use Typdy\StarterKit\Laravel\Tests\TestCase;
use Typdy\StarterKit\Laravel\Tests\Unit\Support\Sync\Concerns\Fixtures\ClassWithTelemetry;

uses(TestCase::class);

it('formats durations as seconds, minutes and hours', function () {
    $harness = new ClassWithTelemetry();

    expect($harness->formatDurationForTest(45))->toBe('45s');
    expect($harness->formatDurationForTest(125))->toBe('2m 5s');
    expect($harness->formatDurationForTest(7260))->toBe('2h 1m');
});

it('returns null for count up estimates until minimum progress is reached', function () {
    $harness = new ClassWithTelemetry();

    $harness->setTimerForTest('sync', microtime(true) - 500);

    expect($harness->getCountUpEstimateForTest(3, 20, 'sync'))->toBeNull();
});

it('initializes timers automatically when estimating', function () {
    $harness = new ClassWithTelemetry();

    expect($harness->hasTimerForTest('auto'))->toBeFalse();
    expect($harness->getCountUpEstimateForTest(1, 20, 'auto'))->toBeNull();
    expect($harness->hasTimerForTest('auto'))->toBeTrue();
});

it('returns formatted count up estimates when enough progress exists', function () {
    $harness = new ClassWithTelemetry();

    $harness->setTimerForTest('sync', microtime(true) - 400);

    expect($harness->getCountUpEstimateForTest(4, 10, 'sync'))->toBe('8m 20s');
});

it('returns null when no items remain or estimate would be non-positive', function () {
    $harness = new ClassWithTelemetry();

    $harness->setTimerForTest('done', microtime(true) - 100);
    $harness->setMinimumCountForEstimateForTest(0);

    expect($harness->getCountUpEstimateForTest(9, 10, 'done'))->toBeNull();
});

it('provides countdown estimates using remaining counts', function () {
    $harness = new ClassWithTelemetry();

    $harness->setTimerForTest('down', microtime(true) - 2880);

    expect($harness->getCountDownEstimateForTest(6, 10, 'down'))->toBe('1h 0m');
});
