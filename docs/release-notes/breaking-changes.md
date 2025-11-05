---
navigation_title: Breaking changes
description: Breaking changes for Elastic Distribution of OpenTelemetry PHP.
applies_to:
  stack:
  serverless:
    observability:
products:
  - id: cloud-serverless
  - id: observability
  - id: edot-sdk
---

# Elastic Distribution of OpenTelemetry PHP breaking changes [edot-php-breaking-changes]

Breaking changes can impact your Elastic applications, potentially disrupting normal operations. Before you upgrade, carefully review the Elastic Distribution of OpenTelemetry PHP breaking changes and take the necessary steps to mitigate any issues.

% ## Next version [edot-php-X.X.X-breaking-changes]

% Use the following template to add entries to this document.

% TEMPLATE START
% ::::{dropdown} Title of breaking change
% Description of the breaking change.
% **Impact**<br> Impact of the breaking change.
% **Action**<br> Steps for mitigating impact.
% View [PR #](PR link).
% ::::
% TEMPLATE END

## Version 1.2.0 [edot-php-1.2.0-breaking-changes]

::::{dropdown} Removal of ELASTIC_OTEL_VERIFY_SERVER_CERT (replaced by OTEL_EXPORTER_OTLP_INSECURE)
The environment variable ELASTIC_OTEL_VERIFY_SERVER_CERT has been removed and replaced by the standard OpenTelemetry variable OTEL_EXPORTER_OTLP_INSECURE.

**Impact**<br>
Any configuration still using ELASTIC_OTEL_VERIFY_SERVER_CERT is ignored. TLS server certificate verification now defaults to enabled (no change here) unless explicitly disabled via OTEL_EXPORTER_OTLP_INSECURE=true.

**Semantic change**<br>
Previous variable was affirmative (verify on true). New variable is negative (insecure on true):
- ELASTIC_OTEL_VERIFY_SERVER_CERT=true  → OTEL_EXPORTER_OTLP_INSECURE=false (secure; default)
- ELASTIC_OTEL_VERIFY_SERVER_CERT=false → OTEL_EXPORTER_OTLP_INSECURE=true (insecure; TLS verification disabled)

**Action**<br>
Remove ELASTIC_OTEL_VERIFY_SERVER_CERT from environments. For production leave OTEL_EXPORTER_OTLP_INSECURE unset or set to false. Only for local/testing disable verification:
```
OTEL_EXPORTER_OTLP_INSECURE=true
```

View PR [PR #300](https://github.com/elastic/elastic-otel-php/pull/300)
::::
