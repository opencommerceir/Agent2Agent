<?php

namespace App\Modules\Analytics\Domain\ValueObjects;

/**
 * Every KPI this module knows how to compute. Several of these are
 * deliberately *not* backed by a dedicated Domain Calculator (`TopProducts`,
 * `LowStockProducts`, `LoyaltyPointsEarned`, `LoyaltyPointsRedeemed`,
 * `ActiveLoyaltyAccounts`) — they're direct reads with no derived math to
 * apply, shaped straight from a Query Builder's own output by
 * `CalculateKPIAction`. The rest map to exactly one of the 4 requested
 * Domain Calculators (`RevenueCalculator`/`OrderCalculator`/
 * `CustomerCalculator`/`ConversionRateCalculator`) — see each Calculator's
 * own docblock for which `KPIType`s it owns.
 */
enum KPIType: string
{
    case Revenue = 'revenue';
    case RevenueGrowthRate = 'revenue_growth_rate';
    case TotalOrders = 'total_orders';
    case AverageOrderValue = 'average_order_value';
    case TotalCustomers = 'total_customers';
    case NewCustomers = 'new_customers';
    case ConversionRate = 'conversion_rate';
    case TopProducts = 'top_products';
    case LowStockProducts = 'low_stock_products';
    case LoyaltyPointsEarned = 'loyalty_points_earned';
    case LoyaltyPointsRedeemed = 'loyalty_points_redeemed';
    case ActiveLoyaltyAccounts = 'active_loyalty_accounts';
    case CustomerRetentionRate = 'customer_retention_rate';
    case CustomerLifetimeValue = 'customer_lifetime_value';
}
