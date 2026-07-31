<?php

namespace App\Modules\CRM;

use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\CapabilityHandlerRegistry;
use App\Modules\CRM\Application\Actions\AddCommentToTicketAction;
use App\Modules\CRM\Application\Actions\AddNoteToCustomerAction;
use App\Modules\CRM\Application\Actions\CreateTicketAction;
use App\Modules\CRM\Application\Actions\GetTicketAction;
use App\Modules\CRM\Application\Actions\ListTicketsAction;
use App\Modules\CRM\Application\DTOs\CustomerNoteData;
use App\Modules\CRM\Application\DTOs\TicketCommentData;
use App\Modules\CRM\Application\DTOs\TicketData;
use App\Modules\CRM\Domain\Repositories\CustomerNoteRepositoryInterface;
use App\Modules\CRM\Domain\Repositories\TagRepositoryInterface;
use App\Modules\CRM\Domain\Repositories\TicketRepositoryInterface;
use App\Modules\CRM\Infrastructure\Repositories\EloquentCustomerNoteRepository;
use App\Modules\CRM\Infrastructure\Repositories\EloquentTagRepository;
use App\Modules\CRM\Infrastructure\Repositories\EloquentTicketRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the CRM module — Phase 3's first Domain Module, built on top
 * of Phase 1's Core and Phase 2's Commerce exactly the way CLAUDE.md's
 * "Infrastructure First, Domains Second" philosophy intends: nothing in
 * Core or Commerce changed to make CRM possible. CRM depends on Commerce
 * only through Commerce's own published Domain Repository Interfaces
 * (`CustomerRepositoryInterface`) — never Commerce's Infrastructure/Model
 * classes — the same Dependency Inversion direction Core's marker
 * interfaces already established for Core -> Module, applied here for
 * Module -> Module.
 *
 * Capability *handler* registration lives here (pure in-memory, safe on
 * every boot); capability *description* registration follows Commerce's
 * seeder pattern instead (CRMCapabilitiesSeeder), same
 * RefreshDatabase-ordering reason documented there.
 */
class CRMServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TicketRepositoryInterface::class, EloquentTicketRepository::class);
        $this->app->bind(CustomerNoteRepositoryInterface::class, EloquentCustomerNoteRepository::class);
        $this->app->bind(TagRepositoryInterface::class, EloquentTagRepository::class);
    }

    public function boot(): void
    {
        $handlers = $this->app->make(CapabilityHandlerRegistry::class);

        $handlers->register('crm.ticket.create', function (array $input, AuthContext $context) {
            /** @var TicketData $ticket */
            $ticket = $this->app->make(CreateTicketAction::class)->execute(
                tenantId: $context->tenantId,
                agentId: $context->agentId,
                customerId: (int) $input['customer_id'],
                subject: $input['subject'],
                description: $input['description'],
                priority: $input['priority'] ?? 'medium',
            );

            return ['ticket' => $ticket->toArray()];
        });

        $handlers->register('crm.ticket.get', function (array $input, AuthContext $context) {
            /** @var TicketData $ticket */
            $ticket = $this->app->make(GetTicketAction::class)->execute((int) $input['ticket_id'], $context->tenantId);

            return ['ticket' => $ticket->toArray()];
        });

        $handlers->register(
            'crm.ticket.list',
            fn (array $input, AuthContext $context) => $this->app->make(ListTicketsAction::class)->execute($input, $context->tenantId),
        );

        $handlers->register('crm.comment.create', function (array $input, AuthContext $context) {
            /** @var TicketCommentData $comment */
            $comment = $this->app->make(AddCommentToTicketAction::class)->execute(
                ticketId: (int) $input['ticket_id'],
                tenantId: $context->tenantId,
                agentId: $context->agentId,
                content: $input['content'],
            );

            return ['comment' => $comment->toArray()];
        });

        $handlers->register('crm.note.create', function (array $input, AuthContext $context) {
            /** @var CustomerNoteData $note */
            $note = $this->app->make(AddNoteToCustomerAction::class)->execute(
                tenantId: $context->tenantId,
                agentId: $context->agentId,
                customerId: (int) $input['customer_id'],
                content: $input['content'],
            );

            return ['note' => $note->toArray()];
        });
    }
}
