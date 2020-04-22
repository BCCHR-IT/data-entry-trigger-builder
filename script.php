<?php
    $metadata = $data_entry_trigger_builder->retrieveProjectMetadata($module->getProjectId());
    $instrument_names = REDCap::getInstrumentNames();
?>
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
        $(".source-events-autocomplete" ).autocomplete({source: sourceEvents});
    <?php else: ?>
        var sourceEvents = [];
    <?php endif;?>
                
    var sourceFields = [
        <?php
        foreach ($metadata["fields"] as $field)
        {
            print "'$field',";
        }
        ?>
    ]
    $(".source-fields-autocomplete").autocomplete({source: sourceFields});

    var sourceInstr = [
        <?php
        foreach ($instrument_names as $unique_name => $label)
        {
            print "'$unique_name',";
        }
        ?>
    ]
    $(".source-instr-autocomplete").autocomplete({source: sourceInstr});

    $( "#dialog" ).dialog({ autoOpen: false });
    /**
     * Code to check whether input values in autocompletes are valid fields/events/instruments.
     */
    $('.source-events-autocomplete, .source-fields-autocomplete, .source-instr-autocomplete, .dest-fields-autocomplete, .dest-events-autocomplete').focusout(function(event) {
        if ($(this).val() != '' && $.inArray($(this).val(), sourceFields) == -1 && $.inArray($(this).val(), destFields) == -1 && $.inArray($(this).val(), sourceEvents) == -1 
            && $.inArray($(this).val(), destEvents) == -1 && $.inArray($(this).val(), sourceInstr) == -1)
        {
            $(this).attr("style", "border: 2px solid red");
            if ($(this).siblings('.error').length == 0) {
                $(this).after("<p class='error'><i style='color:red'>Invalid field/event/instrument! Please fix before continuing.</i></p>");
            }
            $(this).focus();
            $( "#invalid-entry-modal" ).modal('show');
        }
        else 
        {
            $(this).removeAttr("style");
            $(this).siblings('.error').remove();
        }
    });

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
                    updateAutocompleteItems(data);
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
        destination project changes, and update autcomplete items
    */
    $("#destination-project-select").change(function () {
        // Reset form by removing all triggers.
        $('.trigger-and-data-wrapper').remove();
        
        $.ajax({
            url: "<?php print $module->getUrl("getDestinationFields.php") ?>",
            type: "POST",
            data: {
                pid: $(this).val()
            },
            success: function (data) {
                updateAutocompleteItems(data);
                $(".dest-fields-autocomplete").val("");
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
    $("#det-form").submit(function (event) {
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
        $('#event-select').val("");
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

    $('#add-trigger-btn').click(function() {
        addTrigger();
    })

    $('#add-field-btn, #add-instr-btn').click(function () {
        updateTable(this);
    })
</script>