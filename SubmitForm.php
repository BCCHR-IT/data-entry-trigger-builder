<?php

$data_entry_trigger_builder = new BCCHR\DataEntryTriggerBuilder\DataEntryTriggerBuilder();

$settings = $_POST;

$dest_project_pid = $settings["dest-project"];

foreach($settings["triggers"] as $index => $trigger_obj)
{
    $err = $data_entry_trigger_builder->validateSyntax($trigger);
    if (!empty($err))
    {
        $errors[$index]["trigger_errors"] = $trigger_errors;
    }

    if (!empty($trigger_obj["linkSourceEvent"]) && !$data_entry_trigger_builder->isValidEvent($trigger_obj["linkSourceEvent"]))
    {
        $errors[$index][$index]["linkSourceEvent"] = "Invalid event!";
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

    foreach($trigger_obj["pipingSourceEvents"] as $n => $fields)
    {
        foreach($fields as $i => $field)
        {
            if(!$data_entry_trigger_builder->isValidEvent($field))
            {
                $errors[$index]["pipingSourceEvents"][$n][$i] = "$field is an invalid event!";
            }
        }
    }

    foreach($trigger_obj["pipingSourceFields"] as $n => $fields)
    {
        foreach($fields as $i => $field)
        {
            if(!$data_entry_trigger_builder->isValidField($field))
            {
                $errors[$index]["pipingSourceFields"][$n][$i] = "$field is an invalid field!";
            }
        }
    }

    foreach($trigger_obj["pipingDestEvents"] as $n => $fields)
    {
        foreach($fields as $i => $field)
        {
            if(!$data_entry_trigger_builder->isValidEvent($field, $dest_project_pid))
            {
                $errors[$index]["pipingDestEvents"][$n][$i] = "$field is an invalid event!";
            }
        }
    }

    foreach($trigger_obj["pipingDestFields"] as $n => $fields)
    {
        foreach($fields as $i => $field)
        {
            if(!$data_entry_trigger_builder->isValidField($field, $dest_project_pid))
            {
                $errors[$index]["pipingDestFields"][$n][$i] = "$field is an invalid field!";
            }
        }
    }

    foreach($trigger_obj["setDestEvents"] as $n => $fields)
    {
        foreach($fields as $i => $field)
        {
            if(!$data_entry_trigger_builder->isValidEvent($field, $dest_project_pid))
            {
                $errors[$index]["setDestEvents"][$n][$i] = "$field is an invalid event!";
            }
        }
    }

    foreach($trigger_obj["setDestFields"] as $n => $fields)
    {
        foreach($fields as $i => $field)
        {
            if(!$data_entry_trigger_builder->isValidField($field, $dest_project_pid))
            {
                $errors[$index]["setDestFields"][$n][$i] = "$field is an invalid field!";
            }
        }
    }

    foreach($trigger_obj["sourceInstrEvents"] as $n => $fields)
    {
        foreach($fields as $i => $field)
        {
            if(!$data_entry_trigger_builder->isValidEvent($field))
            {
                $errors[$index]["sourceInstrEvents"][$n][$i] = "$field is an invalid event!";
            }
        }
    }

    foreach($trigger_obj["sourceInstr"] as $n => $fields)
    {
        foreach($fields as $i => $field)
        {
            if(!$data_entry_trigger_builder->isValidInstrument($field))
            {
                $errors[$index]["sourceInstr"][$n][$i] = "$field is an invalid instrument!";
            }
        }
    }

    foreach($trigger_obj["destInstrEvents"] as $n => $fields)
    {
        foreach($fields as $i => $field)
        {
            if(!$data_entry_trigger_builder->isValidEvent($field, $dest_project_pid))
            {
                $errors[$index]["destInstrEvents"][$n][$i] = "$field is an invalid event!";
            }
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