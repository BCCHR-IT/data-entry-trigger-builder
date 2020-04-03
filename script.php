<script>
    /** 
     * Code to populate the populate
     * the autocomplete fields for the 
     * source project
     */

    <?php if (REDCap::isLongitudinal()): ?>
        var sourceEvents = [
            <?php
            $events = REDCap::getEventNames(true, true);
            foreach ($events as $event)
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
        $metadata = REDCap::getDataDictionary('array');
        foreach ($metadata as $field => $data)
        {
            print "'$field',";
        }
        ?>
    ]
    $(".source-fields-autocomplete" ).autocomplete({source: sourceFields});

    var sourceInstr = [
        <?php
        $instrument_names = REDCap::getInstrumentNames();
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