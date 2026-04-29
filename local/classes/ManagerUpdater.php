<?php
namespace OnlineService\Manager;

use OnlineService\Sync\ToSite\ManagerUserSync;

/**
 * Backward-compatible adapter for legacy handler name.
 */
class ManagerUpdater extends ManagerUserSync
{
    public static function OnAfterUserManagerUpdate(&$arFields): void
    {
        self::onAfterUserUpdate($arFields);
    }
}