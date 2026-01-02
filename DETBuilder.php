<?php

namespace BCCHR\DETBuilder;
require_once "vendor/autoload.php";
use Dompdf\Dompdf;
use REDCap;
use Project;

class DETBuilder extends \ExternalModules\AbstractExternalModule {
    /*
    ** A class to assist with the DET Builder code. 
    */

    public $source_project = 0;  // a pid
    public $source_record = '';  // a record ID in the source project to be parsed by the DETBuilder
    public $dest_project = 0;  // a pid
    public $source_data = array();  // the raw data pulled from the source project
    public $source_field_types = array();  // array of field types in source project, key is field name valie is type
    public $source_instrument_names = array(); // array keyed by instrument name, with array of fields for that instrument as values
    public $dest_field_types = array();  // array of field types in dest project, key is field name value is type
    public $dest_instrument_names = array(); // array keyed by instrument name, with array of fields for that instrument as values
    public $create_record_trigger = '';  // the trigger to check for record creation
    public $link_source_field = '';  // linking field name in source project
    public $link_source_event = '';  // linking event in source project
    public $link_dest_field = '';  // linking field name in dest project
    public $link_dest_event = '';  // linking event in dest project
    public $triggers = array();  // array of triggers to check
    public $piping_source_events = array();  // events in source project
    public $piping_dest_events = array();  // events in dest project
    public $piping_source_fields = array();  // fields in source project
    public $piping_dest_fields = array();  // fields in dest project
    public $set_dest_events = array();
    public $set_dest_fields = array();
    public $set_dest_fields_values = array();
    public $source_to_dest_field_map = array();  // an assoc array of source field name with dest field name as value
    public $data_for_transfer = array();  // the data to be written

    public $source_instruments_events = array();
    public $source_instruments = array();
    public $instr_dest_events = array(); // classic -> longitudinal destination event mapping for instruments
    public $overwrite_data = '';
    public $import_dags = '';

    public $source_rows_by_event = []; // ['event_name' => assoc row]

    public function loadDETSettings() {
        /*
        ** loads the DET settings from the provided source
        */
        // Get DET settings
        //$settings = json_decode($this->getProjectSetting("det_settings", $this->source_project), true);
        $settings = json_decode($this->getProjectSetting("det_settings"), true);
        // REDCap::logEvent("DET Builder: [debug] det_settings read from getProjectSetting", print_r($settings, true), null, $record, null, $project_id);
        
        $this->dest_project = $settings["dest-project"];
        $this->create_record_trigger = $settings["create-record-cond"];  // this is never used.

        $this->link_source_event = $settings["linkSourceEvent"];
        $this->link_source_field = $settings["linkSource"];

        $this->link_dest_event = $settings["linkDestEvent"];
        $this->link_dest_field = $settings["linkDest"];

        $this->triggers = $settings["triggers"];

        $this->piping_source_events = $settings["pipingSourceEvents"];
        // REDCap::logEvent("DET Builder: [debug] source events", print_r($this->piping_source_events, true), null, $record, null, $project_id);
        $this->piping_dest_events = $settings["pipingDestEvents"];

        $this->piping_source_fields = $settings["pipingSourceFields"];
        //REDCap::logEvent("DET Builder: [debug] source fields", print_r($this->piping_source_fields, true), null, $record, null, $project_id);
        $this->piping_dest_fields = $settings["pipingDestFields"];

        $this->set_dest_events = $settings["setDestEvents"];
        $this->set_dest_fields = $settings["setDestFields"];
        $this->set_dest_fields_values = $settings["setDestFieldsValues"];

        $this->source_instruments_events = $settings["sourceInstrEvents"];
        $this->source_instruments = $settings["sourceInstr"];

        // per-instrument dest events mapping
        $this->instr_dest_events = isset($settings["instrDestEvents"])
            ? $settings["instrDestEvents"]
            : [];

        $this->overwrite_data = $settings["overwrite-data"];
        $this->import_dags = $settings["import-dags"];
    
    }  // end loadDETSettings()

