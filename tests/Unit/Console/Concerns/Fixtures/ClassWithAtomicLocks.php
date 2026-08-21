<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Tests\Unit\Console\Concerns\Fixtures;

use Typdy\StarterKit\Laravel\Console\Concerns\HasAtomicLocks;

final class ClassWithAtomicLocks
{
    use HasAtomicLocks;

    /**
     * @param list<?string> $scope
     * @param array<string, mixed> $options
     */
    public function __construct(
        private array $scope,
        private array $options = [],
    ) {}

    public function acquireLockForTest(): bool
    {
        return $this->acquireLock();
    }

    public function hasLockForTest(): bool
    {
        return $this->hasLock();
    }

    public function withOption(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->options[$key] = $value;

        return $clone;
    }

    /**
     * @return list{string, string, ?string}
     */
    private function getScope(): array
    {
        return [
            $this->scope[0] ?? 'team',
            $this->scope[1] ?? 'project',
            $this->scope[2] ?? null,
        ];
    }

    private function option($key = null): mixed
    {
        if ($key === null) {
            return $this->options;
        }

        return $this->options[$key] ?? null;
    }
}
