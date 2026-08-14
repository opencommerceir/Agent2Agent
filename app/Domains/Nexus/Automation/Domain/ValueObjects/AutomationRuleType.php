<?php

namespace App\Domains\Nexus\Automation\Domain\ValueObjects;

/**
 * The roadmap's own closed list for Phase 8 Automation Workflows
 * ("سفارشات تکرارشونده، هشدار موجودی (auto-search)، هشدار قیمت") — three
 * named workflow shapes, not an open-ended rule engine. A "Visual workflow
 * builder" over an unbounded trigger/condition/action grammar is
 * deliberately out of scope (see AutomationRule's own docblock).
 *
 * `AutoDiscover` is a fourth, later addition (the Autonomous Agent
 * Runtime's proactive half) — unlike RecurringOrder, its counterparty is
 * NOT pre-configured: it re-uses GetRecommendationsAction each run to find
 * one, the same discovery mechanism a human browsing the marketplace would
 * use, just automated.
 */
enum AutomationRuleType: string
{
    case RecurringOrder = 'recurring_order';
    case InventoryAlert = 'inventory_alert';
    case PriceAlert = 'price_alert';
    case AutoDiscover = 'auto_discover';
}