    public function loadFieldTypes() {
        /*
        ** parses the data dictionaries for the two projects and stores them. Stores field name, field_type
        */

        $sdd = REDCap::getDataDictionary($this->source_project, 'json');  // source data dict
        $source_dd = json_decode($sdd, true);
        
        foreach ($source_dd as $one_source_dd_field) {  // load source project field types
    
            $this->source_field_types[$one_source_dd_field['field_name']] = $one_source_dd_field['field_type'];
            
            if (empty($this->source_instrument_names[$one_source_dd_field['form_name']])) {  // create form-to-fields mapping
            
                $this->source_instrument_names[$one_source_dd_field['form_name']] = array();
            
            }  // end if

            array_push($this->source_instrument_names[$one_source_dd_field['form_name']], $one_source_dd_field['field_name']);

        }  // end foreach

        // REDCap::logEvent("DET Builder: [debug] source_instrument_names is now: ", print_r($this->source_instrument_names, true), null, $record, null, $project_id);
        
        $ddd = REDCap::getDataDictionary($this->dest_project, 'json');  // dest data dict
        $dest_dd = json_decode($ddd, true);

        foreach ($dest_dd as $one_dest_dd_field) {  // load dest project field types

            $this->dest_field_types[$one_dest_dd_field['field_name']] = $one_dest_dd_field['field_type'];

            if (empty($this->dest_instrument_names[$one_dest_dd_field['form_name']])) {  // create form-to-fields-mapping
            
                $this->dest_instrument_names[$one_dest_dd_field['form_name']] = array();
            
            }  // end if

            array_push($this->dest_instrument_names[$one_dest_dd_field['form_name']], $one_dest_dd_field['field_name']);

        }  // end foreach
        
        // REDCap::logEvent("DET Builder: [debug] in loadFieldTypes() source field types", print_r($this->source_field_types, true), null, $record, null, $project_id);
        // REDCap::logEvent("DET Builder: [debug] in loadFieldTypes() dest field types", print_r($this->dest_field_types, true), null, $record, null, $project_id);
    }  // end loadFieldTypes()

