# Commercial margin resolution (V1)

Platform commercial margin is applied when syncing/costing orders via `OrderCostService`.

## Order of precedence

1. **Payload hints** — `commission_amount` and/or `base_amount` from the provider sync payload
2. **Per-provider rate** — `providers.commission_rate` (% of selling price)
3. **Platform default** — `system_settings.default_commission_percent` (editable in Admin → Settings → Margins)
4. **Zero margin** — `supplier_cost = selling_price`, `commission_amount = 0`

## Rules

- Selling price prefers `final_amount`, then `total_amount`.
- Changing Settings never rewrites historical order financial columns.
- Provider-specific rates always win over the platform default when present.
- Secrets / API keys are not stored in Settings; they remain in `.env`.
