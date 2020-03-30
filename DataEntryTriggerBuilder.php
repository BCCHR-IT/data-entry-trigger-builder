<?php

namespace BCCHR\DataEntryTriggerBuilder;

use REDCap;
use Project;

class DataEntryTriggerBuilder extends \ExternalModules\AbstractExternalModule 
{
    /**
     * Replaces given text with replacement.
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
        $syntax = $this->replaceStrings(trim($syntax), "''");         //Replace strings with ''

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
     * Checks whether fields and events exist within project
     * 
     * @access private
     * @param String $text      The line of text to validate.
     * @return Array            An array of errors, with the line number appended to indicate where it occured.
     */
    public function isValidFieldOrEvent($var)
    {
        $var = trim($var, "'");

        $data_dictionary = REDCap::getDataDictionary('array');

        $events = REDCap::getEventNames(true, true); // If there are no events (the project is classical), the method will return false

        /**
         * Get REDCap completion fields
         */
        $external_fields = array();
        $instruments = REDCap::getInstrumentNames();
        foreach ($instruments as $unique_name => $label)
        {   
            $external_fields[] = "{$unique_name}_complete";
        }

        if (!in_array($var, $external_fields))
        {
            $dictionary = $data_dictionary[$var];
            if (($events === FALSE && empty($dictionary)) ||
                ($events !== FALSE && !in_array($var, $events) && empty($dictionary)))
            {
                return false;
            }
        }
        return true;
    }

    /**
     * Validate general syntax.
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
                
                    if (($next_part !== "(" 
                        && $next_part !== ")" 
                        && $next_part !== "["))
                    {
                        $errors[] = "Invalid <strong>$next_part</strong> after <strong>(</strong>.";
                    }
                    break;
                case ")":
                    // Must have either a ) or logical operator after, if not the last part of syntax
                    if ($index != sizeof($parts) - 1)
                    {
                        $next_part = $parts[$index + 1];
                        if ($next_part !== ")" && !in_array($next_part, $logical_operators))
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
                        $next_part = $parts[$index + 1];

                        if ($previous_2 !== "[")
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
                        ($this->isValidFieldOrEvent($part) == false))
                    {
                        $errors[] = "<strong>$part</strong> is not a valid event/field in this project";
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
        $sql = "select project_id, app_title from redcap_projects";
        if ($query_result = $this->query($sql))
        {
            while($row = db_fetch_assoc($query_result))
            {
                $projects[] = $row;
            }
            $query_result->close();
        }
        return $projects;
    }

    /**
     * Retrieves a project's instruments and fields
     * 
     * @param String $pid   A project's id in REDCap.
     * @return String       A JSON encoded string that contains all the instruments and fields for a project. 
     */
    public function retrieveProjectMetadata($pid)
    {
        if (!empty($pid))
        {
            $metadata = REDCap::getDataDictionary($pid, "array");
            $instruments = array_keys(REDCap::getInstrumentNames());
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

            return ["instruments" => $instruments, "fields" => $fields, "events" => $events, "isLongitudinal" => $isLongitudinal];
        }
        return FALSE;
    }

    /**
     * Print the form item for the destination project.
     * 
     * @param Array $settings   An associative array containing the DET settings
     */
    private function linkedProjectFormItem($settings)
    {
        ?>
        <h4>Select a linked Project</h4>
        <div class="form-group">
            <select name="dest-project" id="destination-project-select" class="form-control selectpicker" data-live-search="true" required>
                <option value="" disabled <?php if (empty($settings)) { print "selected"; }?>>Select a project</option>
                <?php
                    $projects = $this->getProjects();
                    foreach($projects as $project)
                    {
                        if ($project["project_id"] != $_GET["pid"]) {
                            if (!empty($settings["dest-project"]) && $project["project_id"] == $settings["dest-project"])
                            {
                                print "<option value='". $project["project_id"] . "' selected>" . $project["app_title"] . "</option>";
                            }
                            else
                            {
                                print "<option value='". $project["project_id"] . "'>" . $project["app_title"] . "</option>";
                            }
                        }
                    }
                ?>
            </select>
        </div>
        <?php
    }

