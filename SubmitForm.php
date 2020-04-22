<?php
$data_entry_trigger_builder = new BCCHR\DataEntryTriggerBuilder\DataEntryTriggerBuilder();

$settings = $_POST;

$triggers = $settings["triggers"];
$dest_project_pid = $settings["dest-project"];

$trigger_errors = array();

foreach($triggers as $index => $trigger)
{
    if (!empty($trigger))
    {
        $errors = $data_entry_trigger_builder->validateSyntax($trigger);
        if (!empty($errors))
        {
            $trigger_errors[$index] = $errors;
        }
    }
}

if (!empty($trigger_errors))
{
    $errors["trigger_errors"] = $trigger_errors;
}

if (!empty($errors))
{
    print json_encode($errors);
}
else
{
    $data_entry_trigger_builder->setProjectSetting("det_settings", json_encode($settings));
    print json_encode(array("success" => true));
}