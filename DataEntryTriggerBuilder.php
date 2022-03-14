<?php

namespace BCCHR\DataEntryTriggerBuilder;

require_once "vendor/autoload.php";

use Dompdf\Dompdf;
use REDCap;
use Project;

class DataEntryTriggerBuilder extends \ExternalModules\AbstractExternalModule 
{
    /**
     * Replaces all strings in $text with $replacement
     * So "Alice says 'hello'" becomes "Alice says ''" assuming $replacement = ''.
     * 
     * @access private
     * @param String $text          The text to replace.
     * @param String $replacement   The replacement text.
     * @return String A string with the replaced text.
     */
    private function replaceStrings($text, $replacement)
    {
        preg_match_all("/'/", $text, $quotes, PREG_OFFSET_CAPTURE);
        $quotes = $quotes[0];
        if (sizeof($quotes) % 2 === 0)
        {
            $i = 0;
            $to_replace = array();
            while ($i < sizeof($quotes))
            {
                $to_replace[] = substr($text, $quotes[$i][1], $quotes[$i + 1][1] - $quotes[$i][1] + 1);
                $i = $i + 2;
            }

            $text = str_replace($to_replace, $replacement, $text);
        }
        return $text;
    }

    /**
     * Parses a syntax string into blocks.
     * 
     * @access private
     * @param String $syntax    The syntax to parse.
     * @return Array            An array of blocks that make up the syntax passed.
     */
    private function getSyntaxParts($syntax)
    {
        $syntax = str_replace(array("['", "']"), array("[", "]"), $syntax);
        $syntax = $this->replaceStrings(trim($syntax), "''");         // Replace strings with ''

        $parts = array();
        $previous = array();

        $i = 0;
        while($i < strlen($syntax))
        {
            $char = $syntax[$i];
            switch($char)
            {
                case ",":
                case "(":
                case ")":
                case "]":
                    $part = trim(implode("", $previous));
                    $previous = array();
                    if ($part !== "")
                    {
                        $parts[] = $part;
                    }
                    $parts[] = $char;
                    $i++;
                    break;
                case "[":
                    $part = trim(implode("", $previous));
                    if ($part !== "")
                    {
                        $parts[] = $part;
                    }
                    $parts[] = $char;
                    $previous = array();
                    $i++;
                    break;
                case " ":
                    $part = trim(implode("", $previous));
                    $previous = array();
                    if ($part !== "")
                    {
                        $parts[] = $part;
                    }
                    $i++;
                    break;
                default:
                    $previous[] = $char;
                    if ($i == strlen($syntax) - 1)
                    {
                        $part = trim(implode("", $previous));
                        if ($part !== "")
                        {
                            $parts[] = $part;
                        }
                    }
                    $i++;
                    break;
            }
        }

        return $parts;
    }

    /**
     * Checks whether a field exists within a project.
     * 
     * @param String $var       The field to validate
     * @param String $pid       The project id the field supposedly belongs to. Use current project if null.
     * @return Boolean          true if field exists, false otherwise.
     */
    public function isValidField($var, $pid = null)
    {
        $var = trim($var, "'");
        
        if ($pid != null) {
            $data_dictionary = REDCap::getDataDictionary($pid, 'array');
        }
        else {
            $data_dictionary = REDCap::getDataDictionary('array');
        }

        $fields = array_keys($data_dictionary);

        $external_fields = array();
        $instruments = array_unique(array_column($data_dictionary, "form_name"));
        foreach ($instruments as $unique_name)
        {   
            $external_fields[] = "{$unique_name}_complete";
        }

        $checkbox_values = array();
        foreach($data_dictionary as $field_name => $data)
        {
            if ($data["field_type"] == "checkbox")
            {
                $choices = explode("|", $data["select_choices_or_calculations"]);
                foreach($choices as $choice)
                {
                    $choice = trim($choice);
                    $code = trim(substr($choice, 0, strpos($choice, ",")));
                    $checkbox_values[] = "{$field_name}___{$code}";
                }
            }
        }

        return in_array($var, $external_fields) || in_array($var, $fields)  || in_array($var, $checkbox_values);
    }

    /**
     * Checks whether a event exists within a project.
     * 
     * @param String $var       The event to validate
     * @param String $pid       The project id the event supposedly belongs to. Use current project if null.
     * @return Boolean          true if event exists, false otherwise.
     */
    public function isValidEvent($var, $pid = null)
    {
        $var = trim($var, "'");
        $Proj = new Project($pid);
        $events = array_values($Proj->getUniqueEventNames());
        return in_array($var, $events);
    }

    /**
     * Checks whether a instrument exists within a project.
     * 
     * @param String $var       The instrument to validate
     * @param String $pid       The project id the instrument supposedly belongs to. Use current project if null.
     * @return Boolean          true if instrument exists, false otherwise.
     */
    public function isValidInstrument($var, $pid = null)
    {
        $var = trim($var, "'");
        
        if ($pid != null) {
            $data_dictionary = REDCap::getDataDictionary($pid, 'array');
        }
        else {
            $data_dictionary = REDCap::getDataDictionary('array');
        }

        $instruments = array_unique(array_column($data_dictionary, "form_name"));
        
        return in_array($var, $instruments);
    }

