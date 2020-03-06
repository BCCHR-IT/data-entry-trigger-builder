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
        $parts = array();
        $previous = array();

        $i = 0;
        while($i < strlen($syntax))
        {
            $char = $syntax[$i];
            switch($char)
            {
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
                    if ($syntax[$i-1] == " ")
                    {
                        $parts[] = " ";
                    }
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

        $logic_operator_alt = array("==", "!=", "!=", ">", "<", ">=", ">=", "<=", "<=", "||", "&&", "=");
        $logical_operators = array("eq", "ne", "neq", "gt", "lt", "ge", "gte", "lte", "le", "or", "and", "eq");

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
                        $errors[] = "LINE Cannot have a comparison operator <strong>$part</strong> as the first part in syntax.";
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
        $projects = array();
        $sql = "select project_id, app_title from redcap_projects";
        $query_result = $this->query($sql);
        while($row = db_fetch_assoc($query_result))
        {
            $projects[] = $row;
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
        $instr = array_values(array_unique(array_column($metadata, "form_name")));
        $fields = array_keys($metadata);
        return json_encode(array("instruments" => $instr, "fields" => $fields));
    }
}