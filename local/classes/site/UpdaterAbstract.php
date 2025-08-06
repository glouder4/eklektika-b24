<?php
    namespace OnlineService\Site;

    class UpdaterAbstract{
        public $eventManager;
        public function __construct()
        {
            $this->eventManager = \Bitrix\Main\EventManager::getInstance();

            $this->registerEvents();
        }
        protected function registerEvents(){
        }

        protected function sendRequest($params, $debug = false){
            $queryUrl = EKLEKTIKA_SITE_URL.'/local/classes/ajax.php';
            $curl = curl_init();
            $queryData  = http_build_query($params);

            curl_setopt_array($curl, array(
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_SSL_VERIFYHOST => FALSE,
                CURLOPT_POST => 1,
                CURLOPT_HEADER => 0,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $queryUrl,
                CURLOPT_POSTFIELDS => $queryData,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
            ));

            $result = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            $curlErrno = curl_errno($curl);

            curl_close($curl);

            if( $debug ){
                // Логируем детали запроса
                pre("=== CURL Request Details ===");
                pre("URL: " . $queryUrl);
                pre("Params: " . print_r($params, true));
                pre("HTTP Code: " . $httpCode);
                pre("CURL Error: " . $curlError);
                pre("CURL Errno: " . $curlErrno);
                pre("Raw Response: " . $result);
            }

            // Обработка ошибок CURL
            if ($curlErrno) {
                pre("CURL Error occurred: " . $curlError);
                return [
                    'success' => 0,
                    'error' => 'CURL Error: ' . $curlError,
                    'errno' => $curlErrno
                ];
            }

            // Обработка HTTP ошибок
            if ($httpCode !== 200) {
                if( $debug )
                    pre("HTTP Error: " . $httpCode);

                return [
                    'success' => 0,
                    'error' => 'HTTP Error: ' . $httpCode,
                    'response' => $result
                ];
            }

            // Парсим JSON ответ
            $decodedResult = json_decode($result, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                if( $debug ) {
                    pre("JSON Parse Error: " . json_last_error_msg());
                    pre("Raw response that failed to parse: " . $result);
                }
                return [
                    'success' => 0,
                    'error' => 'JSON Parse Error: ' . json_last_error_msg(),
                    'raw_response' => $result
                ];
            }
            if( $debug ) {
                pre("=== Parsed Response ===");
                pre($decodedResult);
                die();
            }

            return $decodedResult;
        }

        protected function callAPI(){

        }
    }