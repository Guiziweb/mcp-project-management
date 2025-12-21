<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Model\User;

interface UserPort
{
    /**
     * Get current authenticated user.
     */
    public function getCurrentUser(): User;
}
