<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Support\Migrations;

final readonly class BlueprintTypeMapper
{
    public function toColumnMethod(string $type): string
    {
        return match ($type) {
            'identifier', 'text', 'email', 'tel', 'url', 'radio-group' => 'string',
            'colour', 'table', 'select', 'checkbox-group', 'date-range' => 'json',
            'integer' => 'integer',
            'float', 'range' => 'float',
            'checkbox' => 'boolean',
            'textarea', 'rte', 'code' => 'text',
            'date-time', 'date', 'time' => 'timestamp',
            default => 'json',
        };
    }
}