    /**
     * Validate syntax.
     * 
     * @access private
     * @see Template::getSyntaxParts()  For retreiving blocks of syntax from the given syntax string.
     * @param String $syntax            The syntax to validate.
     * @since 1.0
     * @return Array                    An array of errors.
     */
    public function validateSyntax($syntax)
    {
        $errors = array();

        $logical_operators =  array("==", "<>", "!=", ">", "<", ">=", ">=", "<=", "<=", "||", "&&", "=");
        
        $parts = $this->getSyntaxParts($syntax);

        $opening_squares = array_keys($parts, "[");
        $closing_squares = array_keys($parts, "]");

        $opening_parenthesis = array_keys($parts, "(");
        $closing_parenthesis = array_keys($parts, ")");

        // Check symmetry of ()
        if (sizeof($opening_parenthesis) != sizeof($closing_parenthesis))
        {
            $errors[] = "<b>ERROR</b>Odd number of parenthesis (. You've either added an extra parenthesis, or forgot to close one.";
        }

        // Check symmetry of []
        if (sizeof($opening_squares) != sizeof($closing_squares))
        {
            $errors[] = "Odd number of square brackets [. You've either added an extra bracket, or forgot to close one.";
        }

        foreach($parts as $index => $part)
        {
            switch ($part) {
                case "(":
                    $previous = $parts[$index - 1];
                    $next_part = $parts[$index + 1];
                
                    if ($next_part !== "(" 
                        && $next_part !== ")" 
                        && $next_part !== "["
                        && !is_numeric($next_part)
                        && $next_part[0] != "'" 
                        && $next_part[0] != "\""
                        && $next_part[strlen($next_part) - 1] != "'" 
                        && $next_part[strlen($next_part) - 1] != "\"")
                    {
                        $errors[] = "Invalid <strong>$next_part</strong> after <strong>(</strong>.";
                    }
                    break;
                case ")":
                    // Must have either a ), ] or logical operator after, if not the last part of syntax
                    if ($index != sizeof($parts) - 1)
                    {
                        $next_part = $parts[$index + 1];
                        if ($next_part !== ")" && $next_part !== "]" && !in_array($next_part, $logical_operators))
                        {
                            $errors[] = "Invalid <strong>$next_part</strong> after <strong>)</strong>.";
                        }
                    }
                    break;
                case "==":
                case "<>": 
                case "!=":
                case ">":
                case "<":
                case ">=":
                case ">=":
                case "<=":
                case "<=":
                case "=":
                    if ($index == 0)
                    {
                        $errors[] = "Cannot have a comparison operator <strong>$part</strong> as the first part in syntax.";
                    }
                    else if ($index != sizeof($parts) - 1)
                    {
                        $previous = $parts[$index - 2];
                        $next_part = $parts[$index + 1];

                        if (in_array($previous, $logical_operators) && $previous !== "or" && $previous !== "and")
                        {
                            $errors[] = "Invalid <strong>$part</strong>. You cannot chain comparison operators together, you must use an <strong>and</strong> or an <strong>or</strong>";
                        }

                        if (!empty($next_part) 
                            && !is_numeric($next_part)
                            && $next_part[0] != "'" 
                            && $next_part[0] != "\""
                            && $next_part[strlen($next_part) - 1] != "'" 
                            && $next_part[strlen($next_part) - 1] != "\"")
                        {
                            $errors[] = "Invalid <strong>$next_part</strong> after <strong>$part</strong>.";
                        }
                    }
                    else
                    {
                        $errors[] = "Cannot have a comparison operator <strong>$part</strong> as the last part in syntax.";
                    }
                    break;
                case "||":
                case "&&":
                    if ($index == 0)
                    {
                        $errors[] = "Cannot have a logical operator <strong>$part</strong> as the first part in syntax.";
                    }
                    else if ($index != sizeof($parts) - 1)
                    {
                        $next_part = $parts[$index + 1];
                        if (!empty($next_part) 
                            && $next_part !== "(" 
                            && $next_part !== "[")
                        {
                            $errors[] = "Invalid <strong>$next_part</strong> after <strong>$part</strong>.";
                        }
                    }
                    else
                    {
                        $errors[] = "Cannot have a logical operator <strong>$part</strong> as the last part in syntax.";
                    }
                    break;
                case "[":
                    break;
                case "]":
                    // Must have either a logical operator or ) or [ after, if not last item in syntax
                    if ($index != sizeof($parts) - 1)
                    {
                        $previous_2 = $parts[$index - 2];
                        $previous_5 = $parts[$index - 5];
                        $next_part = $parts[$index + 1];

                        if ($previous_2 !== "[" && $previous_5 !== "[") // Make sure it has an opening bracket. Proper syntax should be [, field_name, ], or [, field_name, (, code, ), ]
                        {
                            $errors[] = "Unclosed or empty <strong>]</strong> bracket.";
                        }

                        if ($next_part !== ")" 
                            && $next_part !== "["
                            && !in_array($next_part, $logical_operators))
                        {
                            $errors[] = "Invalid <strong>'$next_part'</strong> after <strong>$part</strong>.";
                        }
                    }
                    break;
                default:
                    // Check if it's a field or event
                    if ($part[0] != "'" && 
                        $part[0] != "\"" && 
                        $part[strlen($part) - 1] != "'" && 
                        $part[strlen($part) - 1] != "\"" &&
                        !is_numeric($part) && 
                        !empty($part) && 
                        ($this->isValidField($part) == false && $this->isValidEvent($part) == false))
                    {
                        $errors[] = "<strong>$part</strong> is not a valid event/field in this project. If this is a checkbox field please use the following format: field_name<strong>(</strong>code<strong>)</strong>";
                    }
                    break;
            }
        }

        return $errors;
    }
    
    /**
     * Retrieve the following for all REDCap projects: ID, & title
     * 
     * @return Array    An array of rows pulled from the database, each containing a project's information.
     */
    public function getProjects()
    {
        $query = $this->framework->createQuery();
        $query->add("select project_id, app_title from redcap_projects", []);

        if ($query_result = $query->execute())
        {
            while($row = $query_result->fetch_assoc())
            {
                $projects[] = $row;
            }
        }
        return $projects;
    }