    public function mapFieldsAndData() {
        /*
        ** maps the tuples of source field data, destination fields, events into associative 
        ** arrays, and populates the data_for_transfer array with keys named properly for the 
        ** destination project.
        */

        /*
        ** load the source record data from the project
        */
        $source_fields_to_read = array_keys($this->source_field_types);  // build list of fields to read

        // REDCap::logEvent("DET Builder: [debug] source instrument names value: ", print_r($this->source_instrument_names, true), null, $record, null, $project_id);

        // just read all the form completion fields - check values later
        foreach (array_keys($this->source_instrument_names) as $instr_name) {
            $comp_field_name = $instr_name . "_complete";
            array_push($source_fields_to_read, $comp_field_name);
        }

        // Create a map of trigger index -> completion requirements
        $trigger_completion_requirements = array();
        foreach ($this->triggers as $index => $trigger) {
            $checks = $this->extractFormCompletionChecks($trigger);
            if (!empty($checks)) {
                $trigger_completion_requirements[$index] = $checks;
            }
        }

        // REDCap::logEvent("DET Builder: [debug] in mapFieldsAndData() requesting fields for record " . $this->source_record , print_r($source_fields_to_read, true), null, $record, null, $project_id);
        
        //$raw_source_data = json_decode(REDCap::getData($this->source_project, 'json', $this->source_record, $source_fields_to_read, $this->piping_source_events), true);
        $raw_source_data = json_decode(REDCap::getData($this->source_project, 'json', $this->source_record, $source_fields_to_read), true);
        // $this->source_data = $raw_source_data[0];  // there can only be a single element of this array
        $this->source_rows_by_event = [];
        foreach ($raw_source_data as $row) {
            $ev = isset($row['redcap_event_name']) ? (string)$row['redcap_event_name'] : '';
            $this->source_rows_by_event[$ev] = $row;
        }
        // Keep a default (first row) for legacy use where event is unspecified
        $this->source_data = reset($raw_source_data) ?: [];

        
        // REDCap::logEvent("DET Builder: [debug] in mapFieldsAndData() read source data", print_r($this->source_data, true), null, $record, null, $project_id);

        /*
        ** Build the source to destination field mapping but don't transfer data yet
        */
        foreach ($this->piping_source_fields as $i => $source_field_arr) {
            foreach ($source_field_arr as $j => $sf_name) {
                $this->source_to_dest_field_map[$sf_name] = $this->piping_dest_fields[$i][$j];
            }
        }

        // If full instruments were selected in the settings, include their fields in the transfer.
        // $this->source_instruments is an array indexed by trigger containing arrays of instrument names.
        // Process each trigger independently - no data is transferred until a trigger's conditions are fully met
        foreach ($this->triggers as $triggerIndex => $triggerLogic) {
            // Initialize data array specific to this trigger
            $trigger_data = array();

            // evaluate this trigger's condition
            $valid = REDCap::evaluateLogic($triggerLogic, $this->source_project, $this->source_record);
            
            if (!$valid) {
                continue; // Skip this entire trigger if its condition isn't met
            }

            // Check completion requirements for this trigger before processing any data
            $trigger_requirements = isset($trigger_completion_requirements[$triggerIndex]) ? 
                $trigger_completion_requirements[$triggerIndex] : array();

            // Verify ALL completion requirements are met
            $all_requirements_met = true;
            foreach ($trigger_requirements as $reqInstrument => $reqStatus) {
                $comp_field = $reqInstrument . '_complete';
                if (!isset($this->source_data[$comp_field]) || 
                    $this->source_data[$comp_field] != $reqStatus) {
                    $all_requirements_met = false;
                    break;
                }
            }

            if (!$all_requirements_met) {
                continue; // Skip to next trigger if any completion requirement not met
            }

            // REDCap::logEvent("DET Builder: [debug] Processing trigger $triggerIndex", 
            //     "All conditions met - collecting fields", null, $this->source_record, null, $this->source_project);

            // Only now start collecting fields for this trigger
            if (isset($this->piping_source_fields[$triggerIndex])) {
                // Track which forms we've processed to avoid duplicate _complete fields
                $processed_forms = array();
                
                foreach ($this->piping_source_fields[$triggerIndex] as $j => $sf_name) {
                    $df_name = $this->piping_dest_fields[$triggerIndex][$j];
                    
                    // Find which form this field belongs to
                    $field_form = null;
                    foreach ($this->source_instrument_names as $form_name => $fields) {
                        if (in_array($sf_name, $fields)) {
                            $field_form = $form_name;
                            break;
                        }
                    }
                    
                    if ($this->source_field_types[$sf_name] == 'checkbox') {
                        // Handle checkbox fields
                        foreach ($this->source_data as $field_key => $field_value) {
                            if (str_contains($field_key, $sf_name)) {
                                $dest_field_name = str_replace($sf_name, $df_name, $field_key);
                                $trigger_data[$dest_field_name] = $field_value;
                            }
                        }
                    } else {
                        // Handle regular fields
                        if (array_key_exists($sf_name, $this->source_data)) {
                            $trigger_data[$df_name] = $this->source_data[$sf_name];
                        }
                    }
                    
                    // Include the form completion status if we haven't already for this form
                    if ($field_form && !in_array($field_form, $processed_forms)) {
                        $comp_field = $field_form . '_complete';
                        if (array_key_exists($comp_field, $this->source_data)) {
                            $trigger_data[$comp_field] = $this->source_data[$comp_field];
                            $processed_forms[] = $field_form;
                        }
                    }
                }
            }
                
            // Extract and verify completion requirements for this trigger
            $trigger_requirements = isset($trigger_completion_requirements[$triggerIndex]) ? 
                $trigger_completion_requirements[$triggerIndex] : array();

            // Check all completion requirements before processing any data
            foreach ($trigger_requirements as $reqInstrument => $reqStatus) {
                $comp_field = $reqInstrument . '_complete';
                if (!isset($this->source_data[$comp_field]) || 
                    $this->source_data[$comp_field] != $reqStatus) {
                    continue 2; // Skip to next trigger
                }
            }

            // Process instrument fields if this trigger has any
            if (!empty($this->source_instruments[$triggerIndex])) {
                foreach ($this->source_instruments[$triggerIndex] as $instrumentName) {
                    if (empty($this->source_instrument_names[$instrumentName])) continue;

                    // Process each field in the instrument
                    foreach ($this->source_instrument_names[$instrumentName] as $fieldName) {
                        // Skip if already handled by explicit field mapping
                        if (array_key_exists($fieldName, $trigger_data)) continue;

                        $fieldType = isset($this->source_field_types[$fieldName]) ? $this->source_field_types[$fieldName] : '';

                        if ($fieldType === 'checkbox') {
                            foreach ($this->source_data as $sourceKey => $sourceValue) {
                                if (strpos($sourceKey, $fieldName) === 0) {
                                    $trigger_data[$sourceKey] = $sourceValue;
                                }
                            }
                        } else if (array_key_exists($fieldName, $this->source_data)) {
                            $trigger_data[$fieldName] = $this->source_data[$fieldName];
                        }
                    }
                    
                    // Include the form completion status field for this instrument
                    $comp_field = $instrumentName . '_complete';
                    if (array_key_exists($comp_field, $this->source_data)) {
                        $trigger_data[$comp_field] = $this->source_data[$comp_field];
                    }
                }
            }

            // If we collected any data for this trigger, transfer it
            if (!empty($trigger_data)) {
                foreach ($trigger_data as $field => $value) {
                    $this->data_for_transfer[$field] = $value;
                }
                REDCap::logEvent("DET Builder: [debug] Trigger $triggerIndex succeeded", 
                    "Trigger condition: $triggerLogic\nTransferred fields: " . implode(", ", array_keys($trigger_data)), 
                    null, $this->source_record, null, $this->source_project);
            } else {
                REDCap::logEvent("DET Builder: [debug] Trigger $triggerIndex - no fields to transfer",
                    "Trigger condition met but no fields collected", 
                    null, $this->source_record, null, $this->source_project);
            }
        } // end foreach ($this->triggers as $triggerIndex => $triggerLogic)

        // print "<pre>source->dest field mapping: " . print_r($this->source_to_dest_field_map, true) . "</pre>\n";
    }  // end mapFieldsAndData()

