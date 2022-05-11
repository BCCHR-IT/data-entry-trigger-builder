<?php

require_once "DataEntryTriggerBuilder.php";

/**
 * Display REDCap header.
 */
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

$data_entry_trigger_builder = new BCCHR\DataEntryTriggerBuilder\DataEntryTriggerBuilder();
if (!empty($_POST["json"])) {
    $posted_json = $_POST["json"];
    $settings = json_decode($posted_json, true);
    if ($settings == null)
    {
        $import_err_msg = "Invalid JSON! Please check your DET settings.";
    }
}

if ($settings == null)
{
    $settings = json_decode($data_entry_trigger_builder->getProjectSetting("det_settings"), true);
    $dest_fields = $data_entry_trigger_builder->retrieveProjectMetadata($settings["dest-project"]);
}

$Proj = new Project();
?>
<html>
    <head>
        <!-- boostrap-select css and js-->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.15/dist/css/bootstrap-select.min.css">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.15/dist/js/bootstrap-select.min.js"></script>
        <style>
            p {
                max-width: 100%;
            }
            .fa:hover {
                color:grey;
            }
            .table td, .table th {
                padding: .5%;
            }
            .table-input {
                border: none;
                background-color: transparent;
            }
            .table tr > td:first-child,
            .table tr > th:first-child {
                width: 40% ;
            }
            .table tr > td:nth-child(2),
            .table tr > th:nth-child(2)  {
                width: 40% ;
            }
            .table tr > td:nth-child(3), 
            .table tr > td:nth-child(4),
            .table tr > th:nth-child(3), 
            .table tr > th:nth-child(4) {
                width: 10% ;
            }
            .error {
                border: 2px solid red;
            }
            .error-msg {
                color: red
            }
            .saved {
                color: #007bff
            }
            textarea {
                resize: vertical;
            }
            .trigger-and-data-wrapper {
                border: 1px solid lightgrey;
                padding: 10px;
            }
        </style>
        <script>
            var projectOptions = [
                <?php
                $projects = $data_entry_trigger_builder->getProjects();
                foreach($projects as $project) { print "\"<option value='". $project["project_id"] . "'>" . $project["project_id"] . " - " . $project["app_title"] . "</option>\","; }
                ?>
            ];
        </script>
        <script src="<?php print $module->getUrl("functions.js");?>" type="text/javascript"></script>
    </head>
    <body>
        <?php if ($Proj->project['status'] > 0 || !empty($settings)): ?> 
            <div style="position: sticky; top: 0; width: 100%; background-color:#ff9800; padding:5px; text-align:center; z-index:200;">
                <?php if ($Proj->project['status'] > 0): ?><h6><b>This project is currently in production, be careful with your changes!</b></h6><?php endif; ?>
                <?php if (!empty($settings)): ?><h6><b>WARNING: Any changes made to the REDCap project, after the DET has been created, has the potential to break it. After you’ve updated your project, please make sure to update the DET in accordance with your changes.</b></h6><?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="container jumbotron">
            <h2>Data Entry Trigger Builder</h2>
            <p><b>LIMITATIONS*: This module will work will classical and longitudinal projects, but is currently incompatible with repeatable events.</b></p>
            <?php if (!empty($settings)): ?>
            <p><b>DET was last changed on <span class="saved"><?php print $data_entry_trigger_builder->getProjectSetting("saved_timestamp");?></span> by <span class="saved"><?php print $data_entry_trigger_builder->getProjectSetting("saved_by");?></span></b></p>
            <?php endif; ?>
            <hr/>
            <h5>Import/Export Your DET Settings</h5>
            <p>
                If you've created a JSON string containing your DET settings, you may import them into the module, or you may export your current DET settings (If they exist). When importing settings for projects on a different REDCap instance that have the same structure, change the destination project id before import. 
            </p>
            <p><b>IMPORTANT: Once you've imported your DET settings, you must still save them by clicking "Save DET" at the bottom of the page.</b></p>
            <button type="button" data-toggle="modal" data-target="#upload-json-modal" class="btn btn-primary btn-sm">Import DET Settings</button>
            <?php if (!empty($settings)): ?><button type="button" data-toggle="modal" data-target="#export-json-modal" class="btn btn-primary btn-sm">Export DET Settings</button><?php endif; ?>
            <?php if (!empty($import_err_msg)): ?>
            <p style="color:red"><b><?php print $import_err_msg; ?></b></p>
            <?php endif;?>
            <hr/>
            <!-- <?php if (!empty($settings)): ?>
            <h5>Download Release Notes</h5>
            <form id="download-form" action="<?php print $module->getUrl("downloadReleaseNotes.php");?>" method="post">
                <button id="download-release-notes-btn" type="submit" class="btn btn-primary" style="margin-top:20px">Download Release Notes</button>
            </form>
            <hr/>
            <?php endif;?> -->
            <form id="det-form" method="post" data-source-is-longitudinal="<?php REDCap::isLongitudinal() ? print "yes" : print "no"; ?>">
                <div id="main-form">
                    <h5>Triggers (Max. 10)</h5>
                    <div id="trigger-instr" style="margin-bottom:20px">
                        <label>Push data from a source project to a linked project, when the following conditions are met:</label>
                        <ul>
                            <li>E.g., [event_name][instrument_name_complete] = "2"</li>
                            <li>E.g., [event_name][variable_name] = "1"</li>
                        </ul>
                        <p>Where [event_name] = only in longitudinal projects<br/>Where [instrument_name] = form copied from source to linked project<br/>Where [variable_name] = field copied from source to linked project</p>
                        <p><strong>NOTE:</strong> If [variable_name] is a checkbox field please use the following format: [variable_name<strong>(</strong>code<strong>)</strong>]</p>
                        <p>
                            Multiple conditions can be chained in the same trigger using AND/OR. When creating a trigger 
                            <b>AND must be written as &&</b>, and 
                            <b>OR must be written as ||</b>
                        </p>
                        <ul>
                            <li>E.g., [event_name][instrument_name_complete] = "2" && [event_name][variable_name] = "1"</li>
                            <li>E.g., [event_name][instrument_name_complete] = "2" || [event_name][variable_name] = "1"</li>
                        </ul>
                        <p>Remember to add spaces between all your REDCap variables, and logical quantifiers</p>
                        <p>The following qualifiers are of valid use within the module:</p>
                        <table border="1" style="margin-bottom:10px">
                            <colgroup>
                                <col align="center" class="alternates">
                                <col class="meaning">
                                <col class="example">
                            </colgroup>
                            <thead><tr>
                                <th align="center">Qualifier</th>
                                <th>Syntax Example</th>
                                <th>Meaning</th>
                            </tr></thead>
                            <tbody>
                                <tr>
                                    <td align="center">=, ==</td>
                                    <td>$a = $b</td>
                                    <td>equals</td>
                                </tr>
                                <tr>
                                    <td align="center"><>, !=</td>
                                    <td>$a <> $b</td>
                                    <td>not equals</td>
                                </tr>
                                <tr>
                                    <td align="center">></td>
                                    <td>$a > $b</td>
                                    <td>greater than</td>
                                </tr>
                                <tr>
                                    <td align="center"><</td>
                                    <td>$a < $b</td>
                                    <td>less than</td>
                                </tr>
                                <tr>
                                    <td align="center">>=</td>
                                    <td>$a >= $b</td>
                                    <td>greater than or equal</td>
                                </tr>
                                <tr>
                                    <td align="center"><=</td>
                                    <td>$a <= $b</td>
                                    <td>less than or equal</td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="button" id="add-trigger-btn" class="btn btn-primary btn-sm">Add Trigger</button>
                    </div>
                    <?php if (!empty($settings)): foreach($settings["triggers"] as $index => $trigger_obj): ?>
                    <?php 
                        $index = htmlspecialchars($index, ENT_QUOTES); 
                        $trigger = htmlspecialchars($trigger_obj["trigger"], ENT_QUOTES); 
                    ?>
                    <div class="form-group trigger-and-data-wrapper">
                        <div class="det-trigger">
                            <div class="row">
                                <div class="col-sm-2">
                                    <label><h6>Trigger:</h6></label>
                                </div>
                                <div class="col-sm-9"></div>
                                <div class="col-sm-1" style="text-align: center;">
                                    <span class="fa fa-trash-alt delete-trigger-btn"></span>
                                </div>
                            </div>
                            <textarea rows="1" name="triggers[<?php print $index;?>][trigger]" class="form-control det-trigger-input" required><?php print str_replace("\"", "'", $trigger); ?></textarea>
                        </div>
                        <h6 style="margin-top:10px">Select a Linked Project</h6>
                        <p>The module will move the data into the chosen project.</p>
                        <div class="form-group">
                            <select name="triggers[<?php print $index;?>][dest-project]" class="destination-project-select form-control selectpicker" data-live-search="true" required>
                                <option value="" disabled>Select a project</option>
                                <?php
                                    foreach($projects as $project)
                                    {
                                        if ($project["project_id"] != $_GET["pid"]) {
                                            if (!empty($trigger_obj["dest-project"]) && $project["project_id"] == $trigger_obj["dest-project"])
                                            {
                                                print "<option value='". $project["project_id"] . "' selected>" . $project["project_id"] . " - " . $project["app_title"] . "</option>";
                                            }
                                            else
                                            {
                                                print "<option value='". $project["project_id"] . "'>" . $project["project_id"] . " - " . $project["app_title"] . "</option>";
                                            }
                                        }
                                    }
                                ?>
                            </select>
                            <?php 
                            if (!empty($trigger_obj["dest-project"])) { 
                                $DestProj = new Project($trigger_obj["dest-project"]); 
                                if ($DestProj->project['status'] > 0) {
                                    print "<p><b><i>This project is currently in production.</i></p></b>";
                                }
                            }
                            ?>
                        </div>
                        <h6>Record Linkage</h6>
                        <p>
                            Create subjects/push data to linked project using variables in source and linked project. When the trigger is met, then records between the source and linked project will be linked via the chosen fields. <b>When linking projects with anything other than the record ID fields, 'Auto-numbering for records' must be turned on in the destination project.</b>
                        </p>
                        <div class='row link-field form-group'> 
                            <div class='col-sm-12' style="margin-bottom:10px">
                                <div class='class-sm-12'><label>Link source project field</label></div>
                                <div class='row'>
                                    <?php if (REDCap::isLongitudinal()): ?>
                                        <div class='col-sm-6'>
                                            <input class="linkSourceEvent source-events-autocomplete form-control" name='triggers[<?php print $index;?>][linkSourceEvent]' placeholder="Type to search for event" value="<?php print htmlspecialchars($trigger_obj["linkSourceEvent"], ENT_QUOTES); ?>" required>
                                        </div>
                                    <?php endif;?>
                                    <div class='col-sm-6'>
                                        <input class="linkSource source-fields-autocomplete form-control" name='triggers[<?php print $index;?>][linkSource]' placeholder="Type to search for field" value="<?php print htmlspecialchars($trigger_obj["linkSource"], ENT_QUOTES); ?>" required>
                                    </div> 
                                </div>
                            </div>
                            <div class='col-sm-12' style="margin-bottom:20px">
                                <div class='class-sm-12' id="link-source-text"><label>To linked project field</label></div>
                                <div class='row'>
                                    <div class='col-sm-6 dest-event-wrapper' <?php if(empty($trigger_obj["linkDestEvent"])) {print "style='display:none'";} ?>>
                                        <input class='linkDestEvent dest-events-autocomplete form-control' name='triggers[<?php print $index;?>][linkDestEvent]' placeholder="Type to search for event" value="<?php print htmlspecialchars($trigger_obj["linkDestEvent"], ENT_QUOTES); ?>" required>
                                    </div>
                                    <div id="link-source-wrapper" class='col-sm-6'>
                                        <input class='linkDest dest-fields-autocomplete form-control' name='triggers[<?php print $index;?>][linkDest]' placeholder="Type to search for field" value="<?php print htmlspecialchars($trigger_obj["linkDest"], ENT_QUOTES); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6" style="margin-bottom:20px">
                                <h6>Create Empty Records</h6>
                                <div class="class-sm-12"><label>If 'yes' is chosen, then an empty record is created when the trigger is met. Use this option when you don't want any data moved with the triggers.</label></div>
                                <div class="form-check col-sm-12">
                                    <?php if (empty($settings)): ?>
                                        <input type="radio" name="triggers[<?php print $index;?>][create-empty-record]" class="form-check-input" value="1" required><label class="form-check-label">Yes</label>
                                        <br>
                                        <input type="radio" name="triggers[<?php print $index;?>][create-empty-record]" class="form-check-input" value="0" required><label class="form-check-label">No</label>
                                    <?php else:?>
                                        <?php if ($trigger_obj["create-empty-record"] == "1"):?>
                                        <input type="radio" name="triggers[<?php print $index;?>][create-empty-record]" class="form-check-input" value="1" checked required><label class="form-check-label">Yes</label>
                                        <br>
                                        <input type="radio" name="triggers[<?php print $index;?>][create-empty-record]" class="form-check-input" value="0" required><label class="form-check-label">No</label>
                                        <?php else:?>
                                        <input type="radio" name="triggers[<?php print $index;?>][create-empty-record]" class="form-check-input" value="1" required><label class="form-check-label">Yes</label>
                                        <br>
                                        <input type="radio" name="triggers[<?php print $index;?>][create-empty-record]" class="form-check-input" value="0" checked required><label class="form-check-label">No</label>
                                        <?php endif; ?>
                                    <?php endif;?>
                                </div>
                            </div>
                            <div class='col-sm-6'>
                                <h6>Add Pre/Postfix to Linked Field (Optional)</h6>
                                <div class='class-sm-12'><label>Add a static prefix or postfix to the linked source field when moving data. If neither prefix or postfix is selected, then a prefix is used.</label></div>
                                <div class='row'>
                                    <div class='col-sm-6'>
                                        <input class='form-control' name='triggers[<?php print $index;?>][prefixPostfixStr]' placeholder="Enter your prefix/postfix" value="<?php print htmlspecialchars($trigger_obj["prefixPostfixStr"], ENT_QUOTES); ?>">
                                    </div>
                                    <div class='col-sm-6'>
                                        <?php if (empty($trigger_obj["prefixOrPostfix"])): ?>
                                        <input type="checkbox" name="triggers[<?php print $index;?>][prefixOrPostfix]" class="form-check-input" value="pre"><label class="form-check-label">Prefix</label>
                                        <br>
                                        <input type="checkbox" name="triggers[<?php print $index;?>][prefixOrPostfix]" class="form-check-input" value="post"><label class="form-check-label">Postfix</label>
                                        <?php elseif ($trigger_obj["prefixOrPostfix"] == "pre"): ?>
                                        <input type="checkbox" name="triggers[<?php print $index;?>][prefixOrPostfix]" class="form-check-input" value="pre" checked><label class="form-check-label">Prefix</label>
                                        <br>
                                        <input type="checkbox" name="triggers[<?php print $index;?>][prefixOrPostfix]" class="form-check-input" value="post"><label class="form-check-label">Postfix</label>
                                        <?php else: ?>
                                        <input type="checkbox" name="triggers[<?php print $index;?>][prefixOrPostfix]" class="form-check-input" value="pre"><label class="form-check-label">Prefix</label>
                                        <br>
                                        <input type="checkbox" name="triggers[<?php print $index;?>][prefixOrPostfix]" class="form-check-input" value="post" checked><label class="form-check-label">Postfix</label>
                                        <?php endif;?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <h6>Data Movement</h6>
                        <p>Copy the following instruments/fields from source project to linked project when the trigger is met:</p>
                        <button type="button" data-toggle="modal" data-target="#add-field-modal" class="btn btn-primary btn-xs add-field-btn">Add Field</button>
                        <button type="button" data-toggle="modal" data-target="#add-instr-modal" class="btn btn-primary btn-xs add-instr-btn">Add Instrument</button>
                        <br/><br/>
                        <table id="<?php print "table-$index"; ?>" class="table">
                            <thead>
                                <tr>
                                <th>From Source Project</th>
                                <th>To Linked Project</th>
                                <th>Edit?</th>
                                <th>Delete?</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                                $pipingSourceEvents = $trigger_obj["pipingSourceEvents"];
                                $pipingDestEvents = $trigger_obj["pipingDestEvents"];
                                $pipingSourceFields = $trigger_obj["pipingSourceFields"];
                                $pipingDestFields = $trigger_obj["pipingDestFields"];

                                foreach($pipingSourceFields as $i => $source)
                                {
                                    $pipingSourceEvent = htmlspecialchars($pipingSourceEvents[$i], ENT_QUOTES);
                                    $source = htmlspecialchars($source, ENT_QUOTES);
                                    $pipingDestEvent = htmlspecialchars($pipingDestEvents[$i], ENT_QUOTES);
                                    $dest = htmlspecialchars($pipingDestFields[$i], ENT_QUOTES);

                                    print "<tr class='trigger-field-row'><td>";
                                    if (!empty($pipingSourceEvent))
                                    {
                                        print "[" . $pipingSourceEvent . "]";
                                        print "<input class='pipingSourceEvents' type='hidden' name='triggers[$index][pipingSourceEvents][]' value='" . $pipingSourceEvent . "'>";
                                    }
                                    print "[" . $source . "]";
                                    print "<input class='pipingSourceFields' type='hidden' name='triggers[$index][pipingSourceFields][]' value='" . $source . "'></td><td>";
                                    if (!empty($pipingDestEvents[$i]))
                                    {
                                        print "[" . $pipingDestEvent . "]";
                                        print "<input class='pipingDestEvents' type='hidden' name='triggers[$index][pipingDestEvents][]' value='" . $pipingDestEvent . "'>";
                                    }
                                    print "[" . $dest . "]";
                                    print "<input class='pipingDestFields' type='hidden' name='triggers[$index][pipingDestFields][]' value='" . $dest . "'>";
                                    print "</td><td><span class='fa fa-pencil-alt' onclick='fillPipingFieldForm(this)'></span></td>";
                                    print "<td><span class='fa fa-trash-alt delete-trigger-field'></span></td>";
                                    print "</tr>";
                                }

                                $setDestEvents = $trigger_obj["setDestEvents"];
                                $setDestFields = $trigger_obj["setDestFields"];
                                $setDestFieldsValues = $trigger_obj["setDestFieldsValues"];

                                foreach($setDestFields as $i => $source)
                                {
                                    $setDestFieldsValue = htmlspecialchars($setDestFieldsValues[$i], ENT_QUOTES);
                                    $setDestEvent = htmlspecialchars($setDestEvents[$i], ENT_QUOTES);
                                    $source = htmlspecialchars($source, ENT_QUOTES);

                                    print "<tr class='trigger-field-row'><td>";
                                    if (!empty($setDestFieldsValue))
                                    {
                                        print "'" . $setDestFieldsValue . "'";
                                        print "<input class='setDestFieldsValues' type='hidden' name='triggers[$index][setDestFieldsValues][]' value='" . $setDestFieldsValue . "'></td><td>";
                                    }
                                    if (!empty($setDestEvent))
                                    {
                                        print "[" . $setDestEvent . "]";
                                        print "<input class='setDestEvents' type='hidden' name='triggers[$index][setDestEvents][]' value='" . $setDestEvent . "'>";
                                    }
                                    print "[" . $source . "]";
                                    print "<input class='setDestFields' type='hidden' name='triggers[$index][setDestFields][]' value='" . $source . "'>";
                                    print "</td><td><span class='fa fa-pencil-alt' onclick='fillFieldForm(this)'></span></td>";
                                    print "<td><span class='fa fa-trash-alt delete-trigger-field'></span></td>";
                                    print "</tr>";
                                }

                                $sourceInstr = $trigger_obj["sourceInstr"];
                                $sourceInstrEvents = $trigger_obj["sourceInstrEvents"];
                                $destInstrEvents = $trigger_obj["destInstrEvents"];

                                foreach($sourceInstr as $i => $source)
                                {
                                    $sourceInstrEvent = htmlspecialchars($sourceInstrEvents[$i], ENT_QUOTES);
                                    $source = htmlspecialchars($source, ENT_QUOTES);
                                    $destInstrEvent = htmlspecialchars($destInstrEvents[$i], ENT_QUOTES);

                                    print "<tr class='trigger-field-row'><td>";
                                    if (!empty($sourceInstrEvent))
                                    {
                                        print "[" . $sourceInstrEvent . "]";
                                        print "<input class='sourceInstrEvents' type='hidden' name='triggers[$index][sourceInstrEvents][]' value='" . $sourceInstrEvent . "'>";
                                    }
                                    print "[" . $source . "]";
                                    print "<input class='sourceInstr' type='hidden' name='triggers[$index][sourceInstr][]' value='" . $source . "'></td><td>";
                                    if (!empty($destInstrEvent))
                                    {
                                        print "[" . $destInstrEvent . "]";
                                        print "<input class='destInstrEvents' type='hidden' name='triggers[$index][destInstrEvents][]' value='" . $destInstrEvent . "'>";
                                    }
                                    else 
                                    {
                                        print "<i>Data is moving to a classic project, so there are no events</i>";
                                    }
                                    print "</td><td><span class='fa fa-pencil-alt' onclick='fillInstrForm(this)'></span></td>";
                                    print "<td><span class='fa fa-trash-alt delete-trigger-field'></span></td>";
                                    print "</tr>";
                                }
                            ?>
                            </tbody>
                        </table>
                        <h6>Generate Survey URLs (Optional)</h6>
                        <p>If specified, the destination project will generate a survey url for the participant to redirect to. If the trigger is met, a survey url will generate after the data is moved.</p>
                        <div class="row">
                            <div class="form-group col-6">
                                <label>Specify the destination instrument the module will generate a survey url from.</label>
                                <div class="row">
                                    <div class='col-sm-6 ui-front dest-event-wrapper' <?php if(empty($trigger_obj["surveyUrlEvent"])) {print "style='display:none'";} ?>>
                                        <input class='surveyUrlEvent dest-events-autocomplete form-control' name="triggers[<?php print $index;?>][surveyUrlEvent]" value="<?php print htmlspecialchars($trigger_obj["surveyUrlEvent"], ENT_QUOTES); ?>" placeholder="Type to search for event">
                                    </div>
                                    <div class='col-sm-6 ui-front'>
                                        <input class='surveyUrl form-control' name="triggers[<?php print $index;?>][surveyUrl]" value="<?php print htmlspecialchars($trigger_obj["surveyUrl"], ENT_QUOTES); ?>" placeholder="Type to search for instrument">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group col-6">
                                <label>Specify the source field the survey url will be saved to, for redirection.</label>
                                <div class="row">
                                    <?php if (REDCap::isLongitudinal()): ?>
                                    <div class='col-sm-6'>
                                        <input class="saveUrlEvent source-events-autocomplete form-control" name='triggers[<?php print $index;?>][saveUrlEvent]' value="<?php print htmlspecialchars($trigger_obj["saveUrlEvent"], ENT_QUOTES); ?>" placeholder="Type to search for event">
                                    </div>
                                    <?php endif;?>
                                    <div class='col-sm-6'>
                                        <input class="saveUrlField source-fields-autocomplete form-control" name='triggers[<?php print $index;?>][saveUrlField]' value="<?php print htmlspecialchars($trigger_obj["saveUrlField"], ENT_QUOTES); ?>" placeholder="Type to search for field">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <h6>Confirm the following</h6>
                        <div class='row form-group'> 
                            <div class="col-sm-6">
                                <label>Overwrite data in destination project every time data is saved? This determines whether to push blank data over to the destination project.</label>
                                <div class="form-check col-sm-12">
                                    <?php if ($trigger_obj["overwrite-data"] == "overwrite"):?>
                                    <input type="radio" name="triggers[<?php print $index;?>][overwrite-data]" class="form-check-input" value="overwrite" checked required><label class="form-check-label">Yes</label>
                                    <br>
                                    <input type="radio" name="triggers[<?php print $index;?>][overwrite-data]" class="form-check-input" value="normal" required><label class="form-check-label">No</label>
                                    <?php else:?>
                                    <input type="radio" name="triggers[<?php print $index;?>][overwrite-data]" class="form-check-input" value="overwrite" required><label class="form-check-label">Yes</label>
                                    <br>
                                    <input type="radio" name="triggers[<?php print $index;?>][overwrite-data]" class="form-check-input" value="normal" checked required><label class="form-check-label">No</label>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class='col-sm-6'>
                                <label>Import data access groups (DAGs) every time data is saved? The module can only import DAGs if they have a one-to-one relationship with the destination project.</label>
                                <div class="form-check col-sm-12">
                                    <?php if ($trigger_obj["import-dags"] == "1"):?>
                                    <input type="radio" name="triggers[<?php print $index;?>][import-dags]" class="form-check-input" value="1" checked required><label class="form-check-label">Yes</label>
                                    <br>
                                    <input type="radio" name="triggers[<?php print $index;?>][import-dags]" class="form-check-input" value="0" required><label class="form-check-label">No</label>
                                    <?php else:?>
                                    <input type="radio" name="triggers[<?php print $index;?>][import-dags]" class="form-check-input" value="1" required><label class="form-check-label">Yes</label>
                                    <br>
                                    <input type="radio" name="triggers[<?php print $index;?>][import-dags]" class="form-check-input" value="0" checked required><label class="form-check-label">No</label>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; endif;?>
                    <button id="create-det-btn" type="submit" class="btn btn-primary" style="margin-top:20px">Save DET</button>
                </div>
            </form>
            <div class="modal fade" id="add-field-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5>Add Field</h5>
                            <button type="button" class="close close-modal-btn" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <input class="table-id" type="hidden">
                            <div class='row'>
                                <div class="col-sm-4"><label>Copy from source</label></div>
                                <div class="col-sm-1"><button style="border:none; background-color: transparent" title="Switch to enter custom value, or select field" class="fas fa-exchange-alt"></button></div>
                            </div>
                            <div id="source-input" class="row ui-front" style="display:none">
                                <div class="col-sm-6">
                                    <input id="field-value" class='form-control' placeholder="Type the value to tranfer">
                                </div>
                            </div>
                            <div id="source-select" class="row">
                                <?php if (REDCap::isLongitudinal()): ?>
                                <div class='col-sm-6 ui-front'>
                                    <input id="event-select" class="source-events-autocomplete form-control" placeholder="Type to search for event">
                                </div>
                                <?php endif;?>
                                <div class='col-sm-6 ui-front'>
                                    <input class="source-fields-autocomplete form-control" id="field-select" placeholder="Type to search for field">
                                </div>
                            </div>
                            <br/>
                            <div class='row'>
                                <div class="col-sm-5"><label>To destination</label></div>
                            </div>
                            <div class='row' style='margin-top:20px'>
                                <div class='col-sm-6 ui-front dest-event-wrapper' style='z-index: 0'>
                                    <input data-is-longitudinal="yes" class='dest-events-autocomplete form-control' id="dest-event-select" placeholder="Type to search for event">
                                </div>
                                <div class='col-sm-6 ui-front' style='z-index: 0'>
                                    <input class='dest-fields-autocomplete form-control' id="dest-field-select" placeholder="Type to search for field">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary close-modal-btn" data-dismiss="modal">Close</button>
                            <button type="button" id="add-field-btn" class="btn btn-primary">Add</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="add-instr-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <input class="table-id" type="hidden">
                        <div class="modal-header">
                            <h5>Add Instrument</h5>
                            <button type="button" class="close close-modal-btn" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class='row'>
                                <div class="col-sm-12"><label>All fields of the chosen instrument must exist in the destination project. Move instrument data</label></div>
                            </div>
                            <div class="row">
                                <?php if (REDCap::isLongitudinal()): ?>
                                <div class='col-sm-6 ui-front'>
                                    <input class='source-events-autocomplete form-control' id="instr-event-select" placeholder="Type to search for event">
                                </div>
                                <?php endif;?>
                                <div class='col-sm-6 ui-front'>
                                    <input class='source-instr-autocomplete form-control' id="instr-select" placeholder="Type to search for instrument">
                                </div>
                            </div>
                            <br/>
                            <div class='row' id="add-instr-label-event-div">
                                <div class="col-sm-12"><label>To event</label></div>
                            </div>
                            <div class='row' style='margin-top:20px'>
                                <div class='col-sm-6 ui-front dest-event-wrapper' style='z-index: 0'>
                                    <input data-is-longitudinal="yes" class='dest-events-autocomplete form-control' id="dest-event-instrument" placeholder="Type to search for event">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary close-modal-btn" data-dismiss="modal">Close</button>
                            <button type="button" id="add-instr-btn" class="btn btn-primary">Add</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="upload-json-modal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5>Import DET Settings</h5>
                            <button type="button" class="close close-modal-btn" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form id="upload-json-form" method="post" action="<?php print $module->getUrl("index.php");?>">
                            <div class="modal-body">
                                <div class='row'>
                                    <div class="col-sm-12"><label>Please copy-paste your json into the editor below</label></div>
                                    <div id="json-errors-div" class="col-sm-12" style="color:red"></div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12"><textarea id="upload-textarea" rows="4" name="json" style="width:100%" required></textarea></div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary close-modal-btn" data-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary">Import</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="export-json-modal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <input class="table-id" type="hidden">
                        <div class="modal-header">
                            <h5>Copy DET Settings</h5>
                            <button type="button" class="close close-modal-btn" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class='row'>
                                <div class="col-sm-12"><label>Please copy your json below</label></div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div style="background-color:lightgrey; border: 1px solid black; color:deeppink; padding: 5px">
                                        <?php print htmlspecialchars(json_encode($settings, JSON_PRETTY_PRINT), ENT_QUOTES); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary close-modal-btn" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
<?php
require_once "script.php";
/**
 * Display REDCap footer.
 */
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';