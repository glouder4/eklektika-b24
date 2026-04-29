<?php

namespace OnlineService\Sync\FromSite;

class InboundPayloadNormalizer
{
    public static function normalizeRequestPayload(array $payload): array
    {
        if (isset($payload['PARAMS']) && is_string($payload['PARAMS']) && $payload['PARAMS'] !== '') {
            $decoded = json_decode($payload['PARAMS'], true);
            if (is_array($decoded)) {
                $payload['PARAMS'] = $decoded;
            } else {
                $payload['_INVALID_PAYLOAD'] = true;
                $payload['_INVALID_PAYLOAD_REASON'] = 'PARAMS must be valid JSON object/array';
            }
        }

        if (isset($payload['ACTION']) && is_scalar($payload['ACTION'])) {
            $payload['ACTION'] = strtoupper(trim((string)$payload['ACTION']));
        } else {
            $payload['ACTION'] = '';
        }

        return $payload;
    }

    public static function normalizeResponse($response, array $trace = []): array
    {
        if (!is_array($response)) {
            $response = [
                'success' => 0,
                'error' => 'invalid_sync_response',
            ];
        }

        if (!isset($response['success'])) {
            $response['success'] = 0;
        }
        if (!isset($response['error'])) {
            $response['error'] = '';
        }
        if (!isset($response['error_code'])) {
            $response['error_code'] = '';
        }
        if (isset($response['http_status'])) {
            $response['http_status'] = (int)$response['http_status'];
            if ($response['http_status'] <= 0) {
                unset($response['http_status']);
            }
        } else {
            $errorCode = (string)($response['error_code'] ?? '');
            if ($errorCode === 'invalid_payload' || $errorCode === 'unknown_action') {
                $response['http_status'] = 400;
            } else {
                // Known action + domain failure is always transport-success (HTTP 200).
                $response['http_status'] = 200;
            }
        }
        if (isset($trace['trace_id']) && $trace['trace_id'] !== '') {
            $response['trace_id'] = $trace['trace_id'];
        }
        if (isset($trace['correlation_id']) && $trace['correlation_id'] !== '') {
            $response['correlation_id'] = $trace['correlation_id'];
        }
        if (isset($trace['cutover_label']) && $trace['cutover_label'] !== '') {
            $response['cutover_label'] = $trace['cutover_label'];
        }

        return $response;
    }

    public static function normalizeError(\Throwable $e, array $trace = []): array
    {
        return self::normalizeResponse([
            'success' => 0,
            'error' => 'sync_internal_error',
            'error_code' => 'dispatch_failed',
            'http_status' => 500,
            'error_class' => get_class($e),
        ], $trace);
    }
}
