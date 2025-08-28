<?php

namespace Kit\Scripts;

use Exception;
use \Bitrix\Main\Loader;
use \CCrmDeal;
use \CCrmCompany;

class Folders
{
    const COMMON_STORAGE = "shared_files_s1";
    const UNDEFINED_REQUIRED_PARAMS = 1001;
    const INCORRECT_DEAL_TYPE_ERROR = 1002;

    private $changeDealData;
    private $diskDriver;
    private $storage;
    private $rootFolder;

    /**
     * @param $data includes ID - deal id, and COMPANY_ID
     * @throws Exception
     */
    public function __construct($data)
    {
        if (!Loader::includeModule('crm'))
            throw new Exception('crm not found');
        if (!Loader::includeModule('disk'))
            throw new Exception('disk not found');

        #throws UNDEFINED_REQUIRED_PARAMS
        $this->checkIdExistence($data);

        $this->changeDealData = $this->getDefaultCrmEntities($data['ID']);

        #throws UNDEFINED_REQUIRED_PARAMS
        $this->checkCompanyData($this->changeDealData);
        #throws INCORRECT_DEAL_TYPE_ERROR
        $this->checkDealType($this->changeDealData);

        $this->diskDriver = \Bitrix\Disk\Driver::getInstance();
        $this->storage = $this->diskDriver->getStorageByCommonId(self::COMMON_STORAGE);
        $this->rootFolder = $this->getRootFolder();
    }

    public function run()
    {
        $companyDir = $this->setupCompanyDir($this->rootFolder);
        $dealDir = $this->setupDealDir($companyDir);
        if ($dealDir === null)
            throw new Exception('Directory was not created');
        $this->updateDeal($this->changeDealData['ID']);
    }

    private function updateDeal(int $dealId)
    {
        if (!$dealId) throw new Exception('incorrect deal id');
        global $DB;
        $filePath = $_SERVER['HTTP_ORIGIN']
            . '/docs/shared/path/Templates/'
            . urlencode($this->getCompanyFolderName()) . '/'
            . urlencode($this->getDealFolderName()) . '/';

        $DB->Query("UPDATE b_uts_crm_deal SET UF_TEMPLATE='$filePath' WHERE VALUE_ID=$dealId");
    }

    private function getDefaultCrmEntities($dealId)
    {
        global $DB;
        $query = 'SELECT d.ID, d.CATEGORY_ID, d.COMPANY_ID, d.TITLE AS D_TITLE, c.TITLE as C_TITLE, ufd.UF_TEMPLATE ' .
            'FROM' .
            ' b_crm_deal d JOIN b_uts_crm_deal ufd ON d.ID=ufd.VALUE_ID' .
            ' LEFT JOIN b_crm_company c ON d.COMPANY_ID=c.ID' .
            " WHERE d.ID=$dealId";
        return $DB->Query(
            $query
        )->Fetch();
    }

    /**
     * @throws \Bitrix\Main\ArgumentException
     */
    private function getRootFolder()
    {
        $templatesFolder = $this->storage->getRootObject()->getChild(['=NAME' => 'Templates']);

        if ($templatesFolder === null)
            $templatesFolder = $this->storage->getRootObject()->addSubFolder(['NAME' => 'Templates']);

        return $templatesFolder;
    }

    public function setupCompanyDir($rootDir)
    {
        $companyDir = $rootDir->getChild(['=NAME' => $this->getCompanyFolderName()]);
        if ($companyDir === null)
            $companyDir = $rootDir->addSubFolder(['NAME' => $this->getCompanyFolderName()]);
        return $companyDir;
    }

    public function setupDealDir($companyDir)
    {
        $dealDir = $companyDir->getChild(['=NAME' => $this->getDealFolderName()]);
        if ($dealDir === null)
            $dealDir = $companyDir->addSubFolder(['NAME' => $this->getDealFolderName()]);
        return $dealDir;
    }

    public function getDealFolderName(): string
    {
        return $this->changeDealData['D_TITLE'] . '_D' . $this->changeDealData['ID'];
    }

    public function getCompanyFolderName(): string
    {
        return $this->changeDealData['C_TITLE'] . '_C' . $this->changeDealData['COMPANY_ID'];
    }

    # EXCEPTIONS
    private function checkIdExistence($changedData)
    {
        if (!isset($changedData['ID']) && $changedData['ID'] !== null)
            throw new Exception('ID (DEAL_ID) must be defined', self::UNDEFINED_REQUIRED_PARAMS);
    }

    private function checkCompanyData($companyData)
    {
        if ($companyData == null)
            throw new Exception('COMPANY_ID and ID (LEAD_ID) must be defined', self::UNDEFINED_REQUIRED_PARAMS);
    }

    private function checkDealType($dealData)
    {
        if ($dealData['CATEGORY_ID'] != 2)
            throw new Exception('Incorrect deal type', self::INCORRECT_DEAL_TYPE_ERROR);
    }
}