    /**
     * Retrieves a project's fields
     * 
     * @param String $pid   A project's id in REDCap.
     * @return String       A JSON encoded string that contains all the event, instruments, and fields for a project. 
     */
    public function retrieveProjectMetadata($pid)
    {
        if (!empty($pid))
        {
            $metadata = REDCap::getDataDictionary($pid, "array");
            $instruments = array_values(array_unique(array_column($metadata, "form_name")));
            $Proj = new Project($pid);
            $events = array_values($Proj->getUniqueEventNames());
            $isLongitudinal = $Proj->longitudinal;

            /**
             * We can pipe over any data except descriptive, file, and signature fields. 
             * 
             * NOTE: For calculation fields only the raw data can be imported/exported.
             */
            foreach($metadata as $field_name => $data)
            {
                if ($data["field_type"] == "checkbox")
                {
                    $choices = explode("|", $data["select_choices_or_calculations"]);
                    foreach($choices as $choice)
                    {
                        $choice = trim($choice);
                        $code = trim(substr($choice, 0, strpos($choice, ",")));
                        $fields[] = "{$field_name}___{$code}";
                    }

                }
                if ($data["field_type"] != "descriptive" && $data["field_type"] != "signature" && $data["field_type"] != "file")
                {
                    $fields[] = $field_name;
                }
            }

            /**
             * Add form completion status fields to push
             */
            foreach($instruments as $instrument)
            {
                $fields[] = $instrument . "_complete";
            }

            return ["fields" => $fields, "events" => $events, "isLongitudinal" => $isLongitudinal, "instruments" => $instruments];
        }
        return FALSE;
    }

