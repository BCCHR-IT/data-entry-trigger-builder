<?php

require_once "DataEntryTriggerBuilder.php";

/**
 * Display REDCap header.
 */
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

$data_entry_trigger_builder = new BCCHR\DataEntryTriggerBuilder\DataEntryTriggerBuilder();
$settings = json_decode($data_entry_trigger_builder->getProjectSetting("det_settings"), true);
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
            <p style="font-size:18px; max-width: 100%">
                This module allows the user to customize data entry transfers between two projects in REDCap. If your requirements are more complicated than what's allowed here, please contact
                the <b>BCCHR REDCap</b> team.
            </p>
            <?php if (empty($settings)) { $data_entry_trigger_builder->newForm(); } else { $data_entry_trigger_builder->existingForm($settings); } ?>
        </div>
    </body>
</html>
<?php

require_once "script.php";

/**
 * Display REDCap footer.
 */
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';