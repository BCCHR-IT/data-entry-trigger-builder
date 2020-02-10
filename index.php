<?php

require_once "DataEntryTriggerBuilder.php";

/**
 * Display REDCap header.
 */
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
?>
<html>
    <body>
        <div class="container">
        <h1>Data Entry Trigger Builder</h1>
        <form class="jumbotron">
            <div class="form-group">
                <label>Destination Project PID</label>
                <select id="destination-project-select" class="form-control">
                    <option>Project</option>
                </select>
            </div>
            <div class="form-group">
                <label>Condition to Create Record</label>
                <input name="create-record-cond" type="text" class="form-control" >
            </div>
            <div class="form-group">
                <label>New Record ID (Leave blank for auto generated record id)</label>
                <input name="create-record-cond" type="text" class="form-control">
            </div>
            <div class="form-check">
                <div class="row"><label>Overwrite data in destination project every time data is saved</label></div>
                <input type="radio" name="overwrite-data" class="form-check-input" value="1"><label class="form-check-label">Yes</label>
                <br>
                <input type="radio" name="overwrite-data" class="form-check-input" value="0"><label class="form-check-label">No</label>
            </div>
            <br/>
            <div class="form-group det-trigger">
                <div class="row">
                    <div class="col-sm-2">
                        <label>Trigger</label>
                    </div>
                    <div class="col-sm-9"></div>
                    <div class="col-sm-1">
                        <span class="fa fa-plus add-trigger-btn" style="background-color:white"></span>
                    </div>
                </div>
                <input name="create-record-cond" type="text" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary" disabled>Create DET</button>
        </form>
        </div>
    </body>
</html>
<script>
    $(".add-trigger-btn").click(function () {
        var triggers = $(".det-trigger");
        if (triggers.length < 10)
        {
            triggers.last().after(
                "<div class='det-trigger'>Trigger HTML goes here <span class='fa fa-minus delete-trigger-btn' style='background-color:white'></span></div>"
            );
        } 
    });

    $(".delete-trigger-btn").click(function () {
        console.log($(this).parent());
        $(this).parent().remove();
    });
</script>
<?php

/**
 * Display REDCap footer.
 */
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';