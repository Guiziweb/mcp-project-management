<?php

declare(strict_types=1);

namespace App\Domain\User;

interface UserPort
{
    /**
     * Get current authenticated user.
     */
    public function getCurrentUser(): User;
}
