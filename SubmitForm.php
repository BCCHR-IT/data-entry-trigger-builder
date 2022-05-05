<?php

$data_entry_trigger_builder = new BCCHR\DataEntryTriggerBuilder\DataEntryTriggerBuilder();

$settings = $_POST;

$dest_project_pid = $settings["dest-project"];

foreach($settings["triggers"] as $index => $trigger)
{
    if ($trigger <> "")
    {
        $err = $data_entry_trigger_builder->validateSyntax($trigger);
        if (!empty($err))
        {
            $trigger_errors[$index] = $err;
        }
    }
}

foreach($settings["linkSourceEvent"] as $index => $field)
{
    if(!$data_entry_trigger_builder->isValidEvent($field))
    {
        $errors["linkSourceEvent"][$index][$i] = "$field is an invalid event!";
    }
}

foreach($settings["linkSource"] as $index => $field)
{
    if(!$data_entry_trigger_builder->isValidField($field))
    {
        $errors["linkSource"][$index][$i] = "$field is an invalid field!";
    }
}

foreach($settings["linkDestEvent"] as $index => $field)
{
    if(!$data_entry_trigger_builder->isValidEvent($field, $dest_project_pid))
    {
        $errors["linkDestEvent"][$index][$i] = "$field is an invalid event!";
    }
}

foreach($settings["linkDest"] as $index => $field)
{
    if(!$data_entry_trigger_builder->isValidField($field, $dest_project_pid))
    {
        $errors["linkDest"][$index][$i] = "$field is an invalid field!";
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

foreach($settings["surveyUrlEvent"] as $index => $field)
{
    if($field <> "" && !$data_entry_trigger_builder->isValidEvent($field, $dest_project_pid))
    {
        $errors["surveyUrlEvent"][$index][$i] = "$field is an invalid event!";
    }
}

foreach($settings["surveyUrl"] as $index => $field)
{
    if($field <> "" && !$data_entry_trigger_builder->isValidInstrument($field, $dest_project_pid))
    {
        $errors["surveyUrl"][$index][$i] = "$field is an invalid instrument!";
    }
}

foreach($settings["saveUrlEvent"] as $index => $field)
{
    if($field <> "" && !$data_entry_trigger_builder->isValidEvent($field))
    {
        $errors["saveUrlEvent"][$index][$i] = "$field is an invalid event!";
    }
}

foreach($settings["saveUrlField"] as $index => $field)
{
    if($field <> "" && !$data_entry_trigger_builder->isValidField($field))
    {
        $errors["saveUrlField"][$index][$i] = "$field is an invalid field!";
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
    $data_entry_trigger_builder->setProjectSetting("saved_timestamp", date("Y-m-d H:i:s"));
    $data_entry_trigger_builder->setProjectSetting("saved_by", USERID);
    print json_encode(array("success" => true));
}