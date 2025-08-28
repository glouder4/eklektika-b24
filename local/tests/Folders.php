<?php
require_once __DIR__ . '/testscript_root.php';

\CModule::IncludeModule('kit.scripts');

(new \Kit\Scripts\Folders(['ID' => 11823]))->run();