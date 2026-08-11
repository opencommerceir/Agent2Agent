# LLM Strategy — Full Details

## 1. Provider Abstraction

To keep the system flexible and vendor-neutral, all LLM providers are accessed through a common interface.

```php
interface LLMProviderInterface {
    /**
     * Send chat messages to the LLM and receive a response.
     *
     * @param array $messages
     * Example:
     * [
     *     ["role" => "system", "content" => "You are a negotiation assistant."],
     *     ["role" => "user", "content" => "Customer wants a discount."]
     * ]
     *
     * @param array $options
     * Example:
     * [
     *     "temperature" => 0.4,
     *     "max_tokens" => 512,
     *     "stop" => null
     * ]
     *
     * @return LLMResponse
     */
    public function chat(array $messages, array $options = []): LLMResponse;

    /**
     * Estimate cost before sending the request.
     *
     * @param array $messages
     * @return float
     */
    public function estimateCost(array $messages): float;
}
```

### Expected `LLMResponse` Structure

```php
class LLMResponse {
    public string $content;
    public string $provider;
    public string $model;
    public int $promptTokens;
    public int $completionTokens;
    public int $totalTokens;
    public float $estimatedCost;
    public float $latencyMs;
    public bool $fromFallback;
    public ?string $error;
}
```

---

## 2. Available Providers

| Provider | Cost | Speed | Use Case | Notes |
|---|---:|---:|---|---|
| Local Qwen 2.5 14B | FREE | ~20 tok/s | Default reasoning | Best private/default option for reasoning tasks |
| Local Llama 3.2 8B | FREE | ~30 tok/s | Classification | Lightweight and fast for intent/category detection |
| Groq Free Tier | FREE | ~500 tok/s | Fast responses | Useful when low latency is critical |
| OpenRouter Free Models | FREE | Variable | Fallback | Good fallback when local providers are unavailable |
| OpenAI GPT-4o | $ | Fast | Premium only | Use only for paid/high-value workflows |
| Anthropic Claude | $ | Fast | Premium only | Use only for paid/high-value workflows |

---

## 3. Default Provider Assignment

| Feature | Default Provider | Reason |
|---|---|---|
| Reasoning | `qwen-14b-local` | Strong local reasoning, zero cost |
| Negotiation | `qwen-14b-local` | Good balance of quality and privacy |
| Classification | `llama-3.2-3b-local` | Fast and cheap for simple tasks |
| Fallback | `openrouter-free` | Used when primary provider fails |

---

## 4. Admin Panel Settings

Configuration is stored centrally and can be changed from the admin panel.

```yaml
llm_config:
  reasoning: qwen-14b-local
  negotiation: qwen-14b-local
  classification: llama-3.2-3b-local
  fallback: openrouter-free

  cost_control:
    daily_budget_per_agent: 10000        # Toman
    monthly_budget_per_business: 500000  # Toman

  behavior:
    enable_fallback: true
    enable_hot_reload: true
    log_all_requests: true
    track_token_usage: true
    alert_on_budget_exceeded: true
```

---

## 5. Cost Per Action

| Action | Tokens | Cost (Local) | Cost (GPT-4o) |
|---|---:|---:|---:|
| Reasoning | 500 | 0 | 0.015 |
| Negotiation Message | 200 | 0 | 0.006 |
| Contract Generation | 1000 | 0 | 0.030 |

> **Note:** Local model cost is treated as `0` because inference runs on owned infrastructure.  
> GPT-4o costs are approximate and should be monitored continuously.

---

## 6. Provider Selection Logic

The system should choose providers in this order:

1. Use the configured provider for the requested feature.
2. If the primary provider fails or times out:
   - Use the configured fallback provider.
3. If fallback fails:
   - Return a controlled error.
   - Log the failure.
   - Optionally retry with exponential backoff.

### Example Routing Flow

```text
Request Type
    |
    v
Feature Config Lookup
    |
    v
Primary Provider
    |
    |-- Success --> Return Response
    |
    |-- Failure --> Fallback Provider
                        |
                        |-- Success --> Return Response + Mark as Fallback
                        |
                        |-- Failure --> Error + Logging + Alert
```

---

## 7. Switching Providers from Admin Panel

### Steps

1. Go to:
   ```text
   Settings → LLM Providers
   ```

2. Select the provider for each feature:
   - Reasoning
   - Negotiation
   - Classification
   - Fallback

3. Save changes.

4. System applies changes using hot reload.
   - No restart required.
   - New requests use the updated provider immediately.

5. Monitor usage and costs:
   ```text
   Dashboard → LLM Costs
   ```

---

## 8. Hot Reload Behavior

When admin saves provider settings:

```text
Save Config
    |
    v
Validate Provider IDs
    |
    v
Update Runtime Config Cache
    |
    v
Broadcast Config Updated Event
    |
    v
New Requests Use New Provider
```

### Important Rules

