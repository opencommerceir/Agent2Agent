<?php

namespace App\Domains\Nexus\Admin\Domain\Repositories;

use App\Domains\Nexus\Admin\Domain\Entities\PlatformSetting;

interface PlatformSettingRepositoryInterface
{
    public function findByKey(string $key): ?PlatformSetting;

    public function save(PlatformSetting $setting): PlatformSetting;
}
