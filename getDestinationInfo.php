<?php
$pid = $_POST["pid"];
$data_entry_trigger_builder = new BCCHR\DataEntryTriggerBuilder\DataEntryTriggerBuilder();
$fields = $data_entry_trigger_builder->retrieveProjectMetadata($pid);
$groups = $data_entry_trigger_builder->retrieveProjectGroups($pid);
$to_return = [
    "metadata" => $fields,
    "groups" => $groups
];
print json_encode($module->escape($to_return));