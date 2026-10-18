# Changelog

## 0.1.0

- `convert` and `convertUrl`, every conversion endpoint (`create`, `createFromUrl`, `upload`, `start`, `retrieve`, `list`, `cancel`, `createCase`, `delete`, `wait`), `usage`, `Spec::make`, and `Webhook::verify` and `Webhook::parse`.
- `IntoSQLException` with `status`, `errorCode`, `fields` and `requestId`; `ConversionFailedException` and `ConversionCanceledException`.
- Retries on rate limits, a full account, server errors and lost connections, with the same idempotency key.
