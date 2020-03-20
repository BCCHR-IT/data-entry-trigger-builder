<?php
$data_entry_trigger_builder = new BCCHR\DataEntryTriggerBuilder\DataEntryTriggerBuilder();
$fields = $data_entry_trigger_builder->retrieveProjectMetadata($_POST["pid"]);
print json_encode($fields);