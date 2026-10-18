# TraceLogic Gateway Connector for Magento 2

[![Magento 2.4.x](https://img.shields.io/badge/Magento-2.4.6%2B%20%7C%202.4.7%2B-orange.svg?style=flat-square)](https://magento.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2%20%7C%208.3%20%7C%208.4-blue.svg?style=flat-square)](https://php.net/)
[![License](https://img.shields.io/badge/License-OSL--3.0%20%2F%20AFL--3.0-green.svg?style=flat-square)](LICENSE.txt)
[![TraceLogic](https://img.shields.io/badge/Compliance-EUDR%20%26%20Green%20Claims-emerald.svg?style=flat-square)](https://tracelogicgateway.com)

**Real-time transaction compliance, supplier evidence validation, and cryptographic audit trails directly inside your Magento 2 checkout and catalog.**

[Explore TraceLogic Gateway](https://tracelogicgateway.com) • [Run Free Catalog Audit](https://tracelogicgateway.com/catalog-audit) • [Report Issue](https://github.com/webtech-solutions/magento2-tracelogic/issues)

---

## Overview

The **WebtechSolutions_TraceLogic** extension integrates Magento 2 with the **TraceLogic Gateway** API. It acts as an automated, sub-50ms compliance firewall for your store, validating EU Deforestation Regulation (EUDR) requirements and Green Claims Directive policies before orders are released.

Instead of facing severe regulatory fines (up to 4% of global turnover) or stop-ship delays, this module automatically verifies supplier evidence, flags unsubstantiated greenwashing claims, and generates a tamper-proof cryptographic audit trail.

---

## Key Features

### 🛡️ Real-Time Checkout Compliance Gate
- **Pre-Submission Validation:** Evaluates cart items against HS-code EUDR rules (geolocation polygons, DDS references) and text descriptions for unverified environmental claims.
- **Fail-Open Resilience with Circuit Breaker:** If the compliance API experiences network latency or downtime, the checkout never breaks. Orders pass gracefully and are flagged for asynchronous re-evaluation.
- **Configurable Action:** `REJECTED` orders are blocked or automatically placed on hold. `WARNING` thresholds can be customized.

### 🔄 Intelligent Order Recovery & Auto-Release
- **Automatic Order Hold:** Holds non-compliant orders rather than losing the customer.
- **Hourly Re-Check Cron:** Continuously monitors held orders. Once missing supplier data or certificates are submitted, orders are automatically released for fulfillment.
- **Automated Supplier Outreach:** Sends an automated request email directly to the supplier (`tracelogic_supplier_email`) upon rejection with an upload magic link.

### 📊 Deep Admin Integration & Cryptographic Audit
- **Order Grid Badge & Detail View:** View compliance score (0–100), status badges, violation reason codes, and the unique `chk_vault_*` audit token.
- **One-Click Audit Verification:** "Verify Audit Entry" and "Re-Check" buttons allow direct validation against the immutable hash-chain vault.

### 🔍 Automated Catalog Scanning
- **Proactive Risk Detection:** Run `bin/magento tracelogic:catalog:scan` or rely on the nightly cron to audit entire product catalogs.
- **Admin Save Interceptor:** Warns catalog managers directly in the admin panel if a saved product contains unverified green claims or invalid EUDR data.
- **Compliance Dashboard:** Dedicated report view under **TraceLogic > Compliance Report**.

### 🔗 Two-Way Supplier Sync
- **Magic Link Issuance:** Generate secure, single-use supplier upload tokens directly from Magento admin (**TraceLogic > Supplier Data Request**).
- **Attribute Auto-Population:** Synchronizes validated producer country, geolocation coordinates, DDS references, and verified certificates into empty product attributes without overwriting manual data.

---

## Architecture & How It Works


```

[ Customer Checkout ]
│
▼
[ Magento 2 Plugin (Checkout Hook) ]
│
│  REST HTTPS (< 50ms)
▼
[ TraceLogic Gateway API ]
├── EUDR HS-Code & GeoJSON Rules
├── Green Claims Pattern Scanner
└── Cryptographic SHA-256 Audit Vault
│
▼
[ Response: APPROVED | REJECTED | WARNING ]
│
┌────┴─────────────────────────────┐
▼                                  ▼
[ APPROVED ]                   [ REJECTED / HOLD ]
Order placed successfully      Order placed on Hold + Supplier
Audit token saved to Order     notified via Magic Link

```

---

## Requirements

- **Magento Open Source** or **Adobe Commerce**: `2.4.6`, `2.4.7` or higher
- **PHP**: `8.1`, `8.2`, `8.3`, or `8.4`
- **Frontend Compatibility**: Native Luma, **Hyvä Themes**, and Headless (GraphQL ready)
- **TraceLogic Gateway Account**: Base URL and active API Key (Starter, Pro, or Enterprise)

---

## Installation

### Via Composer (Recommended)

```bash
composer require webtech-solutions/module-tracelogic
bin/magento module:enable WebtechSolutions_TraceLogic
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush

```

### Manual Installation

1. Download or clone this repository into `app/code/WebtechSolutions/TraceLogic`.
2. Run the deployment commands:
```bash
bin/magento module:enable WebtechSolutions_TraceLogic
bin/magento setup:upgrade
bin/magento cache:flush

```



---

## Configuration

1. In the Magento Admin, navigate to:
**Stores > Configuration > Webtech Solutions > TraceLogic Compliance**.
2. Set configuration at the **Website** or **Default** scope:
* **Enable:** `Yes`
* **Gateway Base URL:** `https://api.tracelogicgateway.com`
* **API Key:** `sec_live_...`
* **Action on Rejection:** `Block Checkout` or `Place Order on Hold`
* **Action on Warning:** `Allow Order` or `Flag for Review`


3. **Attribute Mapping:** Configure custom attributes or use the automatically provisioned `tracelogic_*` attributes located under the **TraceLogic Compliance** attribute group.

---

## CLI Commands Reference

| Command | Description |
| --- | --- |
| `bin/magento tracelogic:catalog:scan` | Runs a full compliance scan across all catalog products and updates compliance reports. |
| `bin/magento tracelogic:supplier:sync` | Pulls verified supplier evidence (geolocation, DDS, certs) from TraceLogic into empty product attributes. |
| `bin/magento tracelogic:supplier:sync --supplier=CODE` | Runs targeted evidence synchronization for a specific supplier code. |
| `bin/magento queue:consumers:start tracelogic.order.recheck` | Starts the consumer worker to re-evaluate orders placed during circuit-breaker/offline events. |

---

## Free Compliance Risk Assessment

Not sure if your catalog complies with the latest EU regulations? Test your product feed instantly without installing anything:

👉 **[Run Free CSV Catalog Audit (No registration required)](https://www.google.com/url?sa=E&source=gmail&q=https://tracelogicgateway.com/catalog-audit)**

---

## License & Support

* **License:** Open Software License (OSL 3.0) / Academic Free License (AFL 3.0)
* **Developed By:** [Webtech Solutions](https://webtech-solutions.hu)
* **Documentation & Issues:** Please submit bugs and feature requests via [GitHub Issues](https://www.google.com/url?sa=E&source=gmail&q=https://github.com/webtech-solutions/magento2-tracelogic/issues).
