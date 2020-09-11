<?php

require_once "DataEntryTriggerBuilder.php";

/**
 * Display REDCap header.
 */
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

$data_entry_trigger_builder = new BCCHR\DataEntryTriggerBuilder\DataEntryTriggerBuilder();
if (!empty($_POST["json"])) {
    $settings = json_decode($_POST["json"], true);
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
        </style>
        <script src="<?php print $module->getUrl("functions.js");?>" type="text/javascript"></script>
    </head>
    <body>
        <?php if ($Proj->project['status'] > 0 || !empty($settings)): ?> 
            <div style="position: sticky; top: 0; width: 100%; background-color:#ff9800; padding:5px; text-align:center">
                <?php if ($Proj->project['status'] > 0): ?><h6><b>This project is currently in production, be careful with your changes!</b></h6><?php endif; ?>
                <?php if (!empty($settings)): ?><h6><b>WARNING: Any changes made to the REDCap project, after the DET has been created, has the potential to break it. After you’ve updated your project, please make sure to update the DET in accordance with your changes.</b></h6><?php endif; ?>
                <?php 
                    // if (!empty($settings["dest-project"])) { 
                    //     $DestProj = new Project($settings["dest-project"]); 
                    //     if ($DestProj->project['status'] > 0) {
                    //         print "<h5><b>The destination project is currently in production.</h5><b>";
                    //     }
                    // }
                ?>
            </div>
        <?php endif; ?>
        <div class="container jumbotron">
            <h2>Data Entry Trigger Builder</h2>
            <p>*This module will work will classical and longitudinal projects, but is currently incompatible with repeatable events, and multiple arms.</p>
            <?php if (!empty($settings)): ?>
            <p><b>DET was last changed on <span class="saved"><?php print $data_entry_trigger_builder->getProjectSetting("saved_timestamp");?></span> by <span class="saved"><?php print $data_entry_trigger_builder->getProjectSetting("saved_by");?></span></b></p>
            <?php endif; ?>
            <hr/>
            <h5>Import/Export Your DET Settings</h5>
            <p>
                If you've created a JSON string containing your DET settings, you may import them into the module, or you may export your current DET settings (If they exist).
                 When importing settings for projects on a different REDCap instance that have the same structure, change the destination project id before import. 
            </p>
            <p><b>IMPORTANT: Once you've imported your DET settings, you must still save them by clicking "Save DET" at the bottom of the page.</b></p>
            <button type="button" data-toggle="modal" data-target="#upload-json-modal" class="btn btn-primary btn-sm">Import DET Settings</button>
            <?php if (!empty($settings)): ?><button type="button" data-toggle="modal" data-target="#export-json-modal" class="btn btn-primary btn-sm">Export DET Settings</button><?php endif; ?>
            <?php if (!empty($import_err_msg)): ?>
            <p style="color:red"><b><?php print $import_err_msg; ?></b></p>
            <?php endif;?>
            <hr/>
            <?php if (!empty($settings)): ?>
            <h5>Download Release Notes</h5>
            <form id="download-form" action="<?php print $module->getUrl("downloadReleaseNotes.php");?>" method="post">
                <button id="download-release-notes-btn" type="submit" class="btn btn-primary" style="margin-top:20px">Download Release Notes</button>
            </form>
            <hr/>
            <?php endif;?>
            <form id="det-form" method="post">
                <h5>Select a Linked Project</h5>
                <div class="form-group">
                    <select name="dest-project" id="destination-project-select" class="form-control selectpicker" data-live-search="true" required>
                        <option value="" disabled <?php if (empty($settings)) { print "selected"; }?>>Select a project</option>
                        <?php
                            $projects = $data_entry_trigger_builder->getProjects();
                            foreach($projects as $project)
                            {
                                if ($project["project_id"] != $_GET["pid"]) {
                                    if (!empty($settings["dest-project"]) && $project["project_id"] == $settings["dest-project"])
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
                    if (!empty($settings["dest-project"])) { 
                        $DestProj = new Project($settings["dest-project"]); 
                        if ($DestProj->project['status'] > 0) {
                            print "<p><b><i>This project is currently in production.</i></p></b>";
                        }
                    }
                    ?>
                </div>
                <hr>
                <div id="main-form" <?php if (empty($settings)) :?> style="display:none" <?php endif;?>>
                    <h5>Record Linkage</h5>
                    <p>
                        Create subjects/push data to linked project using variables in source and linked project. 
                        When at least one of the triggers are met, then records between the source and linked project will be linked via the chosen fields.
                    </p>
                    <div class='row link-field form-group'> 
                        <div class='col-sm-12' style="margin-bottom:10px">
                            <div class='class-sm-12'><label>Link source project field</label></div>
                            <div class='row'>
                                <?php if (REDCap::isLongitudinal()): ?>
                                    <div class='col-sm-6'>
                                        <input id='linkSourceEvent' class="source-events-autocomplete form-control" name='linkSourceEvent' placeholder="Type to search for event" value="<?php print $settings["linkSourceEvent"]; ?>" required>
                                    </div>
                                <?php endif;?>
                                <div class='col-sm-6'>
                                    <input id='linkSource' class="source-fields-autocomplete form-control" name='linkSource' placeholder="Type to search for field" value="<?php print $settings["linkSource"]; ?>" required>
                                </div> 
                            </div>
                        </div>
                        <div class='col-sm-12'>
                            <div class='class-sm-12' id="link-source-text"><label>To linked project field</label></div>
                            <div class='row'>
                                <div class='col-sm-6 dest-event-wrapper' <?php if(empty($settings["linkDestEvent"])) {print "style='display:none'";} ?>>
                                    <input id='linkDestEvent' class='dest-events-autocomplete form-control' name='linkDestEvent' placeholder="Type to search for event" value="<?php print $settings["linkDestEvent"]; ?>" required>
                                </div>
                                <div id="link-source-wrapper" class='col-sm-6'>
                                    <input id='linkDest' class='dest-fields-autocomplete form-control' name='linkDest' placeholder="Type to search for field" value="<?php print $settings["linkDest"]; ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <h5>Triggers (Max. 10)</h5>
                    <div id="trigger-instr" style="margin-bottom:20px">
                        <label>Push data from the source project to the linked project, when the following conditions are met:</label>
                        <ul>
                            <li>E.g., [event_name][instrument_name_complete] = "2"</li>
                            <li>E.g., [event_name][variable_name] = "1"</li>
                        </ul>
                        <p>Where [event_name] = only in longitudinal projects<br/>Where [instrument_name] = form copied from source to linked project</p>
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
                    <?php if (!empty($settings)): foreach($settings["triggers"] as $index => $trigger): ?>
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
                            <textarea rows="1" name="triggers[]" class="form-control det-trigger-input" required><?php print str_replace("\"", "'", $trigger); ?></textarea>
                        </div>
                        <p>
                            Copy the following instruments/fields from source project to linked project when the above condition is true: 
                        </p>
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
                                $pipingSourceEvents = $settings["pipingSourceEvents"][$index];
                                $pipingDestEvents = $settings["pipingDestEvents"][$index];
                                $pipingSourceFields = $settings["pipingSourceFields"][$index];
                                $pipingDestFields = $settings["pipingDestFields"][$index];
                                foreach($pipingSourceFields as $i => $source)
                                {
                                    print "<tr class='trigger-field-row'><td>";
                                    if (!empty($pipingSourceEvents[$i]))
                                    {
                                        print "[" . $pipingSourceEvents[$i] . "]";
                                        print "<input class='pipingSourceEvents' type='hidden' name='pipingSourceEvents[$index][]' value='" . $pipingSourceEvents[$i] . "'>";
                                    }
                                    print "[" . $source . "]";
                                    print "<input class='pipingSourceFields' type='hidden' name='pipingSourceFields[$index][]' value='" . $source . "'></td><td>";
                                    if (!empty($pipingDestEvents[$i]))
                                    {
                                        print "[" . $pipingDestEvents[$i] . "]";
                                        print "<input class='pipingDestEvents' type='hidden' name='pipingDestEvents[$index][]' value='" . $pipingDestEvents[$i] . "'>";
                                    }
                                    print "[" . $pipingDestFields[$i] . "]";
                                    print "<input class='pipingDestFields' type='hidden' name='pipingDestFields[$index][]' value='" . $pipingDestFields[$i] . "'>";
                                    print "</td><td><span class='fa fa-pencil-alt' onclick='fillPipingFieldForm(this)'></span></td>";
                                    print "<td><span class='fa fa-trash-alt delete-trigger-field'></span></td>";
                                    print "</tr>";
                                }

                                $setDestEvents = $settings["setDestEvents"][$index];
                                $setDestFields = $settings["setDestFields"][$index];
                                $setDestFieldsValues = $settings["setDestFieldsValues"][$index];
                                foreach($setDestFields as $i => $source)
                                {
                                    print "<tr class='trigger-field-row'><td>";
                                    if (!empty($setDestFieldsValues[$i]))
                                    {
                                        print "'" . $setDestFieldsValues[$i] . "'";
                                        print "<input class='setDestFieldsValues' type='hidden' name='setDestFieldsValues[$index][]' value='" . $setDestFieldsValues[$i] . "'></td><td>";
                                    }
                                    if (!empty($setDestEvents[$i]))
                                    {
                                        print "[" . $setDestEvents[$i] . "]";
                                        print "<input class='setDestEvents' type='hidden' name='setDestEvents[$index][]' value='" . $setDestEvents[$i] . "'>";
                                    }
                                    print "[" . $source . "]";
                                    print "<input class='setDestFields' type='hidden' name='setDestFields[$index][]' value='" . $source . "'>";
                                    print "</td><td><span class='fa fa-pencil-alt' onclick='fillFieldForm(this)'></span></td>";
                                    print "<td><span class='fa fa-trash-alt delete-trigger-field'></span></td>";
                                    print "</tr>";
                                }

                                $sourceInstr = $settings["sourceInstr"][$index];
                                $sourceInstrEvents = $settings["sourceInstrEvents"][$index];
                                foreach($sourceInstr as $i => $source)
                                {
                                    print "<tr class='trigger-field-row'><td>";
                                    if (!empty($sourceInstrEvents[$i]))
                                    {
                                        print "[" . $sourceInstrEvents[$i] . "]";
                                        print "<input class='sourceInstrEvents' type='hidden' name='sourceInstrEvents[$index][]' value='" . $sourceInstrEvents[$i] . "'>";
                                    }
                                    print "[" . $source . "]";
                                    print "<input class='sourceInstr' type='hidden' name='sourceInstr[$index][]' value='" . $source . "'></td><td>";
                                    if (!empty($sourceInstrEvents[$i]))
                                    {
                                        print "[" . $sourceInstrEvents[$i] . "]";
                                    }
                                    print "[" . $source . "]";
                                    print "</td><td><span class='fa fa-pencil-alt' onclick='fillInstrForm(this)'></span></td>";
                                    print "<td><span class='fa fa-trash-alt delete-trigger-field'></span></td>";
                                    print "</tr>";
                                }
                            ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endforeach; endif;?>
                    <hr>
                    <h5>Confirm the following</h5>
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
                                <div class='col-sm-6 ui-front dest-event-wrapper'>
                                    <input class='dest-events-autocomplete form-control' id="dest-event-select" placeholder="Type to search for event">
                                </div>
                                <div class='col-sm-6 ui-front'>
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
                                <div class="col-sm-12"><label>There must be a one-to-one relationship in the linked project</label></div>
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
                                        <?php print json_encode($settings, JSON_PRETTY_PRINT); ?>
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