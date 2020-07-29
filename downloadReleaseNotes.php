<?php

require_once "DataEntryTriggerBuilder.php";

$data_entry_trigger_builder = new BCCHR\DataEntryTriggerBuilder\DataEntryTriggerBuilder();
$settings = json_decode($data_entry_trigger_builder->getProjectSetting("det_settings"), true);
if (!empty($settings)) {
    $data_entry_trigger_builder->downloadReleaseNotes($settings);
}