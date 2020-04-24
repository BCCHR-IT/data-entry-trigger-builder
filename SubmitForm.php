<?php

$data_entry_trigger_builder = new BCCHR\DataEntryTriggerBuilder\DataEntryTriggerBuilder();

$settings = $_POST;

$dest_project_pid = $settings["dest-project"];
if (!empty($settings["linkSourceEvent"]) && !$data_entry_trigger_builder->isValidFieldOrEvent($settings["linkSourceEvent"]))
{
    $errors["linkSourceEvent"] = "Invalid field/event/instrument!";
}

if (!$data_entry_trigger_builder->isValidFieldOrEvent($settings["linkSource"]))
{
    $errors["linkSource"] = "Invalid field/event/instrument!";
}

if (!empty($settings["linkDestEvent"]) && !$data_entry_trigger_builder->isValidFieldOrEvent($settings["linkDestEvent"], $dest_project_pid)) 
{
    $errors["linkDestEvent"] = "Invalid field/event/instrument!";
}

if (!$data_entry_trigger_builder->isValidFieldOrEvent($settings["linkDest"], $dest_project_pid)) 
{
    $errors["linkDest"] = "Invalid field/event/instrument!";
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
        if(!$data_entry_trigger_builder->isValidFieldOrEvent($field))
        {
            $errors["pipingSourceEvents"][$index][$i] = "$field is an invalid event!";
        }
    }
}

foreach($settings["pipingSourceFields"] as $index => $fields)
{
    foreach($fields as $i => $field)
    {
        if(!$data_entry_trigger_builder->isValidFieldOrEvent($field))
        {
            $errors["pipingSourceFields"][$index][$i] = "$field is an invalid field!";
        }
    }
}

foreach($settings["pipingDestEvents"] as $index => $fields)
{
    foreach($fields as $i => $field)
    {
        if(!$data_entry_trigger_builder->isValidFieldOrEvent($field, $dest_project_pid))
        {
            $errors["pipingDestEvents"][$index][$i] = "$field is an invalid event!";
        }
    }
}

foreach($settings["pipingDestFields"] as $index => $fields)
{
    foreach($fields as $i => $field)
    {
        if(!$data_entry_trigger_builder->isValidFieldOrEvent($field, $dest_project_pid))
        {
            $errors["pipingDestFields"][$index][$i] = "$field is an invalid field!";
        }
    }
}

foreach($settings["setDestEvents"] as $index => $fields)
{
    foreach($fields as $i => $field)
    {
        if(!$data_entry_trigger_builder->isValidFieldOrEvent($field, $dest_project_pid))
        {
            $errors["setDestEvents"][$index][$i] = "$field is an invalid event!";
        }
    }
}

foreach($settings["setDestFields"] as $index => $fields)
{
    foreach($fields as $i => $field)
    {
        if(!$data_entry_trigger_builder->isValidFieldOrEvent($field, $dest_project_pid))
        {
            $errors["setDestFields"][$index][$i] = "$field is an invalid field!";
        }
    }
}

foreach($settings["sourceInstrEvents"] as $index => $fields)
{
    foreach($fields as $i => $field)
    {
        if(!$data_entry_trigger_builder->isValidFieldOrEvent($field, $dest_project_pid))
        {
            $errors["sourceInstrEvents"][$index][$i] = "$field is an invalid event!";
        }
    }
}

foreach($settings["sourceInstr"] as $index => $fields)
{
    foreach($fields as $i => $field)
    {
        if(!$data_entry_trigger_builder->isValidFieldOrEvent($field, $dest_project_pid))
        {
            $errors["sourceInstr"][$index][$i] = "$field is an invalid instrument!";
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