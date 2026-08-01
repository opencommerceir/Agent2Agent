<?php

namespace App\Modules\Notifications\Domain\Services;

/**
 * Not named in this stage's own request, but its rule 2 ("simple
 * `{{variable}}` placeholders, not Blade") needs an obvious owner — pure,
 * framework-free, the same shape Commerce's PricingService/Shipping's
 * ShippingRateCalculator/Workflows' WorkflowEvaluator already establish.
 * A variable with no matching key in $variables is left as the literal
 * `{{name}}` text rather than silently becoming an empty string — a
 * caller passing an incomplete variable set gets an obviously-wrong
 * result to notice, not a quietly-blank one.
 */
final class TemplateRenderer
{
    /**
     * @param array<string, mixed> $variables
     */
    public function render(string $template, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            fn (array $match) => array_key_exists($match[1], $variables) ? (string) $variables[$match[1]] : $match[0],
            $template,
        );
    }
}
