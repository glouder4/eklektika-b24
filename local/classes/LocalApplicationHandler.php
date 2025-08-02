<?php
namespace OnlineService;

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
            $filter['EMAIL'] = $fields['EMAIL'];
        }

// Добавляем поиск по телефону
        if (!empty($fields['PHONE'])) {
            $normalizedPhone = normalizePhone($fields['PHONE']);

            // Создаем подмассив для телефонов
            $phoneFilter = [
                'LOGIC' => 'OR',
                'PHONE' => $normalizedPhone,
                'WORK_PHONE' => $normalizedPhone,
                'MOBILE_PHONE' => $normalizedPhone
            ];

            // Для D7 API нужно добавить как подмассив
            $filter[] = $phoneFilter;
        }

        // Если нет критериев поиска
        if (count($filter) <= 2) { // Только CHECK_PERMISSIONS и LOGIC
            return [];
        }


        try {
            $contactResult = \CCrmContact::GetListEx(
                ['ID' => 'DESC'],
                $filter,
                false,
                ['nTopCount' => 1], // Берем только 1 контакт
                []
            );

            if ($contact = $contactResult->Fetch()) {
                // Сразу сохраняем ID контакта
                $contactId = $contact['ID'];

                // Получаем мультиполя через D7 API
                $multiFields = \Bitrix\Crm\FieldMultiTable::getList([
                    'filter' => [
                        '=ENTITY_ID' => \CCrmOwnerType::ContactName,
                        '=ELEMENT_ID' => $contactId,
                        '@TYPE_ID' => ['PHONE', 'EMAIL'] // Используем @ для IN-условия
                    ]
                ]);

                $contactData = [
                    'ID' => $contactId,
                    'NAME' => $contact['NAME'],
                    'LAST_NAME' => $contact['LAST_NAME'],
                    'PHONES' => [],
                    'EMAILS' => []
                ];

                // Обрабатываем мультиполя
                while ($field = $multiFields->fetch()) { // Используем fetch() вместо Fetch()
                    if ($field['TYPE_ID'] === 'PHONE') {
                        $contactData['PHONES'][] = $field['VALUE'];
                    } elseif ($field['TYPE_ID'] === 'EMAIL') {
                        $contactData['EMAILS'][] = $field['VALUE'];
                    }
                }

                // Проверяем соответствие критериям поиска
                $matchesSearch = false;

                // Проверка по ID (самая надежная)
                if (!empty($fields['ID'])) {
                    $matchesSearch = ($contactId == $fields['ID']);
                }
                // Проверка по email
                elseif (!empty($fields['EMAIL'])) {
                    $searchEmail = strtolower(trim($fields['EMAIL']));
                    foreach ($contactData['EMAILS'] as $email) {
                        if (strtolower(trim($email)) === $searchEmail) {
                            $matchesSearch = true;
                            break;
                        }
                    }
                }
                // Проверка по телефону
                elseif (!empty($fields['PHONE'])) {
                    $searchPhone = normalizePhone($fields['PHONE']);
                    foreach ($contactData['PHONES'] as $phone) {
                        if (normalizePhone($phone) === $searchPhone) {
                            $matchesSearch = true;
                            break;
                        }
                    }
                }

                return $matchesSearch ? $contactData : [];
            }

            return [];
        } catch (\Exception $e) {
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