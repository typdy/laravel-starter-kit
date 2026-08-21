<?php

declare(strict_types=1);

use Typdy\StarterKit\Laravel\Models\Media;

beforeEach(function () {
    $this->media = new Media();
    $this->media->name = 'test.webp';
    $this->media->url = 'https://cdn.tcms.io/somepath/test.webp';
    $this->media->conversions = [
        'tiny-thumbnail' => 'https://cdn.tcms.io/somepath/test-tiny-thumbnail.webp',
        'small-thumbnail' => 'https://cdn.tcms.io/somepath/test-small-thumbnail.webp',
        'thumbnail' => 'https://cdn.tcms.io/somepath/test-thumbnail.webp',
        'large-thumbnail' => 'https://cdn.tcms.io/somepath/test-large-thumbnail.webp',
        'huge-thumbnail' => 'https://cdn.tcms.io/somepath/test-huge-thumbnail.webp',
        'constraint-1234' => 'https://cdn.tcms.io/somepath/test-constraint-1234.webp',
    ];
});

it('simplifies constraint conversion', function () {
    expect($this->media->constraintUrl)->toBe('https://cdn.tcms.io/somepath/test-constraint-1234.webp');
});

it('converts conversions to camel', function () {
    expect($this->media->conversions)->toHaveKeys([
        'tinyThumbnail',
        'smallThumbnail',
        'thumbnail',
        'largeThumbnail',
        'hugeThumbnail',
        'constraint1234',
    ]);
});