    public function initializeObject($source_pid, $record) {  // external module code is unhappy with constructors, so this is an actively-called function.

        $this->source_project = $source_pid;  // set the source project id
        $this->source_record = $record;  // set record in the DETBuilder object
        $this->loadDETSettings();  // get the DET settings from project metadata
        $this->loadFieldTypes();  // load the types of fields for the source and dest projects, keyed by field name
        $this->mapFieldsAndData();  // load the data for this record, and map it into its proper name for the destination project

    }  // end init()

     /**
     * Replaces all strings in $text with $replacement
     * So "Alice says 'hello'" becomes "Alice says ''" assuming $replacement = ''.
     * 
     * @access private
     * @param String $text          The text to replace.
     * @param String $replacement   The replacement text.
     * @return String A string with the replaced text.
     */
    private function replaceStrings($text, $replacement) {

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
    }  // end replaceStrings()
    
    /**
     * Parses a syntax string into blocks.
     * 
     * @access private
     * @param String $syntax    The syntax to parse.
     * @return Array            An array of blocks that make up the syntax passed.
     */
    private function getSyntaxParts($syntax) {
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

    }  // end getSyntaxParts()

    /**
     * Extract form completion checks from a trigger condition.
     * Returns an array of form names and their expected completion values.
     * 
     * @access private
     * @param String $trigger   The trigger condition to analyze
     * @return Array           Array of form names mapped to their expected completion values
     */
    private function extractFormCompletionChecks($trigger) {
        $parts = $this->getSyntaxParts($trigger);
        $checks = array();
        
        for($i = 0; $i < count($parts); $i++) {
            $part = $parts[$i];
            // Look for patterns like [form_complete] = 2
            if($part === '[' && isset($parts[$i + 1]) && isset($parts[$i + 2]) && 
               substr($parts[$i + 1], -9) === '_complete') {
                $form = substr($parts[$i + 1], 0, -9);
                if(isset($parts[$i + 3]) && isset($parts[$i + 4])) {
                    if(in_array($parts[$i + 3], array('=', '=='))) {
                        // Remove any quotes from the value
                        $value = trim($parts[$i + 4], "'\"");
                        $checks[$form] = $value;
                    }
                }
            }
        }
        return $checks;
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
    public function validateSyntax($syntax) {

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
    
    }  // end validateSyntax()

    /**
     * Retrieve the following for all REDCap projects: ID, & title
     * 
     * @return Array    An array of rows pulled from the database, each containing a project's information.
     */
    public function getProjects() {
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
    
    }  // end getProjects()

    /**
     * Retrieves a project's fields
     * 
     * @param String $pid   A project's id in REDCap.
     * @return String       A JSON encoded string that contains all the instruments and fields for a project. 
     */
    public function retrieveProjectMetadata($pid) {

        if (!empty($pid))
        {
            $metadata = REDCap::getDataDictionary($pid, "array");
            $instruments = array_unique(array_column($metadata, "form_name"));
            $Proj = new Project($pid);
            $events = array_values($Proj->getUniqueEventNames());
            $isLongitudinal = $Proj->longitudinal;
            /**
             * We can pipe over any data except descriptive fields. 
             * 
             * NOTE: For calculation fields only the raw data can be imported/exported.
             */
            foreach($metadata as $field_name => $data)
            {
                if ($data["field_type"] != "descriptive" && $data["field_type"] != "calc")
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
            
            $return_value = array("fields" => $fields, "events" => $events, "isLongitudinal" => $isLongitudinal);
            // $json_return_value = json_encode($return_value);
            // print ("<!-- in retrieveProjectMetadata value to be returned as json is: " . print_r($json_return_value, true) . "-->\n");
            // return $json_return_value;
            // PATCHED 2025-06-02 by Dan Evans. Trying to make a json error go away.
            //*/
            return ["fields" => $fields, "events" => $events, "isLongitudinal" => $isLongitudinal];
        }
        return FALSE;
    
    }  // end retrieveProjectMetadata()

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
        
        return in_array($var, $external_fields) || in_array($var, $fields);
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
        /*
        ** PATCHED 2025-06-04 by Dan Evans. Newer redcap versions will use event_1_arm_1 as a value even if there are no defined events
        */
        if (sizeof($events) == 0) {  // no defined events, need to add a dummy event_1_arm_1 value
            $events[] = 'event_1_arm_1';
        }  // end if
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

    private function debugToFile($message, $data = null)
    {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $logFile = $logDir . '/det_debug.log';
        $timestamp = date('Y-m-d H:i:s');

        $output = "[$timestamp] $message\n";
        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $output .= print_r($data, true);
            } else {
                $output .= $data . "\n";
            }
        }
        $output .= str_repeat('-', 80) . "\n";

        file_put_contents($logFile, $output, FILE_APPEND);
    }

