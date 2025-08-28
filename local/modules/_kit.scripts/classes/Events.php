<?php

namespace Kit\Scripts;

class Events
{
    const DEBUG = true;

    public static function OnAfterCrmDealAdd($data)
    {
//        try {
//            (new Folders($data))->run();
//        } catch (\Exception $exception) {
//            switch ($exception->getCode()) {
//                case Folders::UNDEFINED_REQUIRED_PARAMS :
//                case Folders::INCORRECT_DEAL_TYPE_ERROR:
//                    if (DEBUG) print_r($exception->getMessage());
//                    break;
//                default:
//                    throw $exception;
//            }
//        }
    }

    public static function OnAfterCrmDealUpdate($data)
    {
//        try {
//            (new Folders($data))->run();
//        } catch (\Exception $exception) {
//            switch ($exception->getCode()) {
//                case Folders::UNDEFINED_REQUIRED_PARAMS :
//                case Folders::INCORRECT_DEAL_TYPE_ERROR:
//                    if (DEBUG) print_r($exception->getMessage());
//                    break;
//                default:
//                    throw $exception;
//            }
//        }
    }


    public static function OnAfterCrmLeadAdd($data)
    {

    }

    public static function OnBeforeCrmLeadUpdate($data)
    {
        try {
            (new CallMade($data))->run();
        } catch (\Exception $exception) {
            file_put_contents(__DIR__ . '/test4.log', print_r($exception->getMessage(), 1), FILE_APPEND);
        }
    }
}
