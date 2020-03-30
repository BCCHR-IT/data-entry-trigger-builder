<script>
    /**
        The field and instrument options for the current destination project
     */
    var fieldOptions = [];
    var instrOptions = [];
    var eventOptions = [];
    var isLongitudinal = false;

    /**
        Code to add another trigger
     */
    $("body").on('click', '.add-trigger-btn', function () {
        var triggers = $(".trigger-and-data-wrapper");
        
        var html = "<div class='form-group trigger-and-data-wrapper new-wrapper'>" +
                    "<div class='det-trigger'>" +
                        "<div class='row'>" + 
                            "<div class='col-sm-2'>" +
                                "<label>Condition:</label>" +
                            "</div>" +
                            "<div class='col-sm-9'></div>" +
                            "<div class='col-sm-1' style='text-align: center;'>" +
                                "<span class='fa fa-minus delete-trigger-btn'></span>" +
                            "</div>" +
                        "</div>" +
                        "<input name='triggers[]' type='text' class='form-control det-trigger-input' required>" +
                    "</div>" +
                    "<p>Copy the following instruments/fields from source project to linked project when the above condition is true:</p>" +
                    "<div class='row' style='margin-top:20px'>" +
                        "<div class='col-sm-2'><button type='button' class='btn btn-link add-instr-btn'>Pipe Instrument</button></div>" +
                        "<div class='col-sm-2'><button type='button' class='btn btn-link add-field-btn'>Pipe Field</button></div>" +
                        "<div class='col-sm-2'><button type='button' class='btn btn-link set-field-btn'>Set Field</button></div>" +
                    "</div>" + 
                "</div>";
        
        if (triggers.length == 0)
        {
            $("#trigger-instr").after(html);
        }
        else if (triggers.length < 10)
        {
            triggers.last().after(html);
        }
        else
        {
            alert("You have reached the maximum number of allowed triggers (10)")
        }
    });

    /**
        Code to add an instrument to a trigger
     */
    $('body').on('click', '.add-instr-btn', function () {
        var instruments = $(this).closest('.trigger-and-data-wrapper').find('.det-instrument');
        if (instruments.length < 10)
        {
            var options = "";
            instrOptions.forEach(function(item) {
                options += "<option " + "value='" + item + "'>" + item + "</option>";
            });
            
            var index = $(".trigger-and-data-wrapper").index($(this).closest('.trigger-and-data-wrapper'));

            var elem = $(
                "<div class='row det-instrument' style='margin-top:20px;'>" +
                    "<div class='col-sm-7'><p>Copy instrument (must have a one-to-one relationship in the destination project)</p></div>" +
                    <?php if (REDCap::isLongitudinal()): ?>
                    "<div class='col-sm-2'>" +
                        "<select name='sourceInstrEvents[" + index + "][]' class='form-control selectpicker select-source-instr' data-live-search='true' required>" +
                        "<option value='' disabled selected>Select event</option>" + 
                        <?php
                            $events = REDCap::getEventNames(true, true);
                            foreach ($events as $event_name) 
                            {
                                print "\"<option value='$event_name'>$event_name</option>\" +";
                            }
                        ?>
                        "</select>" +
                    "</div>" +
                    <?php endif;?>
                    "<div class='col-sm-2'>" +
                        "<select name='sourceInstr[" + index + "][]' class='form-control selectpicker select-source-instr' data-live-search='true' required>" +
                        "<option value='' disabled selected>Select instrument</option>" + 
                        <?php
                            $instruments = REDCap::getInstrumentNames();
                            foreach($instruments as $unique_name => $label)
                            {
                                print "\"<option value='$unique_name'>$unique_name</option>\" +";
                            }
                        ?>
                        "</select>" +
                    "</div>" +
                    "<div class='col-sm-1' style='text-align: center; padding-top: 1%; padding-bottom: 1%;'>" +
                        "<span class='fa fa-minus delete-instr-btn'></span>" +
                    "</div>" +
                "</div>"
            );
            $(this).closest('.row').after(elem);
            elem.find('.selectpicker').selectpicker('render');
        }
        else
        {
            alert("You have reached the maximum number of allowed instruments for a trigger (10)")
        }
    });

    /**
        Code to add a field to a trigger
     */
    $('body').on('click', '.add-field-btn', function() {
        var fields = $(this).closest('.trigger-and-data-wrapper').find('.det-field');
        if (fields.length < 10)
        {
            var index = $(".trigger-and-data-wrapper").index($(this).closest('.trigger-and-data-wrapper'));

            var text = "<div class='row det-field' style='margin-top:20px'>" +
                    "<div class='col-sm-2'><p>Copy field</p></div>" +
                    <?php if (REDCap::isLongitudinal()): ?>
                    "<div class='col-sm-2'>" +
                        "<select name='pipingSourceEvents[" + index + "][]' class='form-control selectpicker select-source-instr' data-live-search='true' required>" +
                        "<option value='' disabled selected>Select event</option>" + 
                        <?php
                            $events = REDCap::getEventNames(true, true);
                            foreach ($events as $event_name) 
                            {
                                print "\"<option value='$event_name'>$event_name</option>\" +";
                            }
                        ?>
                        "</select>" +
                    "</div>" +
                    <?php endif;?>
                    "<div class='col-sm-2'>" +
                        "<select name='pipingSourceFields[" + index + "][]' class='form-control selectpicker' data-live-search='true' required>" +
                        "<option value='' disabled selected>Select field</option>" + 
                        <?php
                            $fields = REDCap::getFieldNames();
                            foreach($fields as $field)
                            {
                                print "\"<option value='$field'>$field</option>\" +";
                            }
                        ?>
                        "</select>" +
                    "</div>" +
                    "<div class='col-sm-1'><p>to</p></div>";
            
            if (isLongitudinal == true)
            {
                options = "";
                eventOptions.forEach(function(item) {
                    options += "<option " + "value='" + item + "'>" + item + "</option>";
                });

                text = text + 
                    "<div class='col-sm-2'>" +
                        "<select name='pipingDestEvents[" + index + "][]' class='form-control selectpicker select-dest-field' data-live-search='true' required>" +
                        "<option value='' disabled selected>Select event</option>" + 
                        options + 
                        "</select>" +
                    "</div>"
            }

            var options = "";
            fieldOptions.forEach(function(item) {
                options += "<option " + "value='" + item + "'>" + item + "</option>";
            });

            text = text + 
                "<div class='col-sm-2'>" +
                        "<select name='pipingDestFields[" + index + "][]' class='form-control selectpicker select-dest-field' data-live-search='true' required>" +
                        "<option value='' disabled selected>Select field</option>" + 
                        options + 
                        "</select>" +
                    "</div>" +
                    "<div class='col-sm-1' style='text-align: center; padding-top: 1%; padding-bottom: 1%;'>" +
                        "<span class='fa fa-minus delete-field-btn' style='margin-right: 5px'></span>" +
                    "</div>" +
                "</div>"

            var elem = $(text);
            $(this).closest('.row').after(elem);
            elem.find('.selectpicker').selectpicker('render');
        }
        else
        {
            alert("You have reached the maximum number of allowed fields for a trigger (10)")
        }
    });

    $('body').on('click', '.set-field-btn', function () {
        var fields = $(this).closest('.trigger-and-data-wrapper').find('.det-field');
        if (fields.length < 10)
        {
            var index = $(".trigger-and-data-wrapper").index($(this).closest('.trigger-and-data-wrapper'));

            var text = "<div class='row det-field' style='margin-top:20px'>" + "<div class='col-sm-2'><p>Set field</p></div>";

            if (isLongitudinal == true)
            {
                options = "";
                eventOptions.forEach(function(item) {
                    options += "<option " + "value='" + item + "'>" + item + "</option>";
                });

                text = text + 
                    "<div class='col-sm-2'>" +
                        "<select name='setDestEvents[" + index + "][]' class='form-control selectpicker select-dest-field' data-live-search='true' required>" +
                        "<option value='' disabled selected>Select event</option>" + 
                        options + 
                        "</select>" +
                    "</div>"
            }
            
            options = "";
            fieldOptions.forEach(function(item) {
                options += "<option " + "value='" + item + "'>" + item + "</option>";
            });

            text = text + 
                    "<div class='col-sm-2'>" +
                        "<select name='setDestFields[" + index + "][]' class='form-control selectpicker select-dest-field' data-live-search='true' required>" +
                        "<option value='' disabled selected>Select field</option>" + 
                        options + 
                        "</select>" +
                    "</div>" +
                    "<div class='col-sm-1'><p>to</p></div>" +
                    "<div class='col-sm-4'>" +
                        "<input name='setDestFieldsValues[" + index + "][]' class='form-control' required>" +
                    "</div>" +
                    "<div class='col-sm-1' style='text-align: center; padding-top: 1%; padding-bottom: 1%;'>" +
                        "<span class='fa fa-minus delete-field-btn' style='margin-right: 5px'></span>" +
                    "</div>" +
                "</div>"

            var elem = $(text);
            $(this).closest('.row').after(elem);
            elem.find('.selectpicker').selectpicker('render');
        }
        else
        {
            alert("You have reached the maximum number of allowed fields for a trigger (10)")
        }
    });

    /**
        Code to delete a trigger
     */
    $('body').on('click', '.delete-trigger-btn', function () {
        $(this).closest('.trigger-and-data-wrapper').remove();
    });

    /**
        Code to delete a trigger's instrument
     */
    $("body").on("click", ".delete-instr-btn", function () {
        $(this).closest('.det-instrument').remove();
    });

    /**
        Code to delete a trigger's field
     */
    $("body").on("click", ".delete-field-btn", function () {
        $(this).closest('.det-field').remove();
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
                    fieldOptions = metadata.fields;
                    instrOptions = metadata.instruments;
                    eventOptions = metadata.events;
                    isLongitudinal = metadata.isLongitudinal;
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
        fieldOptions = [];
        instrOptions = [];
        $('.new-wrapper, .det-field, .det-instrument').remove();
        $.ajax({
            url: "<?php print $module->getUrl("getDestinationFields.php") ?>",
            type: "POST",
            data: {
                pid: $(this).val()
            },
            success: function (data) {
                var metadata = JSON.parse(data);
                fieldOptions = metadata.fields;
                instrOptions = metadata.instruments;
                eventOptions = metadata.events;
                isLongitudinal = metadata.isLongitudinal;

                // Refreshes the link-dest-select right away.
                var options = "<option value='' disabled selected>Select field</option>";
                fieldOptions.forEach(function(item) {
                    options += "<option " + "value='" + item + "'>" + item + "</option>";
                });
                $("#link-dest-select").empty().append(options)
                $("#link-dest-select").selectpicker('refresh');

                if (isLongitudinal)
                {
                    options = "<option value='' disabled selected>Select event</option>";
                    eventOptions.forEach(function(item) {
                        options += "<option " + "value='" + item + "'>" + item + "</option>";
                    });
                    
                    var elem = $(
                        "<div id='link-event-wrapper' class='col-sm-2'>" +
                            "<select id='link-event-select' name='linkDestEvent' class='form-control selectpicker' data-live-search='true' required>" +
                                options +
                            "</select>" + 
                        "</div>"
                    );
                    $("#link-source-wrapper").before(elem);
                    elem.find('.selectpicker').selectpicker('render');
                }
                else
                {
                    $("#link-event-wrapper").remove();
                }
            },
            error: function (data, status, error) {
                console.log("Returned with status " + status + " - " + error);
            }
        });
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
</script>