    /**
     * Print the form item for subject creation.
     * 
     * @param Array $settings   An associative array containing the DET settings
     */
    private function subjectCreationFormItem($settings)
    {
        $events = REDCap::getEventNames(true, true);
        $metadata = $this->retrieveProjectMetadata($this->getProjectId());
        $dest_fields = $this->retrieveProjectMetadata($settings["dest-project"]);
        ?>
        <h4>Subject Creation</h4>
        <div class="form-group">
            <label>Create a subject/record ID in the linked project, PID xyz, when the following conditions are met:</label>
            <ul>
                <li>E.g., [event_name][instrument_name_complete] = '2'</li>
                <li>E.g., [event_name][variable_name] = '1'</li>
            </ul>
            <p>Where [event_name] = only in longitudinal projects<br/>Where [instrument_name] = form copied from source to linked project</p>
            <input id="create-record-input" name="create-record-cond" type="text" class="form-control" value="<?php print $settings["create-record-cond"]?>" required>
        </div>
        <div class='row link-field form-group'> 
            <div class='col-sm-2'><p>Link source project field</p></div> 
            <?php if (REDCap::isLongitudinal()): ?>
            <div class='col-sm-2'>
                <select name='linkSourceEvent' class='form-control selectpicker' data-live-search='true' required>
                    <option value='' disabled <?php if (empty($settings)) { print "selected"; }?>>Select event</option>
                    <?php
                        foreach ($events as $event_name) {
                            if (!empty($settings["linkSourceEvent"]) && $event_name == $settings["linkSourceEvent"])
                            {
                                print "<option value='$event_name' selected>$event_name</option>";
                            }
                            else
                            {
                                print "<option value='$event_name'>$event_name</option>";
                            }
                        }
                    ?>
                </select> 
            </div>
            <?php endif;?>
            <div class='col-sm-2'>
                <select name='linkSource' class='form-control selectpicker' data-live-search='true' required>
                    <option value='' disabled <?php if (empty($settings)) { print "selected"; }?>>Select field</option>
                    <?php
                        foreach($metadata["fields"] as $field_name)
                        {
                            if (!empty($settings["linkSource"]) && $field_name == $settings["linkSource"])
                            {
                                print "<option value='$field_name' selected>$field_name</option>";
                            }
                            else
                            {
                                print "<option value='$field_name'>$field_name</option>";
                            }
                        }
                    ?>
                </select> 
            </div> 
            <div class='col-sm-2'><p>to linked project field</p></div>
            <?php if (!empty($dest_fields) && !empty($settings["linkDestEvent"])): ?>
            <div id="link-event-wrapper" class='col-sm-2'>
                <select id="link-event-select" name='linkDestEvent' class='form-control selectpicker' data-live-search='true' required>
                    <option value='' disabled>Select event</option>
                    <?php
                        foreach ($dest_fields["events"] as $event_name) {
                            if ($event_name == $settings["linkDestEvent"])
                            {
                                print "<option value='$event_name' selected>$event_name</option>";
                            }
                            else
                            {
                                print "<option value='$event_name'>$event_name</option>";
                            }
                        }
                    ?>
                </select> 
            </div>
            <?php endif;?>
            <div id="link-source-wrapper" class='col-sm-2'>
                <select id="link-dest-select" name='linkDest' class='form-control selectpicker select-dest-field' data-live-search='true' required>
                    <option value='' disabled <?php if (empty($settings)) { print "selected"; }?>>Select field</option>
                    <?php
                    if (!empty($dest_fields)) {
                        foreach($dest_fields["fields"] as $field_name)
                        {
                            if (!empty($settings["linkDest"]) && $field_name == $settings["linkDest"])
                            {
                                print "<option value='$field_name' selected>$field_name</option>";
                            }
                            else
                            {
                                print "<option value='$field_name'>$field_name</option>";
                            }
                        }
                    }
                    ?>
                </select> 
            </div> 
        </div>
        <?php
    }

