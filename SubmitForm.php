<?php
$data_entry_trigger_builder = new BCCHR\DataEntryTriggerBuilder\DataEntryTriggerBuilder();

$create_subject_trigger = $_POST["create-record-cond"];
$triggers = $_POST["triggers"];
$dest_project_pid = $_POST["dest-project"];

$create_subject_errors = $data_entry_trigger_builder->validateSyntax($create_subject_trigger);
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
    print json_encode(array("success" => true));
}