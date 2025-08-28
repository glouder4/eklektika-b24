<?php
namespace OnlineService;

use OnlineService\Rest\RestCall;

class LocalApplicationHandler{
    protected $B24;
    protected $request;

    protected array $availableMethods = [
        'GET_CONTACT_ID',
        'DELETE_CONTACT',
        'UPDATE_CONTACT'
    ];
    public function __construct($request)
    {
        $this->request = $request;
    }

    private function getContactByFields($fields){
        \Bitrix\Main\Loader::requireModule('crm');

// Подготавливаем условия поиска
        $filter = [
            'CHECK_PERMISSIONS' => 'N',
            'LOGIC' => 'OR' // Ключевое изменение - используем ИЛИ вместо И
        ];

// Добавляем поиск по id
        if (!empty($fields['ID'])) {
            $filter['ID'] = $fields['ID'];
        }

// Добавляем поиск по email
        if (!empty($fields['EMAIL'])) {
            /* $emailFilter = [
                'LOGIC' => 'OR',
                'EMAIL' => $fields['EMAIL'],
                'WORK_EMAIL' => $fields['EMAIL'],
            ];

            $filter[] = $emailFilter;*/
            $filter['=EMAIL.VALUE'] = $fields['EMAIL'];
        }

// Добавляем поиск по телефону
        if (!empty($fields['PHONE'])) {
            $normalizedPhone = normalizePhone($fields['PHONE']);

            // Создаем подмассив для телефонов
            /* $phoneFilter = [
                'LOGIC' => 'OR',
                'PHONE' => $normalizedPhone,
                'WORK_PHONE' => $normalizedPhone,
                'MOBILE_PHONE' => $normalizedPhone
            ];

            // Для D7 API нужно добавить как подмассив
            $filter[] = $phoneFilter;*/

            $filter['=PHONE.VALUE'] = $normalizedPhone;
        }

        // Если нет критериев поиска
        if (count($filter) <= 2) { // Только CHECK_PERMISSIONS и LOGIC
            return [];
        }

        //pre($filter);
        $counter = 0;
        $restRequest = new RestCall();

        try {
            $contactEmailResult = $restRequest->sendRequest([
                'FILTER' => [
                    'LOGIC' => 'OR',
                    'EMAIL' => $fields['EMAIL'],
                    //'PHONE' => '+'.normalizePhone($fields['PHONE']),
                ],
                'ORDER' => [
                    'ID' => 'DESC',
                ],
                'SELECT' => ['ID','EMAIL','PHONE']
            ],URL_B24.'rest/3032/2hyoqin6b0r3irzz/crm.contact.list.json',false);

            $contactPhoneResult = $restRequest->sendRequest([
                'FILTER' => [
                    'LOGIC' => 'OR',
                    //'EMAIL' => $fields['EMAIL'],
                    'PHONE' => $fields['PHONE'],
                ],
                'ORDER' => [
                    'ID' => 'DESC',
                ],
                'SELECT' => ['ID','EMAIL','PHONE']
            ],URL_B24.'rest/3032/2hyoqin6b0r3irzz/crm.contact.list.json',false);

            if( isset($contactEmailResult['result']) && isset($contactPhoneResult['result']) ){
                return array_merge($contactEmailResult['result'],$contactPhoneResult['result'])[0] ?? [];
            }
        }
        catch (\Exception $e) {
            return [];
        }
    }


    public function getResponse(){
        // Очищаем ACTION от возможных пробелов
        $action = trim($this->request['ACTION']);

        if( in_array($action, $this->availableMethods) ){
            // Роутинг по типу запроса
            switch($action) {
                case 'GET_CONTACT_ID':
                    return $this->handleGetContactId();
                    break;

                case 'DELETE_CONTACT':
                    return $this->handleDeleteContact();
                    break;

                default:
                    return [
                        'success' => false,
                        'message' => 'Метод не реализован'
                    ];
            }
        }
        else{
            return [
                'success' => false,
                'message' => 'Неизвестный метод: ' . $action
            ];
        }
    }

    /**
     * Обработчик запроса GET_CONTACT_ID
     */
    private function handleGetContactId() {
        $contact = $this->getContactByFields($this->request);

        if ($contact) {
            return [
                'success' => true,
                'data' => $contact
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Контакт не найден',
                'search_params' => [
                    'email' => $this->request['EMAIL'] ?? '',
                    'phone' => $this->request['PHONE'] ?? ''
                ]
            ];
        }
    }

    /**
     * Обработчик запроса DELETE_CONTACT
     * $this->request = [
     *  'ID' => id контакта в crm,
     *  'ACTION'
     * ];
     */
    private function handleDeleteContact() {
        \Bitrix\Main\Loader::requireModule('crm');
        \Bitrix\Main\Loader::requireModule('main');

        if (\CCrmContact::Exists($this->request['ID'])) {
            $contactId = $this->request['ID'];

            // Получаем информацию о контакте перед удалением
            $contactInfo = \CCrmContact::GetByID($contactId);

            // Создаём объект контакта
            $contact = new \CCrmContact(false);

            // Сначала отвязываем от компании
            $arFields = ['COMPANY_ID' => 0];

            try {
                $updateResult = $contact->Update($contactId, $arFields);

                // Проверяем ошибки после Update
                if ($contact->LAST_ERROR) {
                    return [
                        'success' => false,
                        'data' => 'Ошибка при обновлении контакта',
                        'error' => $contact->LAST_ERROR,
                        'debug' => [
                            'contact_id' => $contactId,
                            'original_company_id' => $contactInfo['COMPANY_ID'],
                            'update_result' => $updateResult
                        ]
                    ];
                }

                // Теперь пытаемся удалить контакт
                $deleteResult = $contact->Delete($contactId);

                if ($deleteResult) {
                    return [
                        'success' => true,
                        'data' => 'Контакт успешно удален',
                        'debug' => [
                            'contact_id' => $contactId,
                            'original_company_id' => $contactInfo['COMPANY_ID'],
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'data' => 'Ошибка при удалении контакта',
                        'error' => $contact->LAST_ERROR,
                        'debug' => [
                            'contact_id' => $contactId,
                            'original_company_id' => $contactInfo['COMPANY_ID'],
                            'update_result' => $updateResult
                        ]
                    ];
                }

            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'data' => 'Исключение при обработке контакта',
                    'error' => $e->getMessage(),
                    'debug' => [
                        'contact_id' => $contactId,
                        'original_company_id' => $contactInfo['COMPANY_ID'],
                        'exception_trace' => $e->getTraceAsString()
                    ]
                ];
            }
        } else {
            return [
                'success' => false,
                'data' => 'Контакт не существует'
            ];
        }
    }
}