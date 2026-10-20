# Changelog

## 0.3.0

- A PostgreSQL dump converts like a MySQL one; `NotConverted` documents its kinds: `materialized_view`, `rule`, `extension`, `policy`, `type` and `other`.

## 0.2.1

- The README says a MySQL or MariaDB dump converts to any of the six databases, and its report carries the codes of each target.

## 0.2.0

- Reports of database dumps: `source`, `tables` and `notConverted`, read with `$conversion->jobReport()`.

## 0.1.0

- `convert` and `convertUrl`, every conversion endpoint (`create`, `createFromUrl`, `upload`, `start`, `retrieve`, `list`, `cancel`, `createCase`, `delete`, `wait`), `usage`, `Spec::make`, and `Webhook::verify` and `Webhook::parse`.
- `IntoSQLException` with `status`, `errorCode`, `fields` and `requestId`; `ConversionFailedException` and `ConversionCanceledException`.
- Retries on rate limits, a full account, server errors and lost connections, with the same idempotency key.
