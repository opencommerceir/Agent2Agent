<?php

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Application\DTOs\TagData;
use App\Modules\CRM\Domain\Entities\Tag;
use App\Modules\CRM\Domain\Repositories\TagRepositoryInterface;
use App\Modules\CRM\Domain\ValueObjects\TagName;
use InvalidArgumentException;

/**
 * Not wired to MCP this stage — no `crm.tag.create`-shaped capability was
 * among the 5 requested. Exercised directly in tests instead (same
 * "built, tested, not yet exposed" gap UpdateTicketAction's docblock
 * describes). Name uniqueness is enforced per-tenant, the same
 * "SKU/Category-slug are unique per tenant, not globally" convention
 * every other named aggregate in this codebase already follows.
 */
final class CreateTagAction
{
    public function __construct(
        private readonly TagRepositoryInterface $tags,
    ) {
    }

    public function execute(int $tenantId, string $name, ?string $color = null): TagData
    {
        $tagName = new TagName($name); // throws InvalidArgumentException on bad format

        if ($this->tags->nameExists($tagName, $tenantId)) {
            throw new InvalidArgumentException("Tag name [{$tagName}] already exists for this tenant.");
        }

        $tag = Tag::create($tenantId, $tagName, $color);
        $tag = $this->tags->save($tag);

        return TagData::fromEntity($tag);
    }
}
