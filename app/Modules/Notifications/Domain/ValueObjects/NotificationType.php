<?php

namespace App\Modules\Notifications\Domain\ValueObjects;

/**
 * `TicketCreated` is modeled but has no registered Listener this stage —
 * only the Listeners explicitly requested by their own stage
 * (`ShipmentStatusChanged`/`OrderPlaced`/`PointsEarned`, and — Phase 5
 * Stage 5, §7.25 — `SubscriptionPaymentFailed`) are wired, the same
 * "enum case exists before its own Listener does" shape
 * `EventType::CartAbandoned` had before the Tech Debt Sprint wired it.
 *
 * `PromotionAnnouncement` (Agent Orchestrator, §7.26) is a purely
 * additive new case, the same shape `SubscriptionPaymentFailed` itself
 * was added in (nothing about the other cases changes) — added because
 * `DeterministicPlanner`'s own sales-growth plan needs a real
 * `notification.message.send` `type` for "a marketing/promotional
 * message," and none of the other 5 cases fit that meaning. Like
 * `TicketCreated`, it has no registered Listener of its own — sending one
 * still requires an Agent (or, here, the Orchestrator on an Agent's
 * behalf) to call `notification.message.send` directly with an active
 * Template already configured for it.
 */
enum NotificationType: string
{
    case OrderPlaced = 'order_placed';
    case ShipmentStatusChanged = 'shipment_status_changed';
    case PointsEarned = 'points_earned';
    case TicketCreated = 'ticket_created';
    case SubscriptionPaymentFailed = 'subscription_payment_failed';
    case PromotionAnnouncement = 'promotion_announcement';

    // Nexus Phase 5 (Growth) — SendAgentInviteAction's own outbound
    // "join the platform" email, addressed to a raw lead email with no
    // owning Customer/Agent id, same shape PromotionAnnouncement already
    // established for a caller-supplied-recipient send. No Listener of
    // its own (not event-driven — sent synchronously the moment the
    // capability is invoked).
    case AgentInvite = 'agent_invite';

    // Nexus Phase 7 (Business Team Members) — InviteTeamMemberAction's own
    // "here's your temporary password" email, same caller-supplied-recipient
    // shape AgentInvite already established. No Listener of its own.
    case TeamMemberInvited = 'team_member_invited';

    // Nexus Phase 8/M4 (Automation Workflows) — ProcessAutomationRulesAction's
    // own three trigger outcomes, sent synchronously to the rule-owning
    // Business's own registered owner email the moment each rule fires (not
    // event-driven, same "no Listener of its own" shape AgentInvite/
    // TeamMemberInvited already established for a caller-supplied-recipient
    // send — here the recipient is looked up from BusinessOwner instead of
    // being caller-supplied, but the dispatch shape is identical).
    case RecurringOrderPlaced = 'recurring_order_placed';
    case InventoryAlertTriggered = 'inventory_alert_triggered';
    case PriceAlertTriggered = 'price_alert_triggered';
    case AutoDiscoverMatched = 'auto_discover_matched';
}
