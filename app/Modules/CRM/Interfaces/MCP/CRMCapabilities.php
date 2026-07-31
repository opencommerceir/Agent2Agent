<?php

namespace App\Modules\CRM\Interfaces\MCP;

/**
 * The capability manifest for the CRM module — what
 * CRMCapabilitiesSeeder registers into the Capability Registry and
 * CRMServiceProvider wires into CapabilityHandlerRegistry. Kept as plain
 * data here, separate from the seeder's idempotency plumbing, the same
 * split Commerce's CommerceCapabilities established.
 *
 * Only 5 of CRM's 9 Actions are wired here — UpdateTicketAction,
 * GetCustomerNotesAction, CreateTagAction, AssignTagToCustomerAction were
 * built and tested but weren't among the capabilities requested this
 * stage (see each Action's own docblock). `crm.comment.create` and
 * `crm.note.create` were renamed from the originally requested
 * `crm.ticket.comment.add`/`crm.customer.note.add` — CapabilityName
 * requires exactly 3 dot-separated segments (HANDOFF gotcha #2), and
 * those had 4.
 */
final class CRMCapabilities
{
    /**
     * @return list<array{
     *     name: string,
     *     description: string,
     *     inputSchema: array<string, string>,
     *     outputSchema: array<string, string>,
     *     requiredPermissions: list<string>
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'crm.ticket.create',
                'description' => 'Open a new support Ticket for a Customer',
                'inputSchema' => ['customer_id' => 'integer', 'subject' => 'string', 'description' => 'string'],
                // priority is optional — defaults to "medium" — same
                // "optional fields are simply omitted from inputSchema"
                // reasoning Commerce's capabilities already establish.
                'outputSchema' => ['ticket' => 'array'],
                'requiredPermissions' => ['crm.tickets.create'],
            ],
            [
                'name' => 'crm.ticket.get',
                'description' => 'Get a Ticket by id',
                'inputSchema' => ['ticket_id' => 'integer'],
                'outputSchema' => ['ticket' => 'array'],
                'requiredPermissions' => ['crm.tickets.read'],
            ],
            [
                'name' => 'crm.ticket.list',
                'description' => "List the tenant's Tickets, optionally filtered by status or customer",
                // status and customer_id are both optional.
                'inputSchema' => [],
                'outputSchema' => ['tickets' => 'array'],
                'requiredPermissions' => ['crm.tickets.read'],
            ],
            [
                'name' => 'crm.comment.create',
                'description' => 'Add a comment to an existing Ticket',
                'inputSchema' => ['ticket_id' => 'integer', 'content' => 'string'],
                'outputSchema' => ['comment' => 'array'],
                'requiredPermissions' => ['crm.tickets.update'],
            ],
            [
                'name' => 'crm.note.create',
                'description' => 'Add a note to a Customer',
                'inputSchema' => ['customer_id' => 'integer', 'content' => 'string'],
                'outputSchema' => ['note' => 'array'],
                'requiredPermissions' => ['crm.customers.update'],
            ],
        ];
    }
}
