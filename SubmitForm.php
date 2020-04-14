<?php
$data_entry_trigger_builder = new BCCHR\DataEntryTriggerBuilder\DataEntryTriggerBuilder();

if (!empty($_POST["json"]))
{
    $settings = json_decode(trim($_POST["json"]), true);
}
else
{
    $settings = $_POST;
    unset($settings["json"]);
}

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

if (!empty($create_subject_errors))
{
    $errors["create_subject_errors"] = $create_subject_errors;
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