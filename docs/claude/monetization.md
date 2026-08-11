# Monetization Model

## Credit Packages

| Package | Price (Toman) | Credits | Validity |
|---------|---------------|---------|----------|
| Starter | 500,000 | 1,000 | 30 days |
| Professional | 2,000,000 | 5,000 | 30 days |
| Enterprise | 10,000,000 | 30,000 | 30 days |

## Cost Per Action (Credits)

| Action | Credits |
|--------|---------|
| Agent activation (daily) | 10 |
| Discovery search | 5 |
| Initiate negotiation | 20 |
| Negotiation message | 2 |
| Contract generation | 50 |
| Payment processing | 100 + 0.5% |

## Admin Margin Settings

```yaml
admin_settings:
  llm_cost_markup: 30%
  transaction_fee: 0.5%
  subscription_markup: 20%
  negotiation_fee: 1%
```

## Revenue Streams

- Credit sales
- Transaction fees (0.5% of deal value)
- Premium features
- Enterprise SLA
- API access