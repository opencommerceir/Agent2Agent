<?php

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Application\DTOs\TicketCommentData;
use App\Modules\CRM\Domain\Entities\TicketComment;
use App\Modules\CRM\Domain\Events\CommentWasAddedToTicket;
use App\Modules\CRM\Domain\Exceptions\TicketNotFoundException;
use App\Modules\CRM\Domain\Repositories\TicketRepositoryInterface;
use Illuminate\Support\Facades\Event;

/**
 * Backs the `crm.comment.create` MCP capability. Loads the parent Ticket
 * first with an explicit tenantId — the only place tenant isolation is
 * enforced for a comment, since TicketComment itself carries no tenant_id
 * column (Ticket entity's own docblock).
 */
final class AddCommentToTicketAction
{
    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
    ) {
    }

    public function execute(int $ticketId, int $tenantId, int $agentId, string $content): TicketCommentData
    {
        if (! $this->tickets->findById($ticketId, $tenantId)) {
            throw new TicketNotFoundException("Ticket [{$ticketId}] does not exist.");
        }

        $comment = TicketComment::create($ticketId, $agentId, $content);
        $comment = $this->tickets->addComment($comment);

        Event::dispatch(new CommentWasAddedToTicket($comment));

        return TicketCommentData::fromEntity($comment);
    }
}
