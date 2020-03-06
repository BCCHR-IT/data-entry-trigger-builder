<script>
    /**
        The field and instrument options for the current destination project
     */
    var fieldOptions = [];
    var instrOptions = [];

    /**
        Code to add another trigger
     */
    $("body").on('click', '.add-trigger-btn', function () {
        var triggers = $(".trigger-and-data-wrapper");
        if (triggers.length < 10)
        {
            triggers.first().before(
                "<div class='form-group trigger-and-data-wrapper new-wrapper'>" +
                    "<div class='det-trigger'>" +
                        "<div class='row'>" + 
                            "<div class='col-sm-2'>" +
                                "<label>Condition:</label>" +
                            "</div>" +
                            "<div class='col-sm-9'></div>" +
                            "<div class='col-sm-1'>" +
                                "<span class='fa fa-minus delete-trigger-btn' style='margin-right: 5px'></span>" +
                                "<span class='fa fa-plus add-trigger-btn'></span>" +
                            "</div>" +
                        "</div>" +
                        "<input name='triggers[]' type='text' class='form-control det-trigger-input'>" +
                    "</div>" +
                    "<p>Copy the following instruments/fields from source project to linked project when the above condition is true:</p>" +
                    "<div class='row' style='margin-top:20px'>" +
                        "<div class='col-sm-2'><button type='button' class='btn btn-primary add-instr-btn'>+ Instrument</button></div>" +
                        "<div class='col-sm-2'><button type='button' class='btn btn-primary add-field-btn'>+ Field</button></div>" +
                    "</div>" + 
                "</div>"
            );
            $(".trigger-and-data-wrapper").last().find('.selectpicker').selectpicker('render')
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

            var elem = $(
                "<div class='row det-instrument' style='margin-top:20px;'>" +
                    "<div class='col-sm-2'><p>Copy instrument</p></div>" +
                    "<div class='col-sm-3'>" +
                        "<select name='sourceInstr[]' class='form-control selectpicker select-source-instr' data-live-search='true' required>" +
                        "<option value='' disabled selected>Select an instrument</option>" + 
                        <?php
                            $instruments = REDCap::getInstrumentNames();
                            foreach($instruments as $unique_name => $label)
                            {
                                print "\"<option value='$unique_name'>$unique_name</option>\" +";
                            }
                        ?>
                        "</select>" +
                    "</div>" +
                    "<div class='col-sm-1'><p>to</p></div>" +
                    "<div class='col-sm-3'>" +
                        "<select name='destInstr[]' class='form-control selectpicker select-dest-instr' data-live-search='true' required>" +
                        "<option value='' disabled selected>Select an instrument</option>" + 
                        options + 
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
            var options = "";
            fieldOptions.forEach(function(item) {
                options += "<option " + "value='" + item + "'>" + item + "</option>";
            });

            var elem = $(
                "<div class='row det-field' style='margin-top:20px'>" +
                    "<div class='col-sm-2'><p>Copy field</p></div>" +
                    "<div class='col-sm-3'>" +
                        "<select name='sourceFields[]' class='form-control selectpicker' data-live-search='true' required>" +
                        "<option value='' disabled selected>Select a field</option>" + 
                        <?php
                            $fields = REDCap::getFieldNames();
                            foreach($fields as $field)
                            {
                                print "\"<option value='$field'>$field</option>\" +";
                            }
                        ?>
                        "</select>" +
                    "</div>" +
                    "<div class='col-sm-1'><p>to</p></div>" +
                    "<div class='col-sm-3'>" +
                        "<select name='destFields[]' class='form-control selectpicker select-dest-field' data-live-search='true' required>" +
                        "<option value='' disabled selected>Select a field</option>" + 
                        options + 
                        "</select>" +
                    "</div>" +
                    "<div class='col-sm-1' style='text-align: center; padding-top: 1%; padding-bottom: 1%;'>" +
                        "<span class='fa fa-minus delete-field-btn' style='margin-right: 5px'></span>" +
                    "</div>" +
                "</div>"
            );
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
     * Ajax call to retrieve destination project's fields and instruments
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

                var options = "<option value='' disabled selected>Select a field</option>";
                fieldOptions.forEach(function(item) {
                    options += "<option " + "value='" + item + "'>" + item + "</option>";
                });

                $("#link-dest-select").empty().append(options)
                $("#link-dest-select").selectpicker('refresh');

            },
            error: function (data, status, error) {
                console.log("Returned with status " + status + " - " + error);
            }
        });
    });

    /**
     * Ajax call to submit form, and validate
     */
    $("#create-det-btn").click(function () {
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
                    if (errors.create_subject_errors.length > 0)
                    {
                        var msg = "<b>ERROR! Syntax errors exist in the logic:</b><br>"
                        errors.create_subject_errors.forEach(function(item) {
                            msg += "&bull; " + item + "<br/>";
                        })
                        $("#create-record-input").attr("style", "border: 2px solid red")
                        $("#create-record-input").after("<p class='error'><i style='color:red'>" + msg + "</i></p>")
                    }
                    
                    if (errors.trigger_errors != undefined)
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
            },
            error: function (data, status, error) {
                console.log("Returned with status " + status + " - " + error);
            }
        });
    })
</script>