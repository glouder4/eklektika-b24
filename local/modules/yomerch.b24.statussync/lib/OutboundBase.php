<?php

namespace OnlineService\StatusSync;

class OutboundBase
{
    public $eventManager;

    public function __construct()
    {
        $this->eventManager = \Bitrix\Main\EventManager::getInstance();
        $this->registerEvents();
    }

    protected function registerEvents()
    {
    }

    protected function sendRequest($params, $debug = false)
    {
        if (!is_array($params)) {
            $params = [];
        }

        $trace = \OnlineService\SyncTraceContext::resolve($params);
        $queryUrl = YOMERRCH24_SITE_URL . '/local/modules/yomerch.b24.inbound/endpoint.php';
        $retryCodes = [429, 503];
        $retryDelaysUs = [1000000, 2000000, 4000000, 8000000];
        $attempts = count($retryDelaysUs) + 1;
        $result = '';
        $httpCode = 0;
        $curlError = '';
        $curlErrno = 0;

        if (\defined('YOMERRCH24_SITE_SYNC_TOKEN') && \YOMERRCH24_SITE_SYNC_TOKEN !== '') {
            $params['sync_token'] = \YOMERRCH24_SITE_SYNC_TOKEN;
        }
        $params['_SYNC_TRACE_ID'] = (string)$trace['trace_id'];
        $params['_SYNC_CUTOVER_LABEL'] = (string)$trace['cutover_label'];
        $queryData = http_build_query($params);
        $headers = [
            'X-Correlation-ID: ' . (string)$trace['correlation_id'],
            'X-Cutover-Label: ' . (string)$trace['cutover_label'],
            'X-Sync-Trace-ID: ' . (string)$trace['trace_id'],
        ];
        if (\defined('YOMERRCH24_SITE_SYNC_TOKEN') && \YOMERRCH24_SITE_SYNC_TOKEN !== '') {
            $headers[] = 'X-Sync-Token: ' . \YOMERRCH24_SITE_SYNC_TOKEN;
        }

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_POST => 1,
                CURLOPT_HEADER => 0,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $queryUrl,
                CURLOPT_POSTFIELDS => $queryData,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER => $headers,
            ]);

            $result = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            $curlErrno = curl_errno($curl);
            curl_close($curl);

            $shouldRetry = ($curlErrno === 0)
                && in_array((int)$httpCode, $retryCodes, true)
                && $attempt < $attempts;
            if (!$shouldRetry) {
                break;
            }
            $baseDelayUs = $retryDelaysUs[$attempt - 1];
            $jitterUs = random_int(0, 300000);
            usleep($baseDelayUs + $jitterUs);
        }

        if ($debug) {
            pre('=== CURL Request Details ===');
            pre('URL: ' . $queryUrl);
            pre('Params: ' . print_r($params, true));
            pre('HTTP Code: ' . $httpCode);
            pre('CURL Error: ' . $curlError);
            pre('CURL Errno: ' . $curlErrno);
            pre('Raw Response: ' . $result);
        }

        if ($curlErrno) {
            pre('CURL Error occurred: ' . $curlError);

            return [
                'success' => 0,
                'error' => 'CURL Error: ' . $curlError,
                'errno' => $curlErrno,
            ];
        }

        if ($httpCode !== 200) {
            if ($debug) {
                pre('HTTP Error: ' . $httpCode);
            }

            return [
                'success' => 0,
                'error' => 'HTTP Error: ' . $httpCode,
                'response' => $result,
            ];
        }

        $decodedResult = json_decode((string)$result, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if ($debug) {
                pre('=== Parsed Response ===');
                pre($decodedResult);
                die();
            }

            return $decodedResult;
        }

        if ($debug) {
            pre('JSON Parse Warning: ' . json_last_error_msg());
            pre('Raw response that failed to parse: ' . $result);
        }

        return [
            'success' => 0,
            'error' => 'JSON Parse Error: ' . json_last_error_msg(),
            'raw_response' => $result,
        ];
    }

    protected function callAPI()
    {
    }
}
