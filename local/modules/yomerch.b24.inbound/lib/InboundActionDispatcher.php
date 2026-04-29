<?php

namespace OnlineService\Sync\FromSite;

class InboundActionDispatcher
{
    private const ACTION_ALIASES = [
        // Keep legacy statussync compatibility during cutover.
        'UPDATE_STATUS_GROUP' => 'UPDATE_GROUP',
    ];

    /** @var array<string, callable> */
    private array $map;

    /** @param array<string, callable> $map */
    public function __construct(array $map)
    {
        $normalized = [];
        foreach ($map as $action => $handler) {
            $normalized[\strtoupper((string)$action)] = $handler;
        }
        $this->map = $normalized;
    }

    public function dispatch(string $action, array $request): array
    {
        $action = strtoupper(trim($action));
        if (isset(self::ACTION_ALIASES[$action])) {
            $action = self::ACTION_ALIASES[$action];
        }
        if ($action === '') {
            return [
                'success' => 0,
                'error' => 'Invalid payload: ACTION is required',
                'error_code' => 'invalid_payload',
                'reason_code' => 'action_required',
                'http_status' => 400,
            ];
        }

        if (!isset($this->map[$action]) || !is_callable($this->map[$action])) {
            return [
                'success' => 0,
                'error' => 'Unknown ACTION: ' . $action,
                'error_code' => 'unknown_action',
                'reason_code' => 'unsupported_action',
                'http_status' => 400,
            ];
        }

        return (array)call_user_func($this->map[$action], $request);
    }
}
