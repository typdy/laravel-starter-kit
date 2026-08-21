<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Laravel\Models\Contracts;

use Typdy\StarterKit\Models\Contracts\Camelable;
use Typdy\StarterKit\Models\Contracts\Construct;
use Typdy\StarterKit\Models\Contracts\Relatable;

/**
 * @api
 */
interface TypdyModel extends Construct, Camelable, Relatable {}
