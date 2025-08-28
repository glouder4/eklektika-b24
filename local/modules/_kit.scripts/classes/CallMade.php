<?php

namespace Kit\Scripts;

use CCrmLead;

class CallMade
{
    private $modifiedLead;

    public function __construct($data)
    {
        $leadList = CCrmLead::GetList([], ['ID' => $data['ID']]);
        $this->modifiedLead = $leadList->fetch();
    }

    public function run()
    {
        if($this->getTimeLineData() > 0) {
            global $DB;
            $DB->Query('UPDATE b_uts_crm_lead SET UF_HAVE_CALL=1 WHERE VALUE_ID=' . $this->modifiedLead['ID']);
        }
    }

    public function getTimeLineData()
    {
        global $DB;
        $dbResult = $DB->Query('SELECT * FROM b_crm_act WHERE TYPE_ID=2 AND PROVIDER_ID="VOXIMPLANT_CALL" AND OWNER_TYPE_ID=1 AND OWNER_ID=' . $this->modifiedLead['ID']);
        return $dbResult->Fetch();
    }
}