<?php

require_once "DataEntryTriggerBuilder.php";

/**
 * Display REDCap header.
 */
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

$data_entry_trigger_builder = new BCCHR\DataEntryTriggerBuilder\DataEntryTriggerBuilder();
$settings = json_decode($data_entry_trigger_builder->getProjectSetting("det_settings"), true);
$dest_fields = $data_entry_trigger_builder->retrieveProjectMetadata($settings["dest-project"]);
?>
<html>
    <head>
        <!-- boostrap-select css and js-->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.9/dist/css/bootstrap-select.min.css">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.9/dist/js/bootstrap-select.min.js"></script>
        <style>
            p {
                font-size: 18px;
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
        </style>
        <script src="<?php print $module->getUrl("functions.js");?>" type="text/javascript"></script>
    </head>
    <body>
        <div class="container">
            <h1>Data Entry Trigger Builder</h1>
            <p>
                This module allows users to customize data entry transfers (DETs) between two projects in REDCap.
                A DET runs every time data is created/saved in a record via data entry or surveys. To disable your DET after creation,
                disable the module. Your settings will be saved, and automatically applied, when the module is enabled again.
                If your requirements are more complicated than what's allowed here, please submit a ticket to the <b>BCCHR REDCap</b> team.
            </p>
            <form class="jumbotron" method="post" action="<?php print $module->getUrl("index.php");?>">
                <h4>Select a linked Project</h4>
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
                <hr>
                <div id="main-form" <?php if (empty($settings)) :?> style="display:none" <?php endif;?>>
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
                        <div class='col-sm-6'>
                            <div class='class-sm-12'><label>Link source project field</label></div>
                            <div class='row'>
                                <?php if (REDCap::isLongitudinal()): ?>
                                    <div class='col-sm-6'>
                                        <input class="source-events-autocomplete form-control" name='linkSourceEvent' placeholder="Type to search for event" value="<?php print $settings["linkSourceEvent"]; ?>">
                                    </div>
                                <?php endif;?>
                                <div class='col-sm-6'>
                                    <input class="source-fields-autocomplete form-control" name='linkSource' placeholder="Type to search for field" value="<?php print $settings["linkSource"]; ?>">
                                </div> 
                            </div>
                        </div>
                        <div class='col-sm-6'>
                            <div class='class-sm-12' id="link-source-text"><label>To linked project field</label></div>
                            <div class='row'>
                                <div class='col-sm-6 dest-event-wrapper' <?php if(empty($settings["linkDestEvent"])) {print "style='display:none'";} ?>>
                                    <input class='dest-events-autocomplete form-control' name='linkDestEvent' placeholder="Type to search for event" value="<?php print $settings["linkDestEvent"]; ?>">
                                </div>
                                <div id="link-source-wrapper" class='col-sm-6'>
                                    <input class='dest-fields-autocomplete form-control' name='linkDest' placeholder="Type to search for field" value="<?php print $settings["linkDest"]; ?>">
                                </div>
                            </div>
                        </div>

                    </div>
                    <hr>
                    <h4>Trigger conditions (Max. 10)</h4>
                    <div id="trigger-instr" style="margin-bottom:20px">
                        <label>Push data from the source project to the linked project, when the following conditions are met:</label>
                        <ul>
                            <li>E.g., [event_name][instrument_name_complete] = "2"</li>
                            <li>E.g., [event_name][variable_name] = "1"</li>
                        </ul>
                        <p>Where [event_name] = only in longitudinal projects<br/>Where [instrument_name] = form copied from source to linked project</p>
                        <button type="button" class="btn btn-primary btn-sm add-trigger-btn" onclick="addTrigger()">Add Trigger</button>
                    </div>
                    <?php if (!empty($settings)): foreach($settings["triggers"] as $index => $trigger): ?>
                    <div class="form-group trigger-and-data-wrapper">
                        <div class="det-trigger">
                            <div class="row">
                                <div class="col-sm-2">
                                    <label><h5>Trigger #<?php print $index + 1?></h5></label>
                                </div>
                                <div class="col-sm-9"></div>
                                <div class="col-sm-1" style="text-align: center;">
                                    <span class="fa fa-minus delete-trigger-btn"></span>
                                </div>
                            </div>
                            <input name="triggers[]" type="text" class="form-control det-trigger-input" value="<?php print $trigger; ?>" required>
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
                                    print "['" . $source . "]";
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
                                    <input id="field-value" class='form-control' value='<?php print $setDestFieldsValues[$i]; ?>' placeholder="Type the value to tranfer">
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
                            <button type="button" id="add-field-btn" class="btn btn-primary" onclick="updateTable(this)">Add</button>
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
                                <?php if ($dest_fields["isLongitudinal"]): ?>
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
                            <button type="button" id="add-instr-btn" class="btn btn-primary" onclick="updateTable(this)">Add</button>
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