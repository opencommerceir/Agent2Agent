<?php

namespace App\Modules\AgentOrchestrator\Infrastructure\Repositories;

use App\Modules\AgentOrchestrator\Domain\Entities\AgentProfile;
use App\Modules\AgentOrchestrator\Domain\Exceptions\AgentProfileNotFoundException;
use App\Modules\AgentOrchestrator\Domain\Repositories\AgentProfileRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;

/**
 * Reads via Laravel's own `config()` repository, never `glob()`/direct
 * filesystem access — `config/agents/{type}.php` is auto-merged into
 * `config('agents.{type}')` by `LoadConfiguration`'s own recursive
 * directory scan (confirmed against this Laravel version's own
 * `getConfigurationFiles()`), the same nested-config-directory mechanism
 * `config/mcp.php`/`config/api.php` sit alongside, just one directory
 * level deeper. Reading the filesystem directly instead — the request's
 * own literal `listAll()` implementation — would silently break under
 * `php artisan config:cache` (a cached config repository no longer has
 * the original file paths `glob()` needs), a real production correctness
 * bug avoided here, not merely a style preference.
 *
 * Placed under `Infrastructure/Repositories/`, not `Application/Services/`
 * as the request's own file list named it — every other implementation of
 * a Domain Repository Interface in this codebase lives here regardless of
 * backing mechanism (a database, or — here — the config system, itself an
 * external, non-Domain data source); the same kind of placement
 * correction `ApiVersioning` middleware's own docblock already made
 * (HANDOFF §7.19, "lives under Interfaces/HTTP, not Infrastructure/Middleware
 * as originally requested").
 */
class ConfigBasedAgentProfileRepository implements AgentProfileRepositoryInterface
{
    private const CONFIG_ROOT = 'agents';

    public function findByType(string $type): AgentProfile
    {
        $agentType = AgentType::tryFrom($type);
        $config = config(self::CONFIG_ROOT.'.'.$type);

        if ($agentType === null || ! is_array($config)) {
            throw new AgentProfileNotFoundException("Agent profile [{$type}] not found.");
        }

        return AgentProfile::fromConfig($agentType, $config);
    }

    public function listAll(): array
    {
        $profiles = [];

        foreach (array_keys(config(self::CONFIG_ROOT, [])) as $type) {
            $profiles[] = $this->findByType($type);
        }

        return $profiles;
    }
}
