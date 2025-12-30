<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Validates that an organization name generates a unique slug.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class UniqueOrganizationName extends Constraint
{
    public string $message = 'Une organisation avec ce nom existe déjà.';
}