    /**
     * Print the form items for triggers and data to import.
     * 
     * @param Array $settings   An associative array containing the DET settings
     */
    private function triggersFormItems($settings)
    {
        $events = REDCap::getEventNames(true, true);
        $metadata = $this->retrieveProjectMetadata($this->getProjectId());
        $instrument_names = REDCap::getInstrumentNames();
        $dest_fields = $this->retrieveProjectMetadata($settings["dest-project"]);
        ?>
            <h4>Trigger conditions (Max. 10)</h4>
            <div id="trigger-instr">
                <label>Push data from the source project to the linked project, when the following conditions are met:</label>
                <ul>
                    <li>E.g., [event_name][instrument_name_complete] = "2"</li>
                    <li>E.g., [event_name][variable_name] = "1"</li>
                </ul>
                <p>Where [event_name] = only in longitudinal projects<br/>Where [instrument_name] = form copied from source to linked project</p>
                <button type="button" class="btn btn-link add-trigger-btn">Add Trigger</button>
            </div>
            <?php if (!empty($settings)): foreach($settings["triggers"] as $index => $trigger): ?>
            <div class="form-group trigger-and-data-wrapper">
                <div class="det-trigger">
                    <div class="row">
                        <div class="col-sm-2">
                            <label>Condition:</label>
                        </div>
                        <div class="col-sm-9"></div>
                        <div class="col-sm-1" style="text-align: center;">
                            <span class="fa fa-minus delete-trigger-btn"></span>
                        </div>
                    </div>
                    <input name="triggers[]" type="text" class="form-control det-trigger-input" value="<?php print $trigger; ?>" required>
                </div>
                <p>Copy the following instruments/fields from source project to linked project when the above condition is true:</p>
                <div class="row" style="margin-top:20px">
                    <div class="col-sm-2"><button type="button" class="btn btn-link add-instr-btn">Pipe Instrument</button></div>
                    <div class="col-sm-2"><button type="button" class="btn btn-link add-field-btn">Pipe Field</button></div>
                    <div class="col-sm-2"><button type="button" class="btn btn-link set-field-btn">Set Field</button></div>
                </div>
                <?php
                    $pipingSourceEvents = $settings["pipingSourceEvents"][$index];
                    $pipingDestEvents = $settings["pipingDestEvents"][$index];
                    $pipingSourceFields = $settings["pipingSourceFields"][$index];
                    $pipingDestFields = $settings["pipingDestFields"][$index];
                    foreach($pipingSourceFields as $i => $source)
                    {
                        ?>
                        <div class='row det-field' style='margin-top:20px'>
                            <div class='col-sm-2'><p>Copy field</p></div>
                            <?php if (REDCap::isLongitudinal()): ?>
                            <div class='col-sm-2'>
                                <select name='pipingSourceEvents[<?php print $index;?>][]' class='form-control selectpicker' data-live-search='true' required>
                                    <option value='' disabled <?php if (empty($settings)) { print "selected"; }?>>Select event</option>
                                    <?php
                                        foreach ($events as $event_name) {
                                            if ($event_name == $pipingSourceEvents[$i])
                                            {
                                                print "<option value='$event_name' selected>$event_name</option>";
                                            }
                                            else
                                            {
                                                print "<option value='$event_name'>$event_name</option>";
                                            }
                                        }
                                    ?>
                                </select> 
                            </div>
                            <?php endif;?>
                            <div class='col-sm-2'>
                                <select name='pipingSourceFields[<?php print $index;?>][]' class='form-control selectpicker' data-live-search='true' required>
                                <option value='' disabled>Select field</option> 
                                <?php
                                    foreach($metadata["fields"] as $field_name)
                                    {
                                        if ($field_name == $source)
                                        {
                                            print "<option value='$field_name' selected>$field_name</option>";
                                        }
                                        else
                                        {
                                            print "<option value='$field_name'>$field_name</option>";
                                        }
                                    }
                                ?>
                                </select>
                            </div>
                            <div class='col-sm-1'><p>to</p></div>
                            <?php if ($dest_fields["isLongitudinal"]): ?>
                            <div class='col-sm-2'>
                                <select name='pipingDestEvents[<?php print $index;?>][]' class='form-control selectpicker' data-live-search='true' required>
                                    <option value='' disabled <?php if (empty($settings)) { print "selected"; }?>>Select event</option>
                                    <?php
                                        foreach ($dest_fields["events"] as $event_name) {
                                            if ($event_name == $pipingDestEvents[$i])
                                            {
                                                print "<option value='$event_name' selected>$event_name</option>";
                                            }
                                            else
                                            {
                                                print "<option value='$event_name'>$event_name</option>";
                                            }
                                        }
                                    ?>
                                </select> 
                            </div>
                            <?php endif;?>
                            <div class='col-sm-2'>
                                <select name='pipingDestFields[<?php print $index;?>][]' class='form-control selectpicker select-dest-field' data-live-search='true' required>
                                <option value='' disabled>Select field</option>
                                <?php
                                    foreach($dest_fields["fields"] as $field_name)
                                    {
                                        if ($field_name == $pipingDestFields[$i])
                                        {
                                            print "<option value='$field_name' selected>$field_name</option>";
                                        }
                                        else
                                        {
                                            print "<option value='$field_name'>$field_name</option>";
                                        }
                                    }
                                ?>
                                </select>
                            </div>
                            <div class='col-sm-1' style='text-align: center; padding-top: 1%; padding-bottom: 1%;'>
                                <span class='fa fa-minus delete-field-btn' style='margin-right: 5px'></span>
                            </div>
                        </div>
                      <?php
                    }

                    $setDestEvents = $settings["setDestEvents"][$index];
                    $setDestFields = $settings["setDestFields"][$index];
                    $setDestFieldsValues = $settings["setDestFieldsValues"][$index];
                    foreach($setDestFields as $i => $source)
                    {
                        ?>
                        <div class='row det-field' style='margin-top:20px'>
                            <div class='col-sm-2'><p>Set field</p></div>
                            <?php if ($dest_fields["isLongitudinal"]): ?>
                            <div class='col-sm-2'>
                                <select name='setDestEvents[<?php print $index;?>][]' class='form-control selectpicker' data-live-search='true' required>
                                    <option value='' disabled <?php if (empty($settings)) { print "selected"; }?>>Select event</option>
                                    <?php
                                        foreach ($dest_fields["events"] as $event_name) {
                                            if ($event_name == $setDestEvents[$i])
                                            {
                                                print "<option value='$event_name' selected>$event_name</option>";
                                            }
                                            else
                                            {
                                                print "<option value='$event_name'>$event_name</option>";
                                            }
                                        }
                                    ?>
                                </select> 
                            </div>
                            <?php endif;?>
                            <div class='col-sm-2'>
                                <select name='setDestFields[<?php print $index;?>][]' class='form-control selectpicker select-dest-field' data-live-search='true' required>
                                <option value='' disabled>Select a destination field</option>
                                <?php
                                    foreach($dest_fields["fields"] as $field_name)
                                    {
                                        if ($field_name == $source)
                                        {
                                            print "<option value='$field_name' selected>$field_name</option>";
                                        }
                                        else
                                        {
                                            print "<option value='$field_name'>$field_name</option>";
                                        }
                                    }
                                ?>
                                </select>
                            </div>
                            <div class='col-sm-1'><p>to</p></div>
                            <div class='col-sm-4'>
                                <input name='setDestFieldsValues[<?php print $index;?>][]' class='form-control' value='<?php print $setDestFieldsValues[$i]; ?>' required>
                            </div>
                            <div class='col-sm-1' style='text-align: center; padding-top: 1%; padding-bottom: 1%;'>
                                <span class='fa fa-minus delete-field-btn' style='margin-right: 5px'></span>
                            </div>
                        </div>
                        <?php
                    }

                    $sourceInstr = $settings["sourceInstr"][$index];
                    $sourceInstrEvents = $settings["sourceInstrEvents"][$index];
                    foreach($sourceInstr as $i => $source)
                    {
                        ?>
                        <div class='row det-field' style='margin-top:20px'>
                            <div class='col-sm-7'><p>Copy instrument (must have a one-to-one relationship in the destination project)</p></div>
                            <?php if (REDCap::isLongitudinal()): ?>
                            <div class='col-sm-2'>
                                <select name='sourceInstrEvents[<?php print $index;?>][]' class='form-control selectpicker' data-live-search='true' required>
                                    <option value='' disabled <?php if (empty($settings)) { print "selected"; }?>>Select event</option>
                                    <?php
                                        foreach ($events as $event_name) {
                                            if ($event_name == $sourceInstrEvents[$i])
                                            {
                                                print "<option value='$event_name' selected>$event_name</option>";
                                            }
                                            else
                                            {
                                                print "<option value='$event_name'>$event_name</option>";
                                            }
                                        }
                                    ?>
                                </select> 
                            </div>
                            <?php endif;?>
                            <div class='col-sm-2'>
                                <select name='sourceInstr[<?php print $index;?>][]' class='form-control selectpicker' data-live-search='true' required>
                                <option value='' disabled>Select an instrument</option> 
                                <?php
                                    foreach ($instrument_names as $unique_name=>$label)
                                    {
                                        if ($unique_name == $source)
                                        {
                                            print "<option value='$unique_name' selected>$unique_name</option>";
                                        }
                                        else
                                        {
                                            print "<option value='$unique_name'>$unique_name</option>";
                                        }
                                    }
                                ?>
                                </select>
                            </div>
                            <div class='col-sm-1' style='text-align: center; padding-top: 1%; padding-bottom: 1%;'>
                                <span class='fa fa-minus delete-field-btn' style='margin-right: 5px'></span>
                            </div>
                        </div>
                      <?php
                    }
                ?>
            </div>
            <?php 
            endforeach; 
            endif;
    }

