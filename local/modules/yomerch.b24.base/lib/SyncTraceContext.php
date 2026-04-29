<?php

namespace OnlineService;

final class SyncTraceContext
{
    public const CUTOVER_LABEL = 'cutover-v2';

    public static function resolve(array $payload = []): array
    {
        $incoming = '';
        if (isset($payload['_SYNC_TRACE_ID']) && is_scalar($payload['_SYNC_TRACE_ID'])) {
            $incoming = (string)$payload['_SYNC_TRACE_ID'];
        }
        if ($incoming === '' && isset($_SERVER['HTTP_X_CORRELATION_ID']) && is_scalar($_SERVER['HTTP_X_CORRELATION_ID'])) {
            $incoming = (string)$_SERVER['HTTP_X_CORRELATION_ID'];
        }
        if ($incoming === '' && isset($_SERVER['HTTP_X_SYNC_TRACE_ID']) && is_scalar($_SERVER['HTTP_X_SYNC_TRACE_ID'])) {
            $incoming = (string)$_SERVER['HTTP_X_SYNC_TRACE_ID'];
        }

        $traceId = self::sanitizeTraceId($incoming);
        if ($traceId === '') {
            $traceId = substr(md5(uniqid('', true)), 0, 12);
        }

        return [
            'trace_id' => $traceId,
            'correlation_id' => $traceId,
            'cutover_label' => self::CUTOVER_LABEL,
        ];
    }

    public static function appendToContext(array $context, array $trace): array
    {
        $context['trace_id'] = (string)($trace['trace_id'] ?? '');
        $context['correlation_id'] = (string)($trace['correlation_id'] ?? '');
        $context['cutover_label'] = (string)($trace['cutover_label'] ?? self::CUTOVER_LABEL);

        return $context;
    }

    private static function sanitizeTraceId(string $traceId): string
    {
        $traceId = trim($traceId);
        if ($traceId === '') {
            return '';
        }
        $traceId = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $traceId);

        return substr((string)$traceId, 0, 64);
    }
}
