<?php

namespace Kit\Scripts;

use Bitrix\Disk\Driver;
use Bitrix\Disk\Folder;
use Bitrix\Disk\Storage;
use Bitrix\Main\Loader;

class FolderStructure
{
    const COMMON_STORAGE = "shared_files_s1";
    const UNDEFINED_REQUIRED_PARAMS = 1001;
    const INCORRECT_DEAL_TYPE_ERROR = 1002;

    private Driver $diskDriver;
    private Storage $storage;
    private Folder $rootFolder;

    /**
     * @param $data includes ID - deal id, and COMPANY_ID
     * @throws Exception
     */
    public function __construct()
    {
        $this->checkModules();
        $this->diskDriver = \Bitrix\Disk\Driver::getInstance();
        $this->storage = $this->diskDriver->getStorageByCommonId(self::COMMON_STORAGE);
        $this->rootFolder = $this->getRootFolder();
    }

    public function checkModules() {
        if (!Loader::includeModule('crm'))
            throw new \Exception("module 'crm' not found");
        if (!Loader::includeModule('disk'))
            throw new \Exception("module 'disk' not found");
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

    public function createForDeal($dealId) {
        $dealRes = \CCrmDeal::GetList([], ['=ID' => $dealId]);

        if(!$deal = $dealRes->Fetch()) {
            throw new \Exception('Deal does`t exists!');
        }

        if(!isset($deal['COMPANY_ID']) || $deal['COMPANY_ID'] <= 0) {
            throw new \Exception('Company doesn`t specified!');
        }

        $companyRes = \CCrmCompany::GetList([], ['=ID' => $deal['COMPANY_ID']]);

        if(!$company = $companyRes->Fetch()) {
            throw new \Exception('Company does`t exists!');
        }

        $companyDir = $this->setupCompanyDir($this->rootFolder, $company);

        if ($companyDir === null)
            throw new \Exception('Company directory was not created');

        $dealDir = $this->setupDealDir($companyDir, $deal);

        if ($dealDir === null)
            throw new \Exception('Deal directory was not created');


        return $_SERVER['HTTP_ORIGIN']
            . '/docs/shared/path/Templates/'
            . rawurlencode($companyDir->getName()) . '/'
            . rawurlencode($dealDir->getName()) . '/';
    }

    public function setupCompanyDir($parentFolder, $company): Folder
    {
        $folderCode = \CCrmOwnerTypeAbbr::Company . '_' . $company['ID'];
        $folderName = $company['TITLE'];

        return $this->setupFolder($parentFolder, $folderName, $folderCode);
    }

    public function setupDealDir($parentFolder, $deal): Folder
    {
        $folderCode = \CCrmOwnerTypeAbbr::Deal . '_' . $deal['ID'];
        $folderName = $deal['TITLE'];

        return $this->setupFolder($parentFolder, $folderName, $folderCode);
    }

    public function setupFolder($parentFolder, $name, $code) {
        $name = str_replace(['"'], "”", $name);
        $existsFolder = $parentFolder->getChild(['=CODE' => $code]);

        if ($existsFolder === null)
            $existsFolder = $parentFolder->addSubFolder(['NAME' => $name, 'CODE' => $code]);

        return $existsFolder;
    }

    public function getDealFolderName(): string
    {
        return $this->changeDealData['D_TITLE'] . '_D' . $this->changeDealData['ID'];
    }

    public function getCompanyFolderName(): string
    {
        return $this->changeDealData['C_TITLE'] . '_C' . $this->changeDealData['COMPANY_ID'];
    }
}