    /**
     * Print the form section for additional settings to confirm.
     * 
     * @param Array $settings   An associative array containing the DET settings
     */
    private function additionalSettingsFormItems($settings)
    {
        ?>
        <h4>Confirm the following</h4>
        <div class="row">
            <div class="form-check col-6" style="margin-left:15px">
            <div class="row"><label>Overwrite data in destination project every time data is saved. This determines whether to push blank data over to the destination project.</label></div>
            <?php if (empty($settings)): ?>
                <input type="radio" name="overwrite-data" class="form-check-input" value="overwrite" required><label class="form-check-label">Yes</label>
                <br>
                <input type="radio" name="overwrite-data" class="form-check-input" value="normal" required><label class="form-check-label">No</label>
            <?php else:?>
                <?php if ($settings["overwrite-data"] == "overwrite"):?>
                <input type="radio" name="overwrite-data" class="form-check-input" value="overwrite" checked required><label class="form-check-label">Yes</label>
                <br>
                <input type="radio" name="overwrite-data" class="form-check-input" value="normal" required><label class="form-check-label">No</label>
                <?php else:?>
                <input type="radio" name="overwrite-data" class="form-check-input" value="overwrite" required><label class="form-check-label">Yes</label>
                <br>
                <input type="radio" name="overwrite-data" class="form-check-input" value="normal" checked required><label class="form-check-label">No</label>
                <?php endif; ?>
            <?php endif;?>
            </div>
        </div>
        <?php
    }