    // Return the array key for an event row ('' for classic)
    private function eventKey($isLongitudinal, $event) {
        return $isLongitudinal ? (string)$event : '';
    }

    // Ensure a row exists in $rowsByEvent and has link id (+ event for longitudinal)
    private function ensureRow(
        array &$rowsByEvent,
        $key,
        string $destPkField,
        string $destRecordId,
        string $linkDestField,
        $linkValue,
        bool $isLongitudinal,
        string $event
    ) {
        if (!isset($rowsByEvent[$key])) {
            // required for saveData(): destination PK must be present
            $rowsByEvent[$key] = [
                $destPkField => $destRecordId,
            ];

            // Keep/link the external linkage field
            if ($linkDestField !== $destPkField && $linkValue !== null && $linkValue !== '') {
                $rowsByEvent[$key][$linkDestField] = $linkValue;
            }

            if ($isLongitudinal && $event !== '') {
                $rowsByEvent[$key]['redcap_event_name'] = $event;
            }
        }
    }

    // Add field checkbox + add the form _complete once if available
    private function addFieldToRow(array &$row, $srcField, $destField, array $sourceData, array $sourceFieldTypes, array $sourceInstrumentNames) {
        if (($sourceFieldTypes[$srcField] ?? '') === 'checkbox') {
            foreach ($sourceData as $k => $v) {
                if (strpos($k, $srcField.'___') === 0) {
                    $row[$destField . substr($k, strlen($srcField))] = $v; // preserve ___code
                }
            }
        } else {
            if (array_key_exists($srcField, $sourceData)) {
                $row[$destField] = $sourceData[$srcField];
            }
        }

        // add that field's form completion once
        foreach ($sourceInstrumentNames as $form => $fields) {
            if (in_array($srcField, $fields, true)) {
                $cf = $form . '_complete';
                if (isset($sourceData[$cf]) && !isset($row[$cf])) {
                    $row[$cf] = $sourceData[$cf];
                }
                break;
        }}
    }

    // Add an entire instrument (+ its _complete)
    private function addInstrumentToRow(array &$row, $instrument, array $sourceData, array $sourceFieldTypes, array $sourceInstrumentNames) {
        $fields = $sourceInstrumentNames[$instrument] ?? [];
        foreach ($fields as $f) {
            $type = $sourceFieldTypes[$f] ?? '';
            if ($type === 'checkbox') {
                foreach ($sourceData as $k => $v) {
                    if (strpos($k, $f.'___') === 0) $row[$k] = $v;
                }
            } else {
                if (array_key_exists($f, $sourceData)) $row[$f] = $sourceData[$f];
            }
        }
        $cf = $instrument . '_complete';
        if (isset($sourceData[$cf])) $row[$cf] = $sourceData[$cf];
    }