- Do **not** interrupt active in-flight requests.
- Apply changes to new requests only.
- Keep previous config version for rollback.
- Log every provider change with admin user ID and timestamp.

---

## 9. Budget Control

Budget limits prevent unexpected cost growth.

### Daily Agent Budget

```yaml
daily_budget_per_agent: 10000  # Toman
```

If an agent exceeds this limit:

- Block paid provider usage.
- Force local provider only.
- Show warning in admin dashboard.
- Log budget violation event.

### Monthly Business Budget

```yaml
monthly_budget_per_business: 500000  # Toman
```

If a business exceeds this limit:

- Disable premium providers.
- Keep local/free providers active if possible.
- Notify admin.
- Require manual approval to continue paid usage.

---

## 10. Cost Estimation Before Request

Before calling a paid provider, the system should estimate cost.

```php
$estimatedCost = $provider->estimateCost($messages);

if ($estimatedCost > $remainingBudget) {
    throw new BudgetLimitExceededException(
        "Estimated cost exceeds remaining budget."
    );
}
```

### Recommended Pre-Request Checks

- Check daily budget.
- Check monthly budget.
- Check provider availability.
- Check token count.
- Decide whether to use local fallback.

---

## 11. Fallback Strategy

### Fallback Triggers

Use fallback when:

- Primary provider timeout.
- Authentication error.
- Rate limit exceeded.
- Internal provider exception.
- Model unavailable.
- Budget restriction blocks premium provider.

### Fallback Priority Example

```yaml
fallback_chain:
  - openrouter-free
  - groq-free
  - local-qwen-14b
```

### Fallback Rules

- Never fallback from local to paid automatically unless explicitly allowed.
- Prefer free/local providers for fallback.
- Mark response metadata with `fromFallback: true`.
- Log fallback reason.

---

## 12. Monitoring Dashboard

Admin dashboard should show:

### Usage Metrics

- Total requests
- Total tokens used
- Prompt tokens
- Completion tokens
- Average latency
- Success rate
- Fallback rate
- Error rate

### Cost Metrics

- Cost per provider
- Cost per feature
- Cost per agent
- Cost per business
- Daily cost
- Monthly cost
- Remaining budget

### Alerts

- Budget exceeded
- Provider down
- High fallback rate
- High error rate
- Abnormal token usage
- Latency spike

---

## 13. Example Dashboard Widgets

| Widget | Description |
|---|---|
| Total Requests Today | Number of LLM calls made today |
| Tokens Used Today | Total prompt + completion tokens |
| Cost Today | Estimated cost in Toman or USD |
| Fallback Rate | Percentage of requests served by fallback |
| Provider Health | Online/offline status per provider |
| Budget Remaining | Daily/monthly remaining budget |
| Top Feature by Usage | Reasoning, negotiation, classification, etc. |

---

## 14. Security and Privacy Rules

| Rule | Description |
|---|---|
| Prefer local models for sensitive data | Avoid sending private data to external APIs |
| Mask sensitive fields | Remove phone numbers, national IDs, payment data before external calls |
| Log safely | Do not store raw sensitive prompts |
| Role-based access | Only admins can change LLM provider settings |
| Audit trail | Track who changed provider configuration and when |
| API key storage | Store provider keys encrypted |

---

## 15. Recommended Production Configuration

```yaml
llm_config:
  reasoning: qwen-14b-local
  negotiation: qwen-14b-local
  classification: llama-3.2-3b-local
  fallback: openrouter-free

  fallback_chain:
    - openrouter-free
    - groq-free
    - local-qwen-14b

  cost_control:
    daily_budget_per_agent: 10000
    monthly_budget_per_business: 500000

  behavior:
    enable_fallback: true
    enable_hot_reload: true
    log_all_requests: true
    track_token_usage: true
    alert_on_budget_exceeded: true
    block_paid_when_budget_exceeded: true
    prefer_local_for_sensitive_data: true
```

---

## 16. Operational Checklist

Before going live:

- [ ] Local Qwen model is running.
- [ ] Local Llama model is running.
- [ ] Fallback provider is configured.
- [ ] Budget limits are set.
- [ ] Cost tracking is enabled.
- [ ] Admin can switch providers.
- [ ] Hot reload works without restart.
- [ ] Dashboard shows token usage.
- [ ] Dashboard shows cost usage.
- [ ] Alerts are configured.
- [ ] Sensitive data masking is enabled.
- [ ] Provider failure fallback is tested.

---

## 17. Summary

The LLM strategy is designed to be:

- **Cost-efficient:** Use local/free providers by default.
- **Fast:** Use lightweight models for simple tasks.
- **Reliable:** Automatically fallback when a provider fails.
- **Controllable:** Admin can switch providers without restart.
- **Budget-safe:** Enforce daily and monthly spending limits.
- **Observable:** Monitor usage, cost, latency, and errors.
- **Private:** Prefer local models for sensitive workflows.