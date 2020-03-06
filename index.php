<?php

require_once "DataEntryTriggerBuilder.php";

/**
 * Display REDCap header.
 */
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

$data_entry_trigger_builder = new BCCHR\DataEntryTriggerBuilder\DataEntryTriggerBuilder();
?>
<html>
    <head>
        <!--boostrap-select required files-->
        <!-- Latest compiled and minified CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.9/dist/css/bootstrap-select.min.css">
        <!-- Latest compiled and minified JavaScript -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.9/dist/js/bootstrap-select.min.js"></script>
        <style>
            .fa-plus:hover, .fa-minus:hover {
                color:grey;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Data Entry Trigger Builder</h1>
            <p style="font-size:14px">
                This module allows the user to customize data entry transfers between two projects in REDCap. If your requirements are more complicated than what's allowed here, please contact
                the <b>BCCHR REDcap</b> team.
            </p>
            <form class="jumbotron">
                <div class="form-group">
                    <label>Linked Project</label>
                    <select name="dest-project" id="destination-project-select" class="form-control selectpicker" data-live-search="true" required>
                        <option value="" disabled selected>Select a project</option>
                        <?php
                            $projects = $data_entry_trigger_builder->getProjects();
                            foreach($projects as $project)
                            {
                                if ($project["project_id"] != $_GET["pid"]) {
                                    print "<option value='". $project["project_id"] . "'>" . $project["app_title"] . "</option>";
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
                    <input id="create-record-input" name="create-record-cond" type="text" class="form-control" required>
                </div>
                <div class='row link-field form-group'> 
                    <div class='col-sm-2'><p>Link source project field</p></div> 
                    <div class='col-sm-3'> 
                        <select name='linkSource' class='form-control selectpicker' data-live-search='true' required> 
                        <option value='' disabled selected>Select a field</option>  
                        <?php
                            $fields = REDCap::getFieldNames();
                            foreach($fields as $field)
                            {
                                print "<option value='$field'>$field</option>";
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
                        <input name="triggers[]" type="text" class="form-control det-trigger-input">
                    </div>
                    <p>Copy the following instruments/fields from source project to linked project when the above condition is true:</p>
                    <div class="row" style="margin-top:20px">
                        <div class="col-sm-2"><button type="button" class="btn btn-primary add-instr-btn">+ Instrument</button></div>
                        <div class="col-sm-2"><button type="button" class="btn btn-primary add-field-btn">+ Field</button></div>
                    </div>
                </div>
                <h4>Additional Settings</h4>
                <div class="row">
                    <div class="form-check col" style="margin-left:15px">
                        <div class="row"><label>Overwrite data in destination project every time data is saved</label></div>
                        <input type="radio" name="overwrite-data" class="form-check-input" value="1"><label class="form-check-label">Yes</label>
                        <br>
                        <input type="radio" name="overwrite-data" class="form-check-input" value="0"><label class="form-check-label">No</label>
                    </div>
                    <div class="form-check col">
                        <div class="row"><label>Use DAGs (Will only push DAGs one-to-one)</label></div>
                        <input type="radio" name="overwrite-data" class="form-check-input" value="1"><label class="form-check-label">Yes</label>
                        <br>
                        <input type="radio" name="overwrite-data" class="form-check-input" value="0"><label class="form-check-label">No</label>
                    </div>
                </div>
                <button id="create-det-btn" type="button" class="btn btn-primary" style="margin-top:20px">Create DET</button>
            </form>
        </div>
    </body>
</html>
<?php

require_once "script.php";

/**
 * Display REDCap footer.
 */
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';