    public function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) {

        // $this->debugToFile('HOOK redcap_save_record() called', [
        //     'project_id' => $project_id,
        //     'record' => $record,
        //     'instrument' => $instrument,
        //     'event_id' => $event_id,
        //     'group_id' => $group_id,
        //     'repeat_instance' => $repeat_instance
        // ]);

        $det = new DETBuilder();  // make a new DETBuilder object and load the relevant data
        $det->initializeObject($project_id, $record);

        // $this->debugToFile('DET snapshot', [
        //     'dest_project'        => $det->dest_project,
        //     'link_source_event'   => $det->link_source_event,
        //     'link_source_field'   => $det->link_source_field,
        //     'link_dest_event'     => $det->link_dest_event,
        //     'link_dest_field'     => $det->link_dest_field,
        //     'overwrite'           => $det->overwrite_data,
        //     'import_dags'         => $det->import_dags,
        //     'triggers_count'      => is_array($det->triggers) ? count($det->triggers) : 0
        // ]);

        // $this->debugToFile('Source data keys', array_keys($det->source_data ?? []));

        if ($project_id == $this->getProjectId()) {
            // $this->debugToFile("Comparing project_id to getProjectID", [
            //     'project_id' => $project_id,
            //     'getProjectId' => $this->getProjectId()
            // ]);

            foreach($det->triggers as $index => $trigger) {

                // $this->debugToFile("Trigger[$index] evaluate", $trigger);

                $valid = REDCap::evaluateLogic($trigger, $project_id, $record); // REDCap class method to evaluate conditional logic.

                // $this->debugToFile("Trigger[$index] evaluateLogic result", var_export($valid, true));

                if ($valid === false) {
                    // $this->debugToFile("Trigger[$index] invalid logic - skipping", $trigger);
                    REDCap::logEvent("DET: Trigger was either syntactically incorrect, or parameters were invalid (e.g., record or event does not exist). No data moved.", "Trigger: $trigger", null, $record, $event_id, $project_id);
                    continue;
                }

                if ($valid) {
                    // Per-trigger staging without anonymous functions
                    $rowsByEvent = []; // key => row array
                    $ProjDest       = new \Project($det->dest_project);
                    $isLongitudinal = (bool) $ProjDest->longitudinal;

                    // $this->debugToFile("Trigger[$index] context", [
                    //     'isLongitudinal' => $isLongitudinal,
                    //     'link_dest_event' => $det->link_dest_event,
                    //     'link_dest_field' => $det->link_dest_field
                    // ]);

                    $destPkField = (string) $ProjDest->table_pk;   // e.g. "record_id"

                    // get the source link value (e.g. study_id) from the configured source event/row
                    $srcLinkEvent = (string) ($det->link_source_event ?? '');
                    $srcRowForLink = $det->source_rows_by_event[$srcLinkEvent]
                        ?? $det->source_rows_by_event['']
                        ?? $det->source_data;

                    $linkValue = $srcRowForLink[$det->link_source_field] ?? null;

                    // resolve/create destination record id
                    $destRecordId = null;

                    if ($det->link_dest_field === $destPkField) {
                        // linking directly by PK
                        $destRecordId = (string) $linkValue;
                    } else {
                        // lookup destination record by link field (e.g. study_id)
                        if ($linkValue !== null && $linkValue !== '') {
                            $safe = str_replace("'", "\\'", (string)$linkValue);
                            $filterLogic = sprintf("[%s] = '%s'", $det->link_dest_field, $safe);

                            $raw = json_decode(
                                REDCap::getData(
                                    $det->dest_project,
                                    'json',
                                    null,
                                    [$destPkField, $det->link_dest_field],
                                    null,
                                    null,
                                    false,
                                    false,
                                    false,
                                    $filterLogic
                                ),
                                true
                            ) ?: [];

                            if (!empty($raw[0][$destPkField])) {
                                $destRecordId = (string) $raw[0][$destPkField];
                            }
                        }

                        // create new destination record if not found (auto-numbering must be ON)
                        if ($destRecordId === null || $destRecordId === '') {
                            $destRecordId = (string) REDCap::reserveNewRecordId($det->dest_project);
                        }
                    }

                    // 1) field → field pairs
                    if (!empty($det->piping_source_fields[$index])) {
                        // $this->debugToFile("Trigger[$index] field→field pairs",
                        //     [
                        //         'src' => $det->piping_source_fields[$index],
                        //         'dst' => $det->piping_dest_fields[$index] ?? [],
                        //         'dst_events' => $det->piping_dest_events[$index] ?? []
                        //     ]
                        // );

                        foreach ($det->piping_source_fields[$index] as $j => $srcField) {
                            $destField = $det->piping_dest_fields[$index][$j] ?? null;
                            if (!$destField) {
                                // $this->debugToFile("Trigger[$index] pair[$j] skipped - no destField for srcField", $srcField);
                                continue;
                            }

                            $destEvent = $det->piping_dest_events[$index][$j] ?? '';
                            $srcEvent  = $det->piping_source_events[$index][$j] ?? ''; // <-- NEW
                            // $this->debugToFile("Trigger[$index] pair[$j] events", [
                            //     'srcEvent'  => $srcEvent,
                            //     'destEvent' => $destEvent
                            // ]);

                            // DEST event fallback: if longitudinal and no per-pair dest, use link_dest_event
                            if ($isLongitudinal && $destEvent === '') {
                                $destEvent = $det->link_dest_event ?: '';
                                if ($destEvent === '') {
                                    // $this->debugToFile("WARN missing DEST event for field map and no link_dest_event fallback", [
                                    //     'trigger'=>$index, 'src'=>$srcField, 'dst'=>$destField
                                    // ]);
                                    continue;
                                }
                            }
                            $key = $det->eventKey($isLongitudinal, $destEvent);

                            // SOURCE row: use srcEvent if present, else the classic row ('') or first row
                            $sourceRow = $det->source_rows_by_event[$srcEvent]
                                    ?? $det->source_rows_by_event['']
                                    ?? $det->source_data;

                            $det->ensureRow(
                                $rowsByEvent,
                                $key,
                                $destPkField,
                                $destRecordId,
                                $det->link_dest_field,
                                $linkValue,
                                $isLongitudinal,
                                $destEvent
                            );

                            $det->addFieldToRow(
                                $rowsByEvent[$key],
                                $srcField,
                                $destField,
                                $sourceRow,
                                $det->source_field_types,
                                $det->source_instrument_names
                            );

                            // $this->debugToFile("Trigger[$index] pair[$j] staged", [
                            //     'srcEvent' => $srcEvent,
                            //     'destEvent'=> $destEvent,
                            //     'key'      => $key,
                            //     'src'      => $srcField,
                            //     'dst'      => $destField,
                            //     'row_keys' => array_keys($rowsByEvent[$key])
                            // ]);
                        }
                    }

                    // 2) constant value → dest field
                    if (!empty($det->set_dest_fields[$index])) {
                        // $this->debugToFile("Trigger[$index] set constants", [
                        //     'fields' => $det->set_dest_fields[$index],
                        //     'values' => $det->set_dest_fields_values[$index] ?? [],
                        //     'events' => $det->set_dest_events[$index] ?? [],
                        // ]);

                        foreach ($det->set_dest_fields[$index] as $j => $destField) {
                            $val   = $det->set_dest_fields_values[$index][$j] ?? null;
                            if ($val === null) {
                                // $this->debugToFile("Trigger[$index] const[$j] skipped - no value for field", $destField);
                                continue;
                            }

                            $event = $det->set_dest_events[$index][$j] ?? '';
                            if ($isLongitudinal && $event === '') {
                                // $this->debugToFile("WARN missing event for set-value map", [
                                //     'trigger'=>$index, 'dst'=>$destField
                                // ]);
                                continue;
                            }
                            $key = $det->eventKey($isLongitudinal, $event);


                            $det->ensureRow(
                                $rowsByEvent,
                                $key,
                                $destPkField,
                                $destRecordId,
                                $det->link_dest_field,
                                $linkValue,
                                $isLongitudinal,
                                $destEvent
                            );

                            $rowsByEvent[$key][$destField] = $val;

                            // $this->debugToFile("Trigger[$index] const[$j] staged", [
                            //     'event' => $event,
                            //     'key'   => $key,
                            //     'dst'   => $destField,
                            //     'value' => $val,
                            //     'row_keys' => array_keys($rowsByEvent[$key])
                            // ]);
                        }
                    }

                    // 3) instrument → instrument
                    if (!empty($det->source_instruments[$index])) {

                        // $this->debugToFile("Trigger[$index] instr→instr list", [
                        //     'instr'         => $det->source_instruments[$index],
                        //     'src_events'    => $det->source_instruments_events[$index] ?? [],
                        //     'instr_dest_evt'=> $det->instr_dest_events[$index] ?? []
                        // ]);

                        foreach ($det->source_instruments[$index] as $k => $instr) {
                            // Decide destination event
                            $srcEvent  = $det->source_instruments_events[$index][$k] ?? ''; // <-- NEW
                            $destEvent = $det->instr_dest_events[$index][$k] ?? '';
                            // $this->debugToFile("INSTR TO INSTR Trigger[$index] pair[$k] events", [
                            //     'srcEvent'  => $srcEvent,
                            //     'destEvent' => $destEvent
                            // ]);

                            if ($isLongitudinal) {
                                // 1) Preferred: explicit instrument-level DEST event (classic → longitudinal or long → long override)
                                $explicitDest = $det->instr_dest_events[$index][$k] ?? '';
                                if ($explicitDest !== '') {
                                    $destEvent = $explicitDest;
                                }
                                // 2) Longitudinal → longitudinal default: mirror the source event if present
                                elseif ($srcEvent !== '') {
                                    $destEvent = $srcEvent;
                                }
                                // 3) Fallback: use global linkDestEvent if defined (old behaviour, or classic → longitudinal with only linkDestEvent)
                                elseif (!empty($det->link_dest_event)) {
                                    $destEvent = $det->link_dest_event;
                                }

                                // If no dest event in a longitudinal dest project, skip this instrument
                                if ($destEvent === '') {
                                    // $this->debugToFile("WARN missing DEST event for instrument map", [
                                    //     'trigger'   => $index,
                                    //     'instr'     => $instr,
                                    //     'srcEvent'  => $srcEvent,
                                    //     'link_dest' => $det->link_dest_event
                                    // ]);
                                    continue;
                                }
                            } else {
                                // Destination project is classic → no event name used
                                $destEvent = '';
                            }

                            $key = $det->eventKey($isLongitudinal, $destEvent);

                            // Choose correct source row: prefer the matching event, then default row
                            $sourceRow = $det->source_rows_by_event[$srcEvent]
                                    ?? $det->source_rows_by_event['']
                                    ?? $det->source_data;

                            $det->ensureRow(
                                $rowsByEvent,
                                $key,
                                $destPkField,
                                $destRecordId,
                                $det->link_dest_field,
                                $linkValue,
                                $isLongitudinal,
                                $destEvent
                            );

                            $det->addInstrumentToRow(
                                $rowsByEvent[$key],
                                $instr,
                                $sourceRow,
                                $det->source_field_types,
                                $det->source_instrument_names
                            );

                            // $this->debugToFile("Trigger[$index] instr[$k] staged", [
                            //     'srcEvent'  => $srcEvent,
                            //     'destEvent' => $destEvent,
                            //     'key'       => $key,
                            //     'instr'     => $instr,
                            //     'row_keys'  => array_keys($rowsByEvent[$key])
                            // ]);
                        }
                    }

                    // $this->debugToFile("Trigger[$index] rowsByEvent BEFORE prune", $rowsByEvent);

                    // 4) prune empty rows
                    $payload = [];
                    foreach ($rowsByEvent as $r) {
                        $keys = array_diff(array_keys($r), [$det->link_dest_field, 'redcap_event_name']);
                        if (!empty($keys)) $payload[] = $r;
                    }

                    // $this->debugToFile("Trigger[$index] payload rows", $payload);

                    // 5) save once for this trigger
                    if (!empty($payload)) {
                        $save_params = [
                            'project_id'        => $det->dest_project,
                            'dataFormat'        => 'json',
                            'type'              => 'flat',
                            'overwriteBehavior' => $det->overwrite_data,
                            'data'              => json_encode($payload),
                        ];
                        $this->debugToFile("Trigger[$index] saveData params", $save_params);
                        $this->debugToFile("Trigger[$index] payload first row", $payload[0] ?? []);

                        $result = REDCap::saveData($save_params);

                        // $this->debugToFile("Trigger[$index] saveData result", $result);

                        if (!empty($result['errors'])) {
                            $this->debugToFile("Trigger[$index] ERROR(s)", (array)$result['errors']);
                            REDCap::logEvent("DET: Errors", json_encode($save_response["errors"]), null, $record, $event_id, $project_id);
                        } else {
                            if (!empty($result['warnings'])) {
                                $this->debugToFile("Trigger[$index] WARNING(s)", (array)$result['warnings']);
                                REDCap::logEvent("DET: Ran sucessfully with Warnings", json_encode($save_response["warnings"]), null, $record, $event_id, $project_id);
                            }
                            $this->debugToFile(
                                "Trigger[$index] saved OK",
                                [
                                    'ids' => (array)($result['ids'] ?? []),
                                    'item_count' => $result['item_count'] ?? null
                                ]
                            );
                            REDCap::logEvent("DET: Ran successfully", "Data was successfully imported from project $project_id to project $det->dest_project", null, $record, $event_id, $project_id);
                        }
                    } else {
                        $this->debugToFile("Trigger[$index] SKIP save - empty payload");
                        REDCap::logEvent("DET: Trigger skipped, payload was empty (e.g., record or event does not exist). No data moved.", "Trigger: $trigger", null, $record, $event_id, $project_id);
                    }
                } else {
                    $this->debugToFile("Trigger[$index] false - skipping", $trigger);
                    REDCap::logEvent("DET: Trigger was either syntactically incorrect, or parameters were invalid (e.g., record or event does not exist). No data moved.", "Trigger: $trigger", null, $record, $event_id, $project_id);
                }
            } // foreach triggers
        } // if project
    }

    /**
     * Function to create and download release notes from settings in the DET Builder.
     */
    public function downloadReleaseNotes($settings)  {

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
        $section->addText("If you have any questions about this document or about the project, please contact at redcap@bcchr.ca", "generalFontStyle");

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
    }  // end downloadReleaseNotes

    /**
     * Function called by external module that checks whether the user has permissions to use the module.
     * Only returns the link if the user has admin privileges.
     * 
     * @param String $project_id    Project ID of current REDCap project.
     * @param String $link          Link that redirects to external module.
     * @return NULL Return null if the user doesn't have permissions to use the module. 
     * @return String Return link to module if the user has permissions to use it. 
     */
    public function redcap_module_link_check_display($project_id, $link) {
        // if (SUPER_USER) line modified by Dan Evans, 2022-12-13 to make compatible with PHPv8.1
        if (defined("SUPER_USER") && SUPER_USER)
        {
            return $link;
        }
        return null;
    }  // end redcap_module_link_check_display()

}  // end class
?>