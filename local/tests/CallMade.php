<?php

require_once __DIR__ . '/testscript_root.php';

CModule::IncludeModule('kit.scripts');

(new \Kit\Scripts\CallMade(['ID' => 34864]))->run();