    /**
     * Print the DET form. Will fill the form with 
     * existing DET data, if it exists.
     * 
     * @param Array $settings   An associative array containing the DET settings
     */
    public function getForm($settings = null)
    {
        ?>
        <form class="jumbotron" method="post" action="<?php print $this->getUrl("index.php");?>">
            <?php $this->linkedProjectFormItem($settings); ?>
            <div id="main-form" <?php if (empty($settings)) :?> style="display:none" <?php endif;?>>
                <?php
                    $this->subjectCreationFormItem($settings);
                    $this->triggersFormItems($settings);
                    $this->additionalSettingsFormItems($settings);
                ?>
                <button id="create-det-btn" type="submit" class="btn btn-primary" style="margin-top:20px">Create DET</button>
            </div>
        </form>
        <?php
    }

    /**
     * Parses a String of branching logic into blocks of logic syntax.
     * Assumes valid REDCap Logic syntax in trigger.
     * 
     * Figure out case for : (() () ())) 
     * 
     * @param String $trigger_cond   A String of REDCap branching logic.
     * @return Array    An array of syntax blocks representing the given branching logic String.
     */
    private function parseCondition($trigger_cond)
    {
        $pos = strpos($trigger_cond, "(");

        /**
         * If brackets are at the beginning of the condition then split on first
         * && after them. If there are no && then split on the first ||.
         */
        if ($pos === 0)
        {
            for($i = 0; $i < strlen($trigger_cond); $i++)
            {
                if ($trigger_cond[$i] == "(")
                {
                    $opening_brackets[] = $i;
                }
                else if ($trigger_cond[$i] == ")")
                {
                    array_pop($opening_brackets);
                    if (empty($opening_brackets))
                    {
                        $closing_offset = $i;
                        break;
                    }
                }
                $closing_offset = -1;
            }

            if ($closing_offset == strlen($trigger_cond) - 1)
            {
                $left_cond = substr($trigger_cond, 1);
                $left_cond = substr($left_cond, 0, -1);

                return [
                    "left_branch" => $left_cond, 
                    "operand" => "", 
                    "right_branch" => ""
                ];
            }
            else
            {
                $remainder = substr($trigger_cond, $closing_offset+2);
            }
        }
        else if ($pos > 0)
        {
            $remainder = substr($trigger_cond, 0, $pos);
        }
        else if ($pos === FALSE)
        {
            $remainder = $trigger_cond;
        }

        $left_cond = "";
        $operator = "";
        $right_cond = "";

        if (preg_match("/\s*(&&)/", $remainder, $operators, PREG_OFFSET_CAPTURE) === 1 || 
            preg_match("/\s*(\|\|)/", $remainder, $operators, PREG_OFFSET_CAPTURE) === 1)
        {
            $relational_offset = $operators[0][1];
            $operator = $operators[0][0];

            if ($pos > 0 || $pos === FALSE)
            {
                $offset = $relational_offset + 1;
            }
            else if ($pos === 0)
            {
                $offset = $closing_offset + $relational_offset + 2;
            }

            $left_cond = trim(substr($trigger_cond, 0, $offset));
            $right_cond = trim(substr($trigger_cond, $offset + strlen($operator)));
            $operator = trim($operator);

            return [
                "left_branch" => $left_cond, 
                "operand" => $operator, 
                "right_branch" => $right_cond
            ];
        }
        else
        {
            return false;
        }
    }

