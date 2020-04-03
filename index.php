<?php

require_once "DataEntryTriggerBuilder.php";

/**
 * Display REDCap header.
 */
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

$data_entry_trigger_builder = new BCCHR\DataEntryTriggerBuilder\DataEntryTriggerBuilder();
$settings = json_decode($data_entry_trigger_builder->getProjectSetting("det_settings"), true);
$metadata = $data_entry_trigger_builder->retrieveProjectMetadata($module->getProjectId());
$instrument_names = REDCap::getInstrumentNames();
$dest_fields = $data_entry_trigger_builder->retrieveProjectMetadata($settings["dest-project"]);
?>
<html>
    <head>
        <style>
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
            <p style="font-size:18px; max-width: 100%">
                This module allows the user to customize data entry transfers between two projects in REDCap. If your requirements are more complicated than what's allowed here, please contact
                the <b>BCCHR REDCap</b> team.
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
                        <div class='col-sm-2'><p>Link source project field</p></div> 
                        <?php if (REDCap::isLongitudinal()): ?>
                            <div class='col-sm-2'>
                                <input class="source-events-autocomplete form-control" name='linkSourceEvent' placeholder="Type to search for event" value="<?php print $settings["linkSourceEvent"]; ?>">
                            </div>
                        <?php endif;?>
                        <div class='col-sm-2'>
                            <input class="source-fields-autocomplete form-control" name='linkSource' placeholder="Type to search for field" value="<?php print $settings["linkSource"]; ?>">
                        </div> 
                        <div class='col-sm-2' id="link-source-text"><p>to linked project field</p></div>
                        <div class='col-sm-2 dest-event-wrapper' <?php if(empty($settings["linkDestEvent"])) {print "style='display:none'";} ?>>
                            <input class='dest-events-autocomplete form-control' name='linkDestEvent' placeholder="Type to search for event" value="<?php print $settings["linkDestEvent"]; ?>">
                        </div>
                        <div id="link-source-wrapper" class='col-sm-2'>
                            <input class='dest-fields-autocomplete form-control' name='linkDest' placeholder="Type to search for field" value="<?php print $settings["linkDest"]; ?>">
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
                                    <label><h5>Trigger #<?php print $index?></h5></label>
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
                                    <input class='source-instr-autocomplete form-control' id="instr-select" placeholder="Type to search for field">
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
<script>
    /** 
     * Code to populate the populate
     * the autocomplete fields for the 
     * source project
     */

    <?php if (REDCap::isLongitudinal()): ?>
        var sourceEvents = [
            <?php
            foreach ($metadata["events"] as $event)
            {
                print "'$event',";
            }
            ?>
        ]
        
        $(".source-events-autocomplete" ).autocomplete({
            source: sourceEvents
        });
    <?php endif;?>
                
    var sourceFields = [
        <?php
        foreach ($metadata["fields"] as $field)
        {
            print "'$field',";
        }
        ?>
    ]
    $(".source-fields-autocomplete" ).autocomplete({source: sourceFields});

    var sourceInstr = [
        <?php
        foreach ($instrument_names as $unique_name => $label)
        {
            print "'$unique_name',";
        }
        ?>
    ]
    $(".source-instr-autocomplete").autocomplete({source: sourceInstr});

    /**
     * When user goes to add a field or instrument
     * update .table-id item, hidden in each modal,
     * to tell them which trigger's table to update.
     * 
     */
    $("body").on("click", ".add-field-btn, .add-instr-btn", function () {
        var id = $(this).siblings("table").attr("id");
        $(".table-id").val(id);
    });

    $("body").on("click", ".fa-pencil-alt", function () {
        var id = $(this).parents("table").attr("id");
        $(".table-id").val(id);
    });

    /**
        Code to delete a trigger
     */
    $('body').on('click', '.delete-trigger-btn', function () {
        $(this).closest('.trigger-and-data-wrapper').remove();
    });

    /**
        Code to delete a trigger's field/instrument
     */
    $("body").on("click", ".delete-trigger-field", function () {
        $(this).closest('.trigger-field-row').remove();
    });

    /**
     * Ajax calls to retrieve destination project's fields and instruments
     */

    /**
        Call to retrieve destination project's fields and instruments when page loads.
        Only relevent when DET aleady exists.
    */
    $(document).ready(function() {
        if ($("#destination-project-select").val() != "")
        {
            $.ajax({
                url: "<?php print $module->getUrl("getDestinationFields.php") ?>",
                type: "POST",
                data: {
                    pid: $("#destination-project-select").val()
                },
                success: function (data) {
                    var metadata = JSON.parse(data);
                    var destFields = metadata.fields;
                    var destEvents = metadata.events;
                    var isLongitudinal = metadata.isLongitudinal;

                    if (isLongitudinal) {
                        $(".dest-events-autocomplete").autocomplete({source: destEvents});
                        $(".dest-event-wrapper").show();
                    }
                    else {
                        $(".dest-events-autocomplete").val("");
                        $(".dest-event-wrapper").hide();
                    }

                    $(".dest-fields-autocomplete").autocomplete({source: destFields});
                },
                error: function (data, status, error) {
                    console.log("Returned with status " + status + " - " + error);
                }
            });
        }
    });

    /**
        Call to retrieve destination project's fields and instruments when 
        destination project changes.
    */
    $("#destination-project-select").change(function () {
        // Reset form by removing all triggers.
        $('.trigger-and-data-wrapper').remove();

        fieldOptions = [];
        instrOptions = [];
        
        $.ajax({
            url: "<?php print $module->getUrl("getDestinationFields.php") ?>",
            type: "POST",
            data: {
                pid: $(this).val()
            },
            success: function (data) {
                var metadata = JSON.parse(data);
                var destFields = metadata.fields;
                var destEvents = metadata.events;
                var isLongitudinal = metadata.isLongitudinal;

                if (isLongitudinal) {
                    $(".dest-events-autocomplete").autocomplete({source: destEvents});
                    $(".dest-event-wrapper").show();
                }
                else {
                    $(".dest-events-autocomplete").val("");
                    $(".dest-event-wrapper").hide();
                }

                $(".dest-fields-autocomplete").val("");
                $(".dest-fields-autocomplete").autocomplete({source: destFields});
            },
            error: function (data, status, error) {
                console.log("Returned with status " + status + " - " + error);
            }
        });

        // Show the main form are if it's not already visible
        $('#main-form').show();
    });

    /**
     * Ajax call to submit form, and validate
     */
    $("form").submit(function (event) {
        event.preventDefault();
        $.ajax({
            url: "<?php print $module->getUrl("SubmitForm.php") ?>",
            type: "POST",
            data: $("form").serialize(),
            success: function (data) {
                var errors = JSON.parse(data);
                var triggers = $('.det-trigger');

                $("#create-record-input").siblings('.error').remove();
                triggers.siblings('.error').remove();
                $("#create-record-input").removeAttr("style");
                triggers.find("input").removeAttr("style");

                if (errors.success != true)
                {
                    if (errors.create_subject_errors)
                    {
                        var msg = "<b>ERROR! Syntax errors exist in the logic:</b><br>"
                        errors.create_subject_errors.forEach(function(item) {
                            msg += "&bull; " + item + "<br/>";
                        })
                        $("#create-record-input").attr("style", "border: 2px solid red")
                        $("#create-record-input").after("<p class='error'><i style='color:red'>" + msg + "</i></p>")
                    }
                    
                    if (errors.trigger_errors)
                    {
                        for (var index in errors.trigger_errors)
                        {
                            var item = errors.trigger_errors[index];
                            var msg = "<b>ERROR! Syntax errors exist in the logic:</b><br>";
                            item.forEach(function(m) {
                                msg += "&bull; " + m + "<br/>";
                            });
                            $(triggers[index]).find("input").attr("style", "border: 2px solid red")
                            $(triggers[index]).after("<p class='error'><i style='color:red'>" + msg + "</i></p>")
                        }
                    }
                }
                else
                {
                    alert("Your DET has successfully been created");
                }
            },
            error: function (data, status, error) {
                console.log("Returned with status " + status + " - " + error);
            }
        });
    })

    /**
     * Clear add field modal items, whenever
     * user switches back and forth between
     * entering a custom value or selecting a field
     * for the source.
     */
    $('.fa-exchange-alt').click(function () {
        $('#source-select').toggle();
        $('#field-value').val("");
        $('#source-input').toggle();
        $('#field-select').val("");
    });

    /**
     * When a user closes a modal, clear the
     * form. 
     */
    $('.close-modal-btn').click(function() {
        clearInstrForm();
        clearFieldForm();
        $('#add-field-btn').text("Add");
        $('#add-instr-btn').text("Add");
    })
</script>
<?php

/**
 * Display REDCap footer.
 */
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';