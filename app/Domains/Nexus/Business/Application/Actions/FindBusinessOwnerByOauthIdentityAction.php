<?php

namespace App\Domains\Nexus\Business\Application\Actions;

use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwnerOauthIdentity;

final class FindBusinessOwnerByOauthIdentityAction
{
    public function execute(string $provider, string $providerUserId): ?BusinessOwner
    {
        $identity = BusinessOwnerOauthIdentity::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        return $identity ? BusinessOwner::query()->find($identity->business_owner_id) : null;
    }
}
