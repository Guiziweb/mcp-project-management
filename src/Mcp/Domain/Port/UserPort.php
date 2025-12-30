<?php

declare(strict_types=1);

namespace App\Mcp\Domain\Port;

use App\Mcp\Domain\Model\ProviderUser;

interface UserPort
{
    /**
     * Get current authenticated user from the provider.
     */
    public function getCurrentUser(): ProviderUser;
}
