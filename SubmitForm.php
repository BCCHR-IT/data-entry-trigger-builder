<?php

$data_entry_trigger_builder = new BCCHR\DataEntryTriggerBuilder\DataEntryTriggerBuilder();

$settings = $_POST;

$dest_project_pid = $settings["dest-project"];

if (!empty($settings["linkSourceEvent"]) && !$data_entry_trigger_builder->isValidEvent($settings["linkSourceEvent"]))
{
    $errors["linkSourceEvent"] = "Invalid event!";
}

if (!$data_entry_trigger_builder->isValidField($settings["linkSource"]))
{
    $errors["linkSource"] = "Invalid field!";
}

if (!empty($settings["linkDestEvent"]) && !$data_entry_trigger_builder->isValidEvent($settings["linkDestEvent"], $dest_project_pid)) 
{
    $errors["linkDestEvent"] = "Invalid event!";
}

if (!$data_entry_trigger_builder->isValidField($settings["linkDest"], $dest_project_pid)) 
{
    $errors["linkDest"] = "Invalid field!";
}

foreach($settings["triggers"] as $index => $trigger)
{
    if (!empty($trigger))
    {
        $err = $data_entry_trigger_builder->validateSyntax($trigger);
        if (!empty($err))
        {
            $trigger_errors[$index] = $err;
        }
    }
}

foreach($settings["pipingSourceEvents"] as $index => $fields)
{
    foreach($fields as $i => $field)
    {
        if(!$data_entry_trigger_builder->isValidEvent($field))
        {
            $errors["pipingSourceEvents"][$index][$i] = "$field is an invalid event!";
        }
    }
}

foreach($settings["pipingSourceFields"] as $index => $fields)
{
    foreach($fields as $i => $field)
    {
        if(!$data_entry_trigger_builder->isValidField($field))
        {
            $errors["pipingSourceFields"][$index][$i] = "$field is an invalid field!";
        }
    }
}

foreach($settings["pipingDestEvents"] as $index => $fields)
{
    foreach($fields as $i => $field)
    {
        if(!$data_entry_trigger_builder->isValidEvent($field, $dest_project_pid))
        {
            $errors["pipingDestEvents"][$index][$i] = "$field is an invalid event!";
        }
    }
}

foreach($settings["pipingDestFields"] as $index => $fields)
{
    foreach($fields as $i => $field)
    {
        if(!$data_entry_trigger_builder->isValidField($field, $dest_project_pid))
        {
            $errors["pipingDestFields"][$index][$i] = "$field is an invalid field!";
        }
    }
}

foreach($settings["setDestEvents"] as $index => $fields)
{
    foreach($fields as $i => $field)
    {
        if(!$data_entry_trigger_builder->isValidEvent($field, $dest_project_pid))
        {
            $errors["setDestEvents"][$index][$i] = "$field is an invalid event!";
        }
    }
}

foreach($settings["setDestFields"] as $index => $fields)
{
    foreach($fields as $i => $field)
    {
        if(!$data_entry_trigger_builder->isValidField($field, $dest_project_pid))
        {
            $errors["setDestFields"][$index][$i] = "$field is an invalid field!";
        }
    }
}

foreach($settings["sourceInstrEvents"] as $index => $fields)
{
    foreach($fields as $i => $field)
    {
        if(!$data_entry_trigger_builder->isValidEvent($field))
        {
            $errors["sourceInstrEvents"][$index][$i] = "$field is an invalid event!";
        }
    }
}

foreach($settings["sourceInstr"] as $index => $fields)
{
    foreach($fields as $i => $field)
    {
        if(!$data_entry_trigger_builder->isValidInstrument($field))
        {
            $errors["sourceInstr"][$index][$i] = "$field is an invalid instrument!";
        }
    }
}

foreach($settings["destInstrEvents"] as $index => $fields)
{
    foreach($fields as $i => $field)
    {
        if(!$data_entry_trigger_builder->isValidEvent($field, $dest_project_pid))
        {
            $errors["destInstrEvents"][$index][$i] = "$field is an invalid event!";
        }
    }
}

if (!empty($trigger_errors))
{
    $errors["trigger_errors"] = $trigger_errors;
}

if (!empty($settings["surveyUrlEvent"]) && !$data_entry_trigger_builder->isValidEvent($settings["surveyUrlEvent"], $dest_project_pid))
{
    $errors["surveyUrlEvent"] = "Invalid event!";
}

if (!empty($settings["surveyUrl"]) && !$data_entry_trigger_builder->isValidInstrument($settings["surveyUrl"], $dest_project_pid))
{
    $errors["surveyUrl"] = "Invalid instrument!";
}

if (!empty($settings["saveUrlEvent"]) && !$data_entry_trigger_builder->isValidEvent($settings["saveUrlEvent"])) 
{
    $errors["saveUrlEvent"] = "Invalid event!";
}

if (!empty($settings["saveUrlField"]) && !$data_entry_trigger_builder->isValidField($settings["saveUrlField"])) 
{
    $errors["saveUrlField"] = "Invalid field!";
}

if (!empty($errors))
{
    print json_encode($errors);
}
else
{
    $data_entry_trigger_builder->setProjectSetting("det_settings", json_encode($settings));
    $data_entry_trigger_builder->setProjectSetting("saved_timestamp", date("Y-m-d H:i:s"));
    $data_entry_trigger_builder->setProjectSetting("saved_by", USERID);
    print json_encode(array("success" => true));
}