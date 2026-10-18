# WebtechSolutions_TraceLogic

Magento 2 connector for the TraceLogic Gateway, the compliance and B2B data gateway for EUDR, Green Claims and audit vault checks.

## Install / enable (run manually)

```bash
bin/magento module:enable WebtechSolutions_TraceLogic
bin/magento setup:upgrade
bin/magento cache:flush
```

Configure under Stores > Configuration > Webtech Solutions > TraceLogic Compliance (website scope): base URL, API key, attribute mapping.
New product attributes (`tracelogic_*`) are created in the "TraceLogic Compliance" group of the default attribute set; add them to other attribute sets manually.

## Features

- Checkout gate: orders are checked before submission; REJECTED blocks (WARNING optional). Fail-open by default with a circuit breaker.
- Order data: status, score, audit token, flags; "Verify audit entry" and "Re-check" in the order view; status column in the order grid.
- Catalog: `bin/magento tracelogic:catalog:scan`, nightly cron, warning on admin product save, report under TraceLogic > Compliance report.
- Rejected orders: either blocked, or (config) placed on hold; held orders are re-checked hourly and released automatically once the supplier data passes. Optional automatic supplier e-mail on rejected orders (`tracelogic_supplier_email` attribute).
- Supplier sync: `bin/magento tracelogic:supplier:sync [--supplier=CODE]` (also run before the nightly scan) pulls the data suppliers uploaded to TraceLogic (country, geolocation, DDS, certificates) into **empty** product attributes; existing values are never overwritten. Needs TraceLogic `GET /v1/suppliers/{code}`.
- Supplier data request: TraceLogic > Supplier data request (magic link sent by e-mail).
- Re-check of orders placed while TraceLogic was unavailable: queue consumer `tracelogic.order.recheck` (run via `bin/magento queue:consumers:start tracelogic.order.recheck` or the cron consumers runner) plus an hourly re-queue cron.

## Changelog

### 2026-10-13
- Initial release: checkout compliance gate, order audit data, catalog scan and report, supplier data requests, asynchronous re-check.
