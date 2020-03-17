<?php

namespace BCCHR\DataEntryTriggerBuilder;

use REDCap;

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
    private function isValidFieldOrEvent($var)
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

        $logic_operator_alt = array("==", "<>", "!=", "!=", ">", "<", ">=", ">=", "<=", "<=", "||", "&&", "=");
        $logical_operators = array("eq", "ne", "ne", "neq", "gt", "lt", "ge", "gte", "lte", "le", "or", "and", "eq");

        $syntax = str_replace($logic_operator_alt, $logical_operators, $syntax);
        
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
                case "eq":
                case "ne":
                case "neq":
                case "gt":
                case "ge":
                case "gte":
                case "lt":
                case "le":
                case "lte":
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
                            && $next_part !== "(" 
                            && $next_part !== "[" 
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
                case "or":
                case "and":
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
        $metadata = REDCap::getDataDictionary($pid, "array");
        $instruments = array_keys(REDCap::getInstrumentNames());

        /**
         * We can pipe over any data except descriptive fields. 
         * 
         * NOTE: For calculation fields only the raw data can be imported/exported.
         */
        foreach($metadata as $field_name => $data)
        {
            if ($data["field_type"] != "descriptive")
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

        return json_encode(array("instruments" => $instruments, "fields" => $fields));
    }

    /**
     * Prints a DET form filled with existing data
     * 
     * @param Array $settings   An associative array containing the DET settings
     */
    public function existingForm($settings)
    {
        $metadata = REDCap::getDataDictionary("array");
        $instrument_names = REDCap::getInstrumentNames();
        $dest_fields = json_decode($this->retrieveProjectMetadata($settings["dest-project"]), true);
        ?>
        <form class="jumbotron">
            <p style="font-size:16px"><b>Here are your existing DET settings:</b></p>
            <div class="form-group">
                <label>Linked Project</label>
                <select name="dest-project" id="destination-project-select" class="form-control selectpicker" data-live-search="true" required>
                    <option value="" disabled>Select a project</option>
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
            <h4>Subject Creation</h4>
            <div class="form-group">
                <label>Create a subject/record ID in the linked project, PID xyz, when the following conditions are met:</label>
                <ul>
                    <li>E.g., [event_name][instrument_name_complete] = "2"</li>
                    <li>E.g., [event_name][variable_name] = "1"</li>
                </ul>
                <p>Where [event_name] = only in longitudinal projects<br/>Where [instrument_name] = form copied from source to linked project</p>
                <input id="create-record-input" name="create-record-cond" type="text" class="form-control" value="<?php print $settings["create-record-cond"]?>" required>
            </div>
            <div class='row link-field form-group'> 
                <div class='col-sm-2'><p>Link source project field</p></div> 
                <div class='col-sm-3'>
                    <select name='linkSource' class='form-control selectpicker' data-live-search='true' required>
                        <option value='' disabled>Select a field</option>
                        <?php
                            foreach($metadata as $field_name => $data)
                            {
                                if ($data["field_type"] != "descriptive")
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
                            }
                            foreach ($instrument_names as $unique_name=>$label)
                            {
                                if (!empty($settings["linkSource"]) && $unique_name == $settings["linkSource"])
                                {
                                    print "<option value='{$unique_name}_complete' selected>{$unique_name}_complete</option>";
                                }
                                else
                                {
                                    print "<option value='{$unique_name}_complete'>{$unique_name}_complete</option>";
                                }
                            }
                        ?>
                    </select> 
                </div> 
                <div class='col-sm-2'><p>to linked project field</p></div> 
                <div class='col-sm-3'>
                    <select id="link-dest-select" name='linkDest' class='form-control selectpicker select-dest-field' data-live-search='true' required>
                        <option value='' disabled selected>Select a field</option>  
                        <?php
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
                        ?>
                    </select> 
                </div> 
            </div>
            <h4>Trigger conditions (Max. 10)</h4>
            <div>
                <label>Push data from the source project to the linked project, when the following conditions are met:</label>
                <ul>
                    <li>E.g., [event_name][instrument_name_complete] = "2"</li>
                    <li>E.g., [event_name][variable_name] = "1"</li>
                </ul>
                <p>Where [event_name] = only in longitudinal projects<br/>Where [instrument_name] = form copied from source to linked project</p>
            </div>
            <?php foreach($settings["triggers"] as $index => $trigger) :?>
            <div class="form-group trigger-and-data-wrapper">
                <div class="det-trigger">
                    <div class="row">
                        <div class="col-sm-2">
                            <label>Condition:</label>
                        </div>
                        <div class="col-sm-9"></div>
                        <div class="col-sm-1">
                            <span class="fa fa-plus add-trigger-btn"></span>
                            <?php if ($index > 0): ?>
                                <span class="fa fa-minus delete-trigger-btn"></span>
                            <?php endif;?>
                        </div>
                    </div>
                    <input name="triggers[]" type="text" class="form-control det-trigger-input" value="<?php print $trigger; ?>" required>
                </div>
                <p>Copy the following instruments/fields from source project to linked project when the above condition is true:</p>
                <div class="row" style="margin-top:20px">
                    <div class="col-sm-2"><button type="button" class="btn btn-primary add-instr-btn">+ Instrument</button></div>
                    <div class="col-sm-2"><button type="button" class="btn btn-primary add-field-btn">+ Field</button></div>
                </div>
                <?php
                    $sourceFields = $settings["sourceFields"][$index];
                    $destFields = $settings["destFields"][$index];
                    foreach($sourceFields as $i => $source)
                    {
                        ?>
                        <div class='row det-field' style='margin-top:20px'>
                            <div class='col-sm-2'><p>Copy field</p></div>
                            <div class='col-sm-3'>
                                <select name='sourceFields[<?php print $index;?>][]' class='form-control selectpicker' data-live-search='true' required>
                                <option value='' disabled>Select a field</option> 
                                <?php
                                    foreach($metadata as $field_name => $data)
                                    {
                                        if ($data["field_type"] != "descriptive")
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
                                    }
                                    foreach ($instrument_names as $unique_name=>$label)
                                    {
                                        if ($unique_name == $source)
                                        {
                                            print "<option value='{$unique_name}_complete' selected>{$unique_name}_complete</option>";
                                        }
                                        else
                                        {
                                            print "<option value='{$unique_name}_complete'>{$unique_name}_complete</option>";
                                        }
                                    }
                                ?>
                                </select>
                            </div>
                            <div class='col-sm-1'><p>to</p></div>
                            <div class='col-sm-3'>
                                <select name='destFields[<?php print $index;?>][]' class='form-control selectpicker select-dest-field' data-live-search='true' required>
                                <option value='' disabled>Select a field</option>
                                <?php
                                    foreach($dest_fields["fields"] as $field_name)
                                    {
                                        if ($field_name == $destFields[$i])
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

                    $sourceInstr = $settings["sourceInstr"][$index];
                    $destInstr = $settings["destInstr"][$index];
                    foreach($sourceInstr as $i => $source)
                    {
                        ?>
                        <div class='row det-field' style='margin-top:20px'>
                            <div class='col-sm-2'><p>Copy instrument</p></div>
                            <div class='col-sm-3'>
                                <select name='sourceInstr[<?php print $index;?>][]' class='form-control selectpicker' data-live-search='true' required>
                                <option value='' disabled>Select an instrument</option> 
                                <?php
                                    foreach ($instrument_names as $unique_name=>$label)
                                    {
                                        if ($unique_name == $source)
                                        {
                                            print "<option value='{$unique_name}' selected>{$unique_name}</option>";
                                        }
                                        else
                                        {
                                            print "<option value='{$unique_name}'>{$unique_name}</option>";
                                        }
                                    }
                                ?>
                                </select>
                            </div>
                            <div class='col-sm-1'><p>to</p></div>
                            <div class='col-sm-3'>
                                <select name='destInstr[<?php print $index;?>][]' class='form-control selectpicker select-dest-field' data-live-search='true' required>
                                <option value='' disabled>Select an instrument</option>
                                <?php
                                    foreach($dest_fields["instruments"] as $instrument)
                                    {
                                        if ($instrument == $destInstr[$i])
                                        {
                                            print "<option value='$instrument' selected>$instrument</option>";
                                        }
                                        else
                                        {
                                            print "<option value='$instrument'>$instrument</option>";
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
            <?php endforeach; ?>
            <h4>Confirm the following</h4>
            <div class="row">
                <div class="form-check col" style="margin-left:15px">
                    <div class="row"><label>Overwrite data in destination project every time data is saved</label></div>
                    <?php if ($settings["overwrite-data"] == "overwrite"):?>
                    <input type="radio" name="overwrite-data" class="form-check-input" value="overwrite" checked required><label class="form-check-label">Yes</label>
                    <br>
                    <input type="radio" name="overwrite-data" class="form-check-input" value="normal" required><label class="form-check-label">No</label>
                    <?php else:?>
                    <input type="radio" name="overwrite-data" class="form-check-input" value="overwrite" required><label class="form-check-label">Yes</label>
                    <br>
                    <input type="radio" name="overwrite-data" class="form-check-input" value="normal" checked required><label class="form-check-label">No</label>
                    <?php endif; ?>
                </div>
                <div class="form-check col">
                    <div class="row"><label>Use DAGs (Will only push DAGs one-to-one)</label></div>
                    <?php if ($settings["use-dags"] == "1"):?>
                    <input type="radio" name="use-dags" class="form-check-input" value="1" checked required><label class="form-check-label">Yes</label>
                    <br>
                    <input type="radio" name="use-dags" class="form-check-input" value="0" required><label class="form-check-label">No</label>
                    <?php else:?>
                    <input type="radio" name="use-dags" class="form-check-input" value="1" required><label class="form-check-label">Yes</label>
                    <br>
                    <input type="radio" name="use-dags" class="form-check-input" value="0" checked required><label class="form-check-label">No</label>
                    <?php endif; ?>
                </div>
            </div>
            <button id="create-det-btn" type="submit" class="btn btn-primary" style="margin-top:20px">Create DET</button>
        </form>
        <?php
    }

    /**
     * Prints a new DET form
     */
    public function newForm()
    {
        ?>
        <form class="jumbotron">
            <div class="form-group">
                <label>Linked Project</label>
                <select name="dest-project" id="destination-project-select" class="form-control selectpicker" data-live-search="true" required>
                    <option value="" disabled selected>Select a project</option>
                    <?php
                        $projects = $this->getProjects();
                        foreach($projects as $project)
                        {
                            print "<option value='". $project["project_id"] . "'>" . $project["app_title"] . "</option>";
                        }
                    ?>
                </select>
            </div>
            <h4>Subject Creation</h4>
            <div class="form-group">
                <label>Create a subject/record ID in the linked project, PID xyz, when the following conditions are met:</label>
                <ul>
                    <li>E.g., [event_name][instrument_name_complete] = "2"</li>
                    <li>E.g., [event_name][variable_name] = "1"</li>
                </ul>
                <p>Where [event_name] = only in longitudinal projects<br/>Where [instrument_name] = form copied from source to linked project</p>
                <input id="create-record-input" name="create-record-cond" type="text" class="form-control" value="<?php print $settings["create-record-cond"]?>" required>
            </div>
            <div class='row link-field form-group'> 
                <div class='col-sm-2'><p>Link source project field</p></div> 
                <div class='col-sm-3'>
                    <select name='linkSource' class='form-control selectpicker' data-live-search='true' required>
                        <option value='' disabled selected>Select a field</option>
                        <?php
                            $metadata = REDCap::getDataDictionary("array");
                            foreach($metadata as $field_name => $data)
                            {
                                print "<option value='$field_name'>$field_name</option>";
                            }
                            $instrument_names = REDCap::getInstrumentNames();
                            foreach ($instrument_names as $unique_name=>$label)
                            {
                                print "<option value='{$unique_name}_complete'>{$unique_name}_complete</option>";
                            }
                        ?>
                    </select> 
                </div> 
                <div class='col-sm-2'><p>to linked project field</p></div> 
                <div class='col-sm-3'>
                    <select id="link-dest-select" name='linkDest' class='form-control selectpicker select-dest-field' data-live-search='true' required>
                        <option value='' disabled selected>Select a field</option>  
                    </select> 
                </div> 
            </div>
            <h4>Trigger conditions (Max. 10)</h4>
            <div>
                <label>Push data from the source project to the linked project, when the following conditions are met:</label>
                <ul>
                    <li>E.g., [event_name][instrument_name_complete] = "2"</li>
                    <li>E.g., [event_name][variable_name] = "1"</li>
                </ul>
                <p>Where [event_name] = only in longitudinal projects<br/>Where [instrument_name] = form copied from source to linked project</p>
            </div>
            <div class="form-group trigger-and-data-wrapper">
                <div class="det-trigger">
                    <div class="row">
                        <div class="col-sm-2">
                            <label>Condition:</label>
                        </div>
                        <div class="col-sm-9"></div>
                        <div class="col-sm-1">
                            <span class="fa fa-plus add-trigger-btn"></span>
                        </div>
                    </div>
                    <input name="triggers[]" type="text" class="form-control det-trigger-input" required>
                </div>
                <p>Copy the following instruments/fields from source project to linked project when the above condition is true:</p>
                <div class="row" style="margin-top:20px">
                    <div class="col-sm-2"><button type="button" class="btn btn-primary add-instr-btn">+ Instrument</button></div>
                    <div class="col-sm-2"><button type="button" class="btn btn-primary add-field-btn">+ Field</button></div>
                </div>
            </div>
            <h4>Confirm the following</h4>
            <div class="row">
                <div class="form-check col" style="margin-left:15px">
                    <div class="row"><label>Overwrite data in destination project every time data is saved</label></div>
                    <input type="radio" name="overwrite-data" class="form-check-input" value="overwrite" required><label class="form-check-label">Yes</label>
                    <br>
                    <input type="radio" name="overwrite-data" class="form-check-input" value="normal" required><label class="form-check-label">No</label>
                </div>
                <div class="form-check col">
                    <div class="row"><label>Use DAGs (Will only push DAGs one-to-one)</label></div>
                    <input type="radio" name="use-dags" class="form-check-input" value="1" required><label class="form-check-label">Yes</label>
                    <br>
                    <input type="radio" name="use-dags" class="form-check-input" value="0" required><label class="form-check-label">No</label>
                </div>
            </div>
            <button id="create-det-btn" type="submit" class="btn btn-primary" style="margin-top:20px">Create DET</button>
        </form>
        <?php
    }

    /**
     * REDCap hook is called immediately after a record is saved. Will retrieve the DET settings,
     * import data according to DET.
     */
    public function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        if ($project_id == $this->getProjectId())
        {
            $settings = json_decode($this->getProjectSetting("det_settings"), true);

            // Get DET settings
            $dest_project = $settings["dest-project"];
            $create_record_trigger = $settings["create-record-cond"];
            $link_source = $settings["linkSource"];
            $dest_source = $settings["destSource"];
            $triggers = $settings["triggers"];
            $source_fields = $settings["sourceFields"];
            $dest_fields = $settings["destFields"];
            $overwrite_data = $settings["overwrite-data"];
            $use_dags = $settings["use-dags"];
            
            // Get current record data
            $record_data = REDCap::getData("array", $record, array(), $event_id, $group_id)[$record];

            if ($create_record_trigger)
            {
                $dest_record_data[$dest_source] = $record_data[$link_source];
            }

            foreach($triggers as $trigger)
            {

            }

            $dest_record_data = $record_data;

            // Save DET data in destination project;
            $save_response = REDCap::saveData($dest_project, "array", $dest_record_data, $overwrite_data);

            if (!empty($save_response["errors"]))
            {
                REDCap::logEvent("DET: Errors", json_encode($save_response["errors"]), null, $record, $event_id, $project_id);
            }
             
            if (!empty($save_response["warnings"]))
            {
                REDCap::logEvent("DET: Warnings", json_encode($save_response["warnings"]), null, $record, $event_id, $project_id);
            }

            if (!empty($save_response["ids"]))
            {
                REDCap::logEvent("DET: Modified/Saved the following records", json_encode($save_response["ids"]), null, null, null, $dest_project);
            }
        }
    }
}