    /**
     * REDCap hook is called immediately after a record is saved. Will retrieve the DET settings,
     * & import data according to DET.
     */
    public function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        if ($project_id == $this->getProjectId())
        {
            // Get DET settings
            $settings = json_decode($this->getProjectSetting("det_settings"), true);
            
            $dest_project = $settings["dest-project"];
            $create_record_trigger = $settings["create-record-cond"];

            $link_source_event = $settings["linkSourceEvent"];
            $link_source = $settings["linkSource"];

            $link_dest_event = $settings["linkDestEvent"];
            $link_dest_field = $settings["linkDest"];

            $triggers = $settings["triggers"];

            $piping_source_events = $settings["pipingSourceEvents"];
            $piping_dest_events = $settings["pipingDestEvents"];

            $piping_source_fields = $settings["pipingSourceFields"];
            $piping_dest_fields = $settings["pipingDestFields"];

            $set_dest_events = $settings["setDestEvents"];
            $set_dest_fields = $settings["setDestFields"];
            $set_dest_fields_values = $settings["setDestFieldsValues"];

            $source_instruments_events = $settings["sourceInstrEvents"];
            $source_instruments = $settings["sourceInstr"];

            $overwrite_data = $settings["overwrite-data"];
            $import_dags = $settings["import-dags"];
            
            // Get current record data
            $record_data = json_decode(REDCap::getData("json", $record, null, null, null, false, $import_dags), true);

            /**
             * Process each trigger, and, if true, prepare associated data to move.
             */
            foreach($triggers as $index => $trigger)
            {
                $valid = REDCap::evaluateLogic($trigger, $project_id, $record); // REDCap class method to evaluate conditional logic.
                if ($valid === null) // Null returned if logic is invalid. Else a boolean value.
                {
                    REDCap::logEvent("DET: Trigger was either syntactically incorrect, or parameters were invalid (e.g., record or event does not exist). No data moved.", "Trigger: $trigger", null, $record, $event_id, $project_id);
                }
                else if ($valid)
                {
                    $trigger_source_fields = $piping_source_fields[$index];
                    $trigger_source_events = $piping_source_events[$index];

                    $trigger_dest_fields = $piping_dest_fields[$index];
                    $trigger_dest_events = $piping_dest_events[$index];

                    /**
                     * Move field data from source to destination
                     */
                    foreach($trigger_dest_fields as $i => $dest_field)
                    {
                        if (!empty($trigger_source_events[$i]))
                        {
                            $source_event = $trigger_source_events[$i];
                            $key = array_search($source_event, array_column($record_data, "redcap_event_name"));
                            $data = $record_data[$key];
                        }
                        else
                        {
                            $data = $record_data[0]; // Takes data from first event
                        }

                        if (!empty($trigger_dest_events[$i]))
                        {
                            $dest_event = $trigger_dest_events[$i];
                        }
                        else // Assume classic project
                        {
                            $dest_event = "classic";
                        }
                        
                        if ($dest_event != "classic" && empty($dest_record_data[$dest_event]))
                        {
                            $event_data = ["redcap_event_name" => $dest_event];
                        }
                        else if ($dest_event == "classic" && empty($dest_record_data[$dest_event]))
                        {
                            $event_data = [];
                        }
                        else
                        {
                            $event_data = $dest_record_data[$dest_event];
                        }

                        $source_field = $trigger_source_fields[$i];
                        $event_data[$dest_field] = $data[$source_field];
                        $dest_record_data[$dest_event] = $event_data;
                    }

                    /**
                     * Set destination fields as custom value
                     */
                    $trigger_dest_fields = $set_dest_fields[$index];
                    $trigger_dest_values = $set_dest_fields_values[$index];
                    $trigger_dest_events = $set_dest_events[$index];

                    foreach($trigger_dest_fields as $i => $dest_field)
                    {
                        if (!empty($trigger_dest_events[$i]))
                        {
                            $dest_event = $trigger_dest_events[$i];
                        }
                        else // Assume classic project
                        {
                            $dest_event = "classic";
                        }

                        if ($dest_event != "classic" && empty($dest_record_data[$dest_event]))
                        {
                            $event_data = ["redcap_event_name" => $dest_event];
                        }
                        else if ($dest_event == "classic" && empty($dest_record_data[$dest_event]))
                        {
                            $event_data = [];
                        }
                        else
                        {
                            $event_data = $dest_record_data[$dest_event];
                        }

                        $event_data[$dest_field] = $trigger_dest_values[$i];
                        $dest_record_data[$dest_event] = $event_data;
                    }

                    /**
                     * Move source instruments to destination instruments (Is a one-to-one relationship).
                     */
                    $trigger_source_instruments = $source_instruments[$index];
                    $trigger_source_instruments_events = $source_instruments_events[$index];

                    foreach($trigger_source_instruments as $i => $source_instrument)
                    {
                        if (!empty($trigger_source_instruments_events[$i]))
                        {
                            $dest_evet = $trigger_source_instruments_events[$i];
                        }
                        else // Assume source is classic project. Data will move into the first event of the destination project.
                        {
                            $Proj = new Project($dest_project);
                            $dest_events = $Proj->getUniqueEventNames();
                            if (sizeof($dest_events) == 1) // Destination is also a classic project
                            {
                                $dest_event = "classic";
                            }
                            else
                            {
                                $first_event_id = min(array_keys($dest_events));
                                $dest_event = $dest_events[$first_event_id];
                            }
                        }

                        if ($dest_event != "classic" && empty($dest_record_data[$dest_event]))
                        {
                            $event_data = ["redcap_event_name" => $dest_event];
                        }
                        else if ($dest_event == "classic" && empty($dest_record_data[$dest_event]))
                        {
                            $event_data = [];
                        }
                        else
                        {
                            $event_data = $dest_record_data[$dest_event];
                        }

                        // Fields are returned in the order they are in the REDCap project
                        $source_instrument_fields = REDCap::getFieldNames($source_instrument);
                        $source_instrument_data = json_decode(REDCap::getData("json", $record, $source_instrument_fields, $dest_event == "classic" ? null : $dest_event), true);

                        if (sizeof($source_instrument_data) > 0)
                        {
                            $event_data = $event_data + $source_instrument_data[0];
                            $dest_record_data[$dest_event] = $event_data;
                        }
                    }
                }
            }

            if (!empty($dest_record_data)) {
                // Check if the linking id field is the same as the record id field.
                $dest_record_id = $this->framework->getRecordIdField($dest_project);
                if ($dest_record_id != $link_dest_field)
                {
                    /**
                     * Check for existing record, otherwise create a new one. Assume linking ID is unique.
                     */

                    // Search for the index of the linking id's event. If not found, then assume it's a classical project and that the index for the first event is 0.
                    if (!empty($link_source_event))
                    {
                        $key = array_search($link_source_event, array_column($record_data, "redcap_event_name"));
                    }
                    else
                    {
                        $key = 0;
                    }

                    $data = $record_data[$key];
                    $link_dest_value = $data[$link_source];

                    if (!empty($settings["prefixPostfixStr"]))
                    {
                        if ($settings["prefixOrPostfix"] == "post")
                            $link_dest_value .= $settings["prefixPostfixStr"];
                        else
                            $link_dest_value = $settings["prefixPostfixStr"] . $link_dest_value;
                    }

                    // Set linking id
                    if (!empty($link_dest_event))	
                    {	
                        $dest_record_data[$link_dest_event][$link_dest_field] = $link_dest_value;	
                    }	
                    else // Assume classic project	
                    {	
                        $dest_record_data["classic"][$link_dest_field] = $link_dest_value;
                    }

                    // Retrieve record id. Exit if there is no value for the linking field, as it should be filled and never change.
                    if (empty($link_dest_value))
                    {
                        REDCap::logEvent("DET: Linking field value is empty, so no data moved", "Filter logic: [$link_dest_field] = ''", null, $record, $event_id, $project_id);
                        return;
                    }
                    else 
                    {
                        $filter_logic = "[$link_dest_field] = '$link_dest_value'";
                        $existing_record = REDCap::getData($dest_project, "json", null, $dest_record_id, $link_dest_event, null, false, false, false, $filter_logic);
                        $existing_record = json_decode($existing_record, true);

                        if (sizeof($existing_record) == 0)
                        {
                            $dest_record = $this->framework->addAutoNumberedRecord($dest_project);
                        }
                        else
                        {
                            $dest_record = $existing_record[0][$dest_record_id];
                        }
                    }
                }
                else
                {
                    $dest_record = $record_data[0][$link_source];
                    if (!empty($settings["prefixPostfixStr"]))
                    {
                        if ($settings["prefixOrPostfix"] == "post")
                            $dest_record .= $settings["prefixPostfixStr"];
                        else
                            $dest_record = $settings["prefixPostfixStr"] . $dest_record;
                    }
                }
                
                // Set record_id, and redcap_data_access_group if $import_dags is true
                foreach ($dest_record_data as $i => $data)
                {
                    $dest_record_data[$i][$dest_record_id] = $dest_record;
                    if ($import_dags)
                    {
                        $dest_record_data[$i]["redcap_data_access_group"] = $record_data[0]["redcap_data_access_group"];
                    }
                }

                $dest_record_data = array_values($dest_record_data); // Don't need the keys to push, only the values.
            }
            
            if (!empty($dest_record_data))
            {
                // Save DET data in destination project;
                $save_response = REDCap::saveData($dest_project, "json", json_encode($dest_record_data), $overwrite_data);

                if (!empty($save_response["errors"]))
                {
                    REDCap::logEvent("DET: Errors", json_encode($save_response["errors"]), null, $record, $event_id, $project_id);
                }
                else
                {
                    REDCap::logEvent("DET: Ran successfully", "Data was successfully imported from project $project_id to project $dest_project", null, $record, $event_id, $project_id);
                }

                if (!empty($save_response["warnings"]))
                {
                    REDCap::logEvent("DET: Ran sucessfully with Warnings", json_encode($save_response["warnings"]), null, $record, $event_id, $project_id);
                }

                if (!empty($save_response["ids"]))
                {
                    REDCap::logEvent("DET: Modified/Saved the following records", json_encode($save_response["ids"]), null, null, null, $dest_project);
                }

                /**
                 * If data was saved without errors then generate survey link and save it to the source project 
                 **/

                if (empty($save_response["errors"]))
                {
                    $survey_url_event = $settings["surveyUrlEvent"];
                    $survey_url_instrument = $settings["surveyUrl"];
                    $save_url_event = $settings["saveUrlEvent"];
                    $save_url_field = $settings["saveUrlField"];

                    if (!empty($survey_url_instrument) && !empty($save_url_field))
                    {
                        if (!empty($survey_url_event))
                        {
                            if (!isset($Proj))
                            {
                                $Proj = new Project($dest_project);
                                $dest_events = $Proj->getUniqueEventNames();
                            }
                            $survey_event_id = array_search($survey_url_event, $dest_events);
                        }
                        else
                        {
                            $survey_event_id = null;
                        }

                        $survey_url = REDCap::getSurveyLink($dest_record, $survey_url_instrument, $survey_event_id, 1, $dest_project);
                        
                        if (is_null($survey_url))
                        {
                            REDCap::logEvent("DET: Errors", "Survey url couldn't be generated. Please check your parameters for REDCap::getSurveyLink()\n\nProject = $dest_project\nRecord = $dest_record\nInstrument = $survey_url_instrument\nEvent ID = " . (is_null($survey_event_id) ? "null" : $survey_event_id), null, $record, $event_id, $project_id);
                        }
                        else
                        {
                            $record_id_field = REDCap::getRecordIdField();

                            $save_url_data = [
                                $record_id_field => $record,
                                $save_url_field => $survey_url,
                                "redcap_event_name" => empty($save_url_event) ? "" : $save_url_event
                            ];

                            $save_response = REDCap::saveData($project_id, "json", json_encode(array($save_url_data)));

                            if (!empty($save_response["errors"]))
                            {
                                REDCap::logEvent("DET: Errors", "Unable to save survey url to $save_url_field. Received the following errors: " . json_encode($save_response["errors"]), null, $record, $event_id, $project_id);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Function to create and download release notes from settings in the DET Builder.
     */
    public function downloadReleaseNotes($settings) 
    {
        $sourceProjectTitle = REDCap::getProjectTitle();

        $query = $this->framework->createQuery();
        $query->add("select app_title from redcap_projects where project_id = ?", [$settings["dest-project"]]);

        if ($query_result = $query->execute())
        {
            while($row = $query_result->fetch_assoc())
            {
                $destProjectTitle = $row["app_title"];
            }
        }

        // Creating the new document...
        $phpWord = new \PhpOffice\PhpWord\PhpWord();

        $phpWord->getSettings()->setUpdateFields(true); // Forces document to update and set page numbers when first opened, as page numbers are missing from TOC, because of a bug.

        // Add styling
        $phpWord->addFontStyle(
            "generalFontStyle",
            array('name' => 'Calibri', 'size' => 11, 'color' => 'black')
        );
        $phpWord->addNumberingStyle(
            'hNum',
            array('type' => 'multilevel', 'levels' => array(
                array('pStyle' => 'Heading1', 'format' => 'decimal', 'text' => '%1'),
                array('pStyle' => 'Heading2', 'format' => 'decimal', 'text' => '%1.%2'),
                array('pStyle' => 'Heading3', 'format' => 'decimal', 'text' => '%1.%2.%3'),
                )
            )
        );
        $phpWord->addTitleStyle(
            1,
            array('name' => 'Calibri Light', 'size' => 18, 'color' => 'black', 'bold' => true),
            array('numStyle' => 'hNum', 'numLevel' => 0)
        );
        $phpWord->addFontStyle(
            "titleFontStyle",
            array('name' => 'Calibri Light', 'size' => 18, 'color' => 'black', 'bold' => true)
        );
        $phpWord->addFontStyle(
            "triggerFontStyle",
            array('name' => 'Calibri', 'size' => 11, 'color' => 'black', 'bold' => true)
        );
        $phpWord->addParagraphStyle(
            "centerParagraphStyle",
            array("align" => \PhpOffice\PhpWord\SimpleType\Jc::CENTER)
        );
        $phpWord->addFontStyle(
            "headerFontStyle",
            array('name' => 'Times New Roman', 'size' => 18, 'color' => 'black', 'italic' => true)
        );
        $phpWord->addParagraphStyle(
            "rightParagraphStyle",
            array("align" => \PhpOffice\PhpWord\SimpleType\Jc::END)
        );
        $phpWord->addTableStyle(
            "fieldInstrTableStyle", 
            array("width" => 100 * 50, "unit" => \PhpOffice\PhpWord\SimpleType\TblWidth::PERCENT, "borderSize" => 1, "borderColor" => 000000)
        );
        $phpWord->addFontStyle(
            "titleFontStyle",
            array('name' => 'Calibri Light', 'size' => 18, 'color' => 'black', 'bold' => true, 'underline' => \PhpOffice\PhpWord\Style\Font::UNDERLINE_DASH)
        );
        $phpWord->addFontStyle(
            "boldFontStyle",
            array('name' => 'Calibri Light', 'size' => 11, 'color' => 'black', 'bold' => true)
        );
        $phpWord->addNumberingStyle(
            'multilevel',
            array(
                'type' => 'multilevel',
                'levels' => array(
                    array('format' => 'decimal', 'text' => '%1.', 'left' => 360, 'hanging' => 360, 'tabPos' => 360),
                    array('format' => 'upperLetter', 'text' => '%2.', 'left' => 720, 'hanging' => 360, 'tabPos' => 720),
                )
            )
        );
        $lineStyle = array('weight' => 1, 'width' => 450, 'height' => 0, 'color' => 000000);
        $cellStyle = array("bgColor" => 'D3D3D3');
        $tableStyle = array("width" => 100 * 50, "unit" => \PhpOffice\PhpWord\SimpleType\TblWidth::PERCENT, "borderSize" => 1, "borderColor" => 000000);

        /* Note: any element you append to a document must reside inside of a Section. */

        // Title Page
        $title_section = $phpWord->addSection();
        $header = $title_section->addHeader();
        $header->addText("Release Notes", "headerFontStyle", "rightParagraphStyle");
        $title_section->addText($sourceProjectTitle, "titleFontStyle", "centerParagraphStyle");
        $title_section->addText("Conducted by", "generalFontStyle", "centerParagraphStyle");
        $title_section->addText("Principal Investigator, Title, Affiliation", "generalFontStyle", "centerParagraphStyle");

        $title_section->addTextBreak();
        $title_section->addText("Document History", "boldFontStyle", "centerParagraphStyle");

        $doc_history_table = $title_section->addTable($tableStyle);

        $doc_history_table->addRow();
        $doc_history_table->addCell(1750)->addText("Version", array('bold' => true));
        $doc_history_table->addCell(1750)->addText("Changes Made", array('bold' => true));
        $doc_history_table->addCell(1750)->addText("Effective Date", array('bold' => true));

        $doc_history_table->addRow();
        $doc_history_table->addCell(1750)->addText("1", "generalFontStyle");
        $doc_history_table->addCell(1750)->addText($this->getProjectSetting("saved_by"), "generalFontStyle");
        $doc_history_table->addCell(1750)->addText($this->getProjectSetting("saved_timestamp"), "generalFontStyle");

        $title_section->addTextBreak();
        $title_section->addTextBreak();

        $app_table = $title_section->addTable($tableStyle);

        $app_table->addRow();
        $app_table->addCell(1750)->addText("Application Initial Version", array('bold' => true));
        $app_table->addCell(1750)->addText("");

        $app_table->addRow();
        $app_table->addCell(1750)->addText($sourceProjectTitle, "generalFontStyle");
        $app_table->addCell(1750)->addText("", "generalFontStyle");

        $app_table->addRow();
        $app_table->addCell(1750)->addText("Project ID", "generalFontStyle");
        $app_table->addCell(1750)->addText($this->getProjectId(), "generalFontStyle");

        $app_table->addRow();
        $app_table->addCell(1750)->addText($destProjectTitle, "generalFontStyle");
        $app_table->addCell(1750)->addText("", "generalFontStyle");

        $app_table->addRow();
        $app_table->addCell(1750)->addText("Project ID", "generalFontStyle");
        $app_table->addCell(1750)->addText($settings["dest-project"], "generalFontStyle");

        $app_table->addRow();
        $app_table->addCell(1750)->addText("Accessible", "generalFontStyle");
        $app_table->addCell(1750)->addText("World Wide Web", "generalFontStyle");

        // Table of Contents
        $toc_section = $phpWord->addSection();
        $header = $toc_section->addHeader();
        $header->addText("Release Notes", "headerFontStyle", "rightParagraphStyle");
        $toc_section->addText("Table of Contents", "titleFontStyle");
        $toc_section->addLine($lineStyle);
        $toc_section->addTOC(array('name' => 'Calibri', 'size' => 11, 'color' => 'black'));

        $section = $phpWord->addSection();
    
        // Add Header
        $header = $section->addHeader();
        $header->addText("Release Notes", "headerFontStyle", "rightParagraphStyle");

        // Purpose
        $section->addTitle("Purpose", 1);
        $section->addLine($lineStyle);
        $section->addText("This document describes the '$sourceProjectTitle' Include a short description of the project and how it pertains to data management.", "generalFontStyle");
        // Scope
        $section->addTitle("Scope", 1);
        $section->addLine($lineStyle);
        $section->addText("This document is to be used as a reference '$sourceProjectTitle' users for training purposes as well as for change requests tracking.", "generalFontStyle");

        // Triggers
        $section->addTitle("Database Set Up", 1);
        $section->addLine($lineStyle);

        if ($settings["import-dags"])
        {
            $section->addText("Create records in PID " . $settings["dest-project"] . " in the same data access groups (DAGs). DAG names will be the same across projects, though the IDs will be different.");
            $section->addTextBreak();
        }

        foreach($settings["triggers"] as $index => $trigger) 
        {
            $section->addText("Trigger #$index: Create a record in PID " . $settings["dest-project"] . ", and move data in table $index when the following condition is true:", "generalFontStyle");
            $section->addText(htmlspecialchars($trigger), "triggerFontStyle", "centerParagraphStyle");
            $section->addTextBreak();
        }

        // Fields/Instrument Linkage
        $section->addTitle("Fields/Instrument Linkage", 1);
        $section->addLine($lineStyle);
        $section->addText("The data from the following variables in PID " . $this->getProjectId() . " are *copied* into PID " . $settings["dest-project"] . " automatically when conditions are met.", "generalFontStyle");
        $section->addTextBreak();

        $text .= "Link records between source and destination project using ";
        if (!empty($settings["linkSourceEvent"]))
        {
            $text .= "[" . $settings["linkSourceEvent"] . "]";
        }
        $text .= "[" . $settings["linkSource"] . "] = ";
        if (!empty($settings["linkDestEvent"]))
        {
            $text .= "[" . $settings["linkDestEvent"] . "]";
        }
        $text .= "[" . $settings["linkDest"] . "]";
        $section->addText($text, "generalFontStyle");

        foreach($settings["triggers"] as $index => $trigger) 
        {
            $section->addTextBreak();
            $section->addText("Copy the following data into PID " . $settings["dest-project"] . " when trigger #$index is true", "generalFontStyle");

            $fields_instr_table = $section->addTable($tableStyle);
            
            // Table headers
            $fields_instr_table->addRow();
            $fields_instr_table->addCell(1750, $cellStyle)->addText("From source project", array('bold' => true));
            $fields_instr_table->addCell(1750, $cellStyle)->addText("To destination project", array('bold' => true));

            $pipingSourceEvents = $settings["pipingSourceEvents"][$index];
            $pipingDestEvents = $settings["pipingDestEvents"][$index];
            $pipingSourceFields = $settings["pipingSourceFields"][$index];
            $pipingDestFields = $settings["pipingDestFields"][$index];
            foreach($pipingSourceFields as $i => $source)
            {
                $text = "";
                $fields_instr_table->addRow();
                if (!empty($pipingSourceEvents[$i]))
                {
                    $text .= "[" . $pipingSourceEvents[$i] . "]";
                }
                $text .= "[" . $source . "]";
                $fields_instr_table->addCell(1750)->addText($text);

                $text = "";
                if (!empty($pipingDestEvents[$i]))
                {
                    $text .= "[" . $pipingDestEvents[$i] . "]";
                }
                $text .= "[" . $pipingDestFields[$i] . "]";
                $fields_instr_table->addCell(1750)->addText($text);
            }

            $setDestEvents = $settings["setDestEvents"][$index];
            $setDestFields = $settings["setDestFields"][$index];
            $setDestFieldsValues = $settings["setDestFieldsValues"][$index];
            foreach($setDestFields as $i => $source)
            {
                $text = "";
                $fields_instr_table->addRow();
                if (!empty($setDestFieldsValues[$i]))
                {
                    $text .= "'" . $setDestFieldsValues[$i] . "'";
                    $fields_instr_table->addCell(1750)->addText($text);
                }

                $text = "";
                if (!empty($setDestEvents[$i]))
                {
                    $text .= "[" . $setDestEvents[$i] . "]";
                }
                $text .= "[" . $source . "]";
                $fields_instr_table->addCell(1750)->addText($text);
            }

            $sourceInstr = $settings["sourceInstr"][$index];
            $sourceInstrEvents = $settings["sourceInstrEvents"][$index];
            foreach($sourceInstr as $i => $source)
            {
                $text = "";
                $fields_instr_table->addRow();
                if (!empty($sourceInstrEvents[$i]))
                {
                    $text .= "[" . $sourceInstrEvents[$i] . "]";
                }
                $text .= "[" . $source . "]";
                $fields_instr_table->addCell(1750)->addText($text);

                $text = "";
                if (!empty($sourceInstrEvents[$i]))
                {
                    $text .= "[" . $sourceInstrEvents[$i] . "]";
                }
                $text .= "[" . $source . "]";
                $fields_instr_table->addCell(1750)->addText($text);
            }
        }

        // Restrictions
        $section->addTextBreak();
        $section->addTitle("Restrictions", 1);
        $section->addLine($lineStyle);
        $section->addText("Once the projects are in Production mode, the following action items should not occur in any of the projects under any circumstances. *DO NOT*: ", "generalFontStyle");
        $section->addTextBreak();

        $section->addListItem('Rename/Update/Delete the *Record ID* field', 0, null, 'multilevel');
        $section->addListItem('Add test subjects', 0, null, 'multilevel');
        $section->addListItem('Create subjects manually in *All projects*', 0, null, 'multilevel');
        $section->addListItem('Edit the API user account rights (*api useraccount names*)', 0, null, 'multilevel');
        $section->addListItem('Change the field types (ex. text box fields to check box fields and vice versa)', 0, null, 'multilevel');
        $section->addListItem('Change/Rename the Field Name', 0, null, 'multilevel');
        $section->addListItem('Change/Edit the arm &#38; event names ', 0, null, 'multilevel');

        // Implementation And Approval
        $section->addTextBreak();
        $section->addTitle("Implementation &#38; Approval", 1);
        $section->addLine($lineStyle);

        $completion_table = $section->addTable($tableStyle);
        $completion_table->addRow();
        $completion_table->addCell(1750)->addText("Date Started", "boldFontStyle");
        $completion_table->addCell(1750);
        $completion_table->addRow();
        $completion_table->addCell(1750)->addText("Date Completed", "boldFontStyle");
        $completion_table->addCell(1750);
        $completion_table->addRow();
        $completion_table->addCell(1750)->addText("Total number of hours (DM Team)", "boldFontStyle");
        $completion_table->addCell(1750);

        $section->addTextBreak();

        $dev_table = $section->addTable($tableStyle);
        $dev_table->addRow();
        $dev_table->addCell(1750, $cellStyle)->addText("PROJECT LEAD<w:br />Project Request", "generalFontStyle");
        $dev_table->addCell();
        $dev_table->addCell(1750, $cellStyle)->addText("Date", "generalFontStyle");
        $dev_table->addCell();
        $dev_table->addRow();
        $dev_table->addCell(1750, $cellStyle)->addText("DEVELOPMENT<w:br />Project Design and Setup.", "generalFontStyle");
        $dev_table->addCell();
        $dev_table->addCell(1750, $cellStyle)->addText("Date", "generalFontStyle");
        $dev_table->addCell();
        $dev_table->addRow();
        $dev_table->addCell(1750, $cellStyle)->addText("DEVELOPMENT", "generalFontStyle");
        $dev_table->addCell();
        $dev_table->addCell(1750, $cellStyle)->addText("Date", "generalFontStyle");
        $dev_table->addCell();
        $dev_table->addRow();
        $dev_table->addCell(1750, $cellStyle)->addText("DEV TESTING<w:br />(ALPHA TESTING)", "generalFontStyle");
        $dev_table->addCell();
        $dev_table->addCell(1750, $cellStyle)->addText("Date", "generalFontStyle");
        $dev_table->addCell();
        $dev_table->addRow();
        $dev_table->addCell(1750*2, array("bgColor" => "D3D3D3", "gridSpan" => 4))->addText("BETA RELEASE<w:br />Date:", "generalFontStyle", "centerParagraphStyle");
        $dev_table->addRow();
        $dev_table->addCell(1750, $cellStyle)->addText("BETA TESTING", "generalFontStyle");
        $dev_table->addCell();
        $dev_table->addCell(1750, $cellStyle)->addText("Date", "generalFontStyle");
        $dev_table->addCell();
        $dev_table->addRow();
        $dev_table->addCell(1750, $cellStyle)->addText("BCCH Research DM<w:br />Team Approval", "generalFontStyle");
        $dev_table->addCell();
        $dev_table->addCell(1750, $cellStyle)->addText("Date", "generalFontStyle");
        $dev_table->addCell();
        $dev_table->addRow();
        $dev_table->addCell(1750, array("bgColor" => "D3D3D3", "gridSpan" => 4))->addText("PROD RELEASE<w:br />Date:", "generalFontStyle", "centerParagraphStyle");

        // Enhancements & Approval
        $section->addTextBreak();
        $section->addTitle("Enhancements &#38; Approval", 1);
        $section->addLine($lineStyle);

        $enhancement_table = $section->addTable($tableStyle);
        $enhancement_table->addRow();
        $enhancement_table->addCell(1750, $cellStyle)->addText("Ticket ID#", "boldFontStyle");
        $enhancement_table->addCell(1750, $cellStyle)->addText("Description", "boldFontStyle");
        $enhancement_table->addCell(1750, $cellStyle)->addText("Status [Number of HW]", "boldFontStyle");
        $enhancement_table->addCell(1750, $cellStyle)->addText("Completed Date", "boldFontStyle");
        $enhancement_table->addRow();
        $enhancement_table->addCell();
        $enhancement_table->addCell();
        $enhancement_table->addCell();
        $enhancement_table->addCell();

        // Bug Fixes
        $section->addTextBreak();
        $section->addTitle("Bug Fixes", 1);
        $section->addLine($lineStyle);

        $enhancement_table = $section->addTable($tableStyle);
        $enhancement_table->addRow();
        $enhancement_table->addCell(1750, $cellStyle)->addText("JIRA ID", "boldFontStyle");
        $enhancement_table->addCell(1750, $cellStyle)->addText("Description", "boldFontStyle");
        $enhancement_table->addCell(1750, $cellStyle)->addText("Status", "boldFontStyle");
        $enhancement_table->addCell(1750, $cellStyle)->addText("Resolved Date", "boldFontStyle");
        $enhancement_table->addRow();
        $enhancement_table->addCell();
        $enhancement_table->addCell();
        $enhancement_table->addCell();
        $enhancement_table->addCell();

        // Appendix: Key Terms
        $section->addTextBreak();
        $section->addTitle("Appendix: Key terms", 1);
        $section->addLine($lineStyle);
        $section->addText("The following table provides definitions for terms relevant to this document.", "generalFontStyle");

        $appendix_table = $section->addTable($tableStyle);
        $appendix_table->addRow();
        $appendix_table->addCell(1750, $cellStyle)->addText("Term", "boldFontStyle");
        $appendix_table->addCell(1750, $cellStyle)->addText("Definition", "boldFontStyle");
        $appendix_table->addRow();
        $appendix_table->addCell()->addText("Alpha Testing", "generalFontStyle");
        $appendix_table->addCell()->addText("Alpha testing is simulated or actual operational testing by potential users/customers or an independent test team at the developers' site. Alpha testing is often employed for off-the-shelf software as a form of internal acceptance testing, before the software goes to beta testing.", "generalFontStyle");
        $appendix_table->addRow();
        $appendix_table->addCell()->addText("Beta Testing", "generalFontStyle");
        $appendix_table->addCell()->addText("Beta testing comes after alpha testing and can be considered a form of external user testing. Versions of the software, known as beta versions, are released to a limited audience outside of the programming team known as beta testers.", "generalFontStyle");
        $appendix_table->addRow();
        $appendix_table->addCell()->addText("DET", "generalFontStyle");
        $appendix_table->addCell()->addText("A Data Entry Trigger (DET) in REDCap is the capability to execute a script every time a survey or data entry form is saved.", "generalFontStyle");
        $appendix_table->addRow();
        $appendix_table->addCell()->addText("API", "generalFontStyle");
        $appendix_table->addCell()->addText('The acronym "API" stands for "Application Programming Interface". An API is just a defined way for a program to accomplish a task, usually retrieving or modifying data. API requests to REDCap are done using SSL (HTTPS), which means that the traffic to and from the REDCap server is encrypted.
        ', "generalFontStyle");
        $appendix_table->addRow();
        $appendix_table->addCell()->addText("HW", "generalFontStyle");
        $appendix_table->addCell()->addText("Hours of Work.", "generalFontStyle");

        // Support Information
        $section->addTextBreak();
        $section->addTitle("Support Information", 1);
        $section->addLine($lineStyle);
        $section->addText("If you have any questions about this document or about the project, please contact at redcap@cfri.ca.", "generalFontStyle");

        // Saving the document as OOXML file...
        $filename = $this->getSystemSetting("temp-folder") . "/release_notes.docx";
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($filename);

        // Stream file
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessing');
        header('Content-Disposition: attachment; filename="'.basename($filename).'"');
        readfile($filename);

        // Delete file
        unlink($filename);
    }

    /**
     * Function called by external module that checks whether the user has permissions to use the module.
     * Only returns the link if the user has admin privileges.
     * 
     * @param String $project_id    Project ID of current REDCap project.
     * @param String $link          Link that redirects to external module.
     * @return NULL Return null if the user doesn't have permissions to use the module. 
     * @return String Return link to module if the user has permissions to use it. 
     */
    public function redcap_module_link_check_display($project_id, $link)
    {
        if (SUPER_USER)
        {
            return $link;
        }
        return null;
    }
}