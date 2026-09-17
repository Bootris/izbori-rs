<?php

declare(strict_types=1);

namespace App\Services\Snapshots;

use RuntimeException;

/** Thrown when a source must not go public yet (results before the polls closed, wrong status). */
final class PublishBlockedException extends RuntimeException {}
