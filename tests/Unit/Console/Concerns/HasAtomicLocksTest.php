<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Laravel\Prompts\Note;
use Typdy\StarterKit\Laravel\Tests\TestCase;
use Typdy\StarterKit\Laravel\Tests\Unit\Console\Concerns\Fixtures\ClassWithAtomicLocks;

uses(TestCase::class);

beforeEach(function () {
    Cache::clear();

    $path = sys_get_temp_dir() . '/typdy-testing-locks/' . uniqid('atomic-lock-', more_entropy: true);

    File::ensureDirectoryExists($path);

    Config::set('cache.default', 'file');
    Config::set('cache.stores.file.path', $path);

    Cache::forgetDriver('file');
    Cache::forgetDriver((string) Config::get('cache.default'));

    Note::fake();

    $this->lockCachePath = $path;
});

afterEach(function () {
    File::deleteDirectory($this->lockCachePath);
});

it('acquires a lock and marks it as held', function () {
    $runner = new ClassWithAtomicLocks(['team-a', 'project-a', 'blueprint-a']);

    expect($runner->acquireLockForTest())->toBeTrue();
    expect($runner->hasLockForTest())->toBeTrue();

    $runner->releaseLock(true);
});

it('blocks a second lock acquisition on the same scope', function () {
    $first = new ClassWithAtomicLocks(['team-b', 'project-b', 'blueprint-b']);
    $second = new ClassWithAtomicLocks(['team-b', 'project-b', 'blueprint-b']);

    expect($first->acquireLockForTest())->toBeTrue();
    expect($second->acquireLockForTest())->toBeFalse();
    expect($second->hasLockForTest())->toBeFalse();

    Note::assertOutputContains('Another typdy sync is already running');

    $first->releaseLock(true);
});

it('allows force to take over an existing lock', function () {
    $first = new ClassWithAtomicLocks(['team-c', 'project-c', 'blueprint-c']);

    $forced = new ClassWithAtomicLocks(['team-c', 'project-c', 'blueprint-c'])
        ->withOption('force', true);

    $third = new ClassWithAtomicLocks(['team-c', 'project-c', 'blueprint-c']);

    expect($first->acquireLockForTest())->toBeTrue();
    expect($forced->acquireLockForTest())->toBeTrue();
    expect($third->acquireLockForTest())->toBeFalse();

    Note::assertOutputContains('Force option enabled');

    $forced->releaseLock(true);
});

it('releases a lock so another process can acquire it', function () {
    $first = new ClassWithAtomicLocks(['team-d', 'project-d', 'blueprint-d']);
    $second = new ClassWithAtomicLocks(['team-d', 'project-d', 'blueprint-d']);

    expect($first->acquireLockForTest())->toBeTrue();

    $first->releaseLock();

    expect($second->acquireLockForTest())->toBeTrue();

    $second->releaseLock(true);
});
