<?php

/**
 * Before sainv the DET settings, validate, and return any errors.
 */

$data_entry_trigger_builder = new BCCHR\DataEntryTriggerBuilder\DataEntryTriggerBuilder();

$settings = $_POST;

foreach($settings["triggers"] as $index => $trigger_obj)
{
    $dest_project_pid = $trigger_obj["dest-project"];

    $err = $data_entry_trigger_builder->validateSyntax($trigger_obj["trigger"]);
    if (!empty($err))
    {
        $errors[$index]["trigger_errors"] = $err;
    }

    if (!empty($trigger_obj["linkSourceEvent"]) && !$data_entry_trigger_builder->isValidEvent($trigger_obj["linkSourceEvent"]))
    {
        $errors[$index]["linkSourceEvent"] = "Invalid event!";
    }

    if (!$data_entry_trigger_builder->isValidField($trigger_obj["linkSource"]))
    {
        $errors[$index]["linkSource"] = "Invalid field!";
    }

    if (!empty($trigger_obj["linkDestEvent"]) && !$data_entry_trigger_builder->isValidEvent($trigger_obj["linkDestEvent"], $dest_project_pid)) 
    {
        $errors[$index]["linkDestEvent"] = "Invalid event!";
    }

    if (!$data_entry_trigger_builder->isValidField($trigger_obj["linkDest"], $dest_project_pid)) 
    {
        $errors[$index]["linkDest"] = "Invalid field!";
    }

    foreach($trigger_obj["pipingSourceEvents"] as $n => $field)
    {
        if(!$data_entry_trigger_builder->isValidEvent($field))
        {
            $errors[$index]["pipingSourceEvents"][$n] = "$field is an invalid event!";
        }
    }

    foreach($trigger_obj["pipingSourceFields"] as $n => $field)
    {
        if(!$data_entry_trigger_builder->isValidField($field))
        {
            $errors[$index]["pipingSourceFields"][$n] = "$field is an invalid field!";
        }
    }

    foreach($trigger_obj["pipingDestEvents"] as $n => $field)
    {
        if(!$data_entry_trigger_builder->isValidEvent($field, $dest_project_pid))
        {
            $errors[$index]["pipingDestEvents"][$n] = "$field is an invalid event!";
        }
    }

    foreach($trigger_obj["pipingDestFields"] as $n => $field)
    {
        if(!$data_entry_trigger_builder->isValidField($field, $dest_project_pid))
        {
            $errors[$index]["pipingDestFields"][$n] = "$field is an invalid field!";
        }
    }

    foreach($trigger_obj["setDestEvents"] as $n => $field)
    {
        if(!$data_entry_trigger_builder->isValidEvent($field, $dest_project_pid))
        {
            $errors[$index]["setDestEvents"][$n] = "$field is an invalid event!";
        }
    }

    foreach($trigger_obj["setDestFields"] as $n => $field)
    {
        if(!$data_entry_trigger_builder->isValidField($field, $dest_project_pid))
        {
            $errors[$index]["setDestFields"][$n] = "$field is an invalid field!";
        }
    }

    foreach($trigger_obj["sourceInstrEvents"] as $n => $field)
    {
        if(!$data_entry_trigger_builder->isValidEvent($field))
        {
            $errors[$index]["sourceInstrEvents"][$n] = "$field is an invalid event!";
        }
    }

    foreach($trigger_obj["sourceInstr"] as $n => $field)
    {
        if(!$data_entry_trigger_builder->isValidInstrument($field))
        {
            $errors[$index]["sourceInstr"][$n] = "$field is an invalid instrument!";
        }
    }

    foreach($trigger_obj["destInstrEvents"] as $n => $field)
    {
        if(!$data_entry_trigger_builder->isValidEvent($field, $dest_project_pid))
        {
            $errors[$index]["destInstrEvents"][$n] = "$field is an invalid event!";
        }
    }

    if (!empty($trigger_obj["surveyUrlEvent"]) && !$data_entry_trigger_builder->isValidEvent($trigger_obj["surveyUrlEvent"], $dest_project_pid))
    {
        $errors[$index]["surveyUrlEvent"] = "Invalid event!";
    }

    if (!empty($trigger_obj["surveyUrl"]) && !$data_entry_trigger_builder->isValidInstrument($trigger_obj["surveyUrl"], $dest_project_pid))
    {
        $errors[$index]["surveyUrl"] = "Invalid instrument!";
    }

    if (!empty($trigger_obj["saveUrlEvent"]) && !$data_entry_trigger_builder->isValidEvent($trigger_obj["saveUrlEvent"])) 
    {
        $errors[$index]["saveUrlEvent"] = "Invalid event!";
    }

    if (!empty($trigger_obj["saveUrlField"]) && !$data_entry_trigger_builder->isValidField($trigger_obj["saveUrlField"])) 
    {
        $errors[$index]["saveUrlField"] = "Invalid field!";
    }
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