    /**
     * Validates the given trigger.
     * Assumes valid REDCap Logic syntax in trigger.
     * 
     * @return Boolean
     */
    private function processTrigger($record_data, $trigger)
    {
        $logic_operators = array("==", "=", "<>", "!=", ">", "<", ">=", ">=", "<=");

        $tokens = $this->parseCondition($trigger);

        /**
         * If there's no relational operators, then split condition on 
         * logical operator and process.
         */
        if ($tokens === FALSE)
        {
            $blocks = preg_split("/(==|=|<>|!=|>|<|>=|>=|<=)/", $trigger, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            
            if (!REDCap::isLongitudinal())
            {
                $field = trim($blocks[0], " []'\"()");
                $record_data = $record_data[0];
            }
            else
            {
                $split_pos = strpos($blocks[0], "][");

                $event = substr($blocks[0], 0, $split_pos);
                $field = substr($blocks[0], $split_pos+1);

                $event = trim($event, " []'\"()");
                $field = trim($field, " []'\"()");

                $event_key = array_search($event, array_column($record_data, "redcap_event_name"));
                $record_data = $record_data[$event_key];
            }

            $operator = trim($blocks[1]);
            $value = trim($blocks[2], " '\")");

            switch ($operator)
            {
                case "=":
                case "==":
                    return $record_data[$field] == $value;
                break;
                case "<>":
                case "!=":
                    return $record_data[$field] <> $value;
                break;
                case ">":
                    return $record_data[$field] > $value;
                break;
                case "<":
                    return $record_data[$field] < $value;
                break;
                case ">=":
                    return $record_data[$field] >= $value;
                break;
                case "<=":
                    return $record_data[$field] <= $value;
                break;
            }
        }
        /**
         * Split the condition, if there are relational operators,
         * and process left and right sides of argument on their own.
         * && takes priority
         */
        else if ($tokens["operand"] == "&&")
        {
            return $this->processTrigger($record_data, $tokens["left_branch"]) && $this->processTrigger($record_data, $tokens["right_branch"]);
        }
        else if ($tokens["operand"] == "||")
        {
            return $this->processTrigger($record_data, $tokens["left_branch"]) || $this->processTrigger($record_data, $tokens["right_branch"]);
        }
        else
        {
            return $this->processTrigger($record_data, $tokens["left_branch"]);
        }
        return true;
    }

    /**
     * REDCap hook is called immediately after a record is saved. Will retrieve the DET settings,
     * & import data according to DET.
     */
    public function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        if ($project_id == $this->getProjectId())
        {
            $settings = json_decode($this->getProjectSetting("det_settings"), true);

            // Get DET settings
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
            
            // Get current record data
            $record_data = json_decode(REDCap::getData("json", $record), true);

            if ($this->processTrigger($record_data, $create_record_trigger))
            {
                foreach($triggers as $index => $trigger)
                {
                    if ($this->processTrigger($record_data, $trigger))
                    {
                        $trigger_source_fields = $piping_source_fields[$index];
                        $trigger_dest_fields = $piping_dest_fields[$index];
                        $trigger_source_events = $piping_source_events[$index];
                        $trigger_dest_events = $piping_source_events[$index];

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
                                $data = $record_data[0];
                            }

                            if (!empty($trigger_dest_events[$i]))
                            {
                                $dest_event = $trigger_dest_events[$i];
                            }
                            else
                            {
                                $dest_event = "event_1_arm_1";
                            }
                            
                            if (empty($dest_record_data[$dest_event]))
                            {
                                $event_data = ["redcap_event_name" => $dest_event];
                            }
                            else
                            {
                                $event_data = $dest_record_data[$dest_event];
                            }

                            $source_field = $trigger_source_fields[$i];
                            $event_data[$dest_field] = $data[$source_field];
                            $dest_record_data[$dest_event] = $event_data;
                        }

                        $trigger_dest_fields = $set_dest_fields[$index];
                        $trigger_dest_values = $set_dest_fields_values[$index];
                        $trigger_dest_events = $set_dest_events[$index];
                        foreach($trigger_dest_fields as $i => $dest_field)
                        {
                            if (!empty($trigger_dest_events[$i]))
                            {
                                $dest_event = $trigger_dest_events[$i];
                            }
                            else
                            {
                                $dest_event = "event_1_arm_1";
                            }

                            if (empty($dest_record_data[$dest_event]))
                            {
                                $event_data = ["redcap_event_name" => $dest_event];
                            }
                            else
                            {
                                $event_data = $dest_record_data[$dest_event];
                            }

                            $event_data[$dest_field] = $trigger_dest_values[$i];
                            $dest_record_data[$dest_event] = $event_data;
                        }
    
                        $trigger_source_instruments = $source_instruments[$index];
                        $trigger_source_instruments_events = $source_instruments_events[$index];
                        foreach($trigger_source_instruments as $i => $source_instrument)
                        {
                            if (!empty($trigger_source_instruments_events[$i]))
                            {
                                $event = $trigger_source_instruments_events[$i];
                            }
                            else
                            {
                                $event = "event_1_arm_1";
                            }

                            if (empty($dest_record_data[$event]))
                            {
                                $event_data = ["redcap_event_name" => $event];
                            }
                            else
                            {
                                $event_data = $dest_record_data[$event];
                            }

                            // Fields are returned in the order they are in the REDCap project
                            $source_instrument_fields = REDCap::getFieldNames($source_instrument);
                            $source_instrument_data = json_decode(REDCap::getData("json", $record, $source_instrument_fields, $event), true)[0];

                            $event_data = $event_data + $source_instrument_data;
                            $dest_record_data[$event] = $event_data;
                        }
                    }
                }

                // Check if the linking id field is the same as the record id field.
                $dest_record_id = $this->framework->getRecordIdField($dest_project);
                $link_dest_value = $record_data[0][$link_source];
                if ($dest_record_id != $link_dest_field)
                {
                    // Check for existing record, otherwise create a new one. Assume linking ID is unique
                    if (REDCap::isLongitudinal())
                    {
                        $key = array_search($link_source_event, array_column($record_data, "redcap_event_name"));
                        $data = $record_data[$key];
                        $link_dest_value = $data[$link_source];
                    }

                    $existing_record = REDCap::getData("json", null, $dest_record_id, $link_dest_event, null, false, false, false, "[$link_dest_field] = $link_dest_value");
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
                
                if (!empty($dest_record_data))
                {
                    if (!empty($dest_record))
                    {
                        foreach ($dest_record_data as $i => $data)
                        { 
                            $dest_record_data[$i][$dest_record_id] = $dest_record;  
                        }
                    }

                    if (!empty($link_dest_event))
                    {
                        $dest_record_data[$link_dest_event][$link_dest_field] = $link_dest_value;
                    }
                    else
                    {
                        $dest_record_data["event_1_arm_1"][$link_dest_field] = $link_dest_value;
                    }
                }
                else
                {
                    $dest_record_data[] = [$dest_record_id => $dest_record, $link_dest_field => $link_dest_value];
                }
                
                $dest_record_data = array_values($dest_record_data);
            }

            if (!empty($dest_record_data))
            {
                // Save DET data in destination project;
                $save_response = REDCap::saveData($dest_project, "json", json_encode($dest_record_data), $overwrite_data);

                REDCap::logEvent("DET: Attempted to import", json_encode($dest_record_data), null, $record, $event_id, $project_id);

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
            }
        }
    }
}