<?php
    $metadata = $data_entry_trigger_builder->retrieveProjectMetadata($module->getProjectId());
    // $metadata = json_decode($data_entry_trigger_builder->retrieveProjectMetadata($module->getProjectId()), true);
    //print "<!-- in script.php metadata is an array? " . is_array($metadata) . "-->";
    //print "<!-- in script.php metadata is: " . print_r($metadata, true) . "-->";
    $instrument_names = REDCap::getInstrumentNames();
?>
<script>
    window.redcap_csrf_token = <?= json_encode($module->getCSRFToken()) ?>;
    var sourceIsLongitudinal = <?= REDCap::isLongitudinal() ? 'true' : 'false' ?>;
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
        $(".source-events-autocomplete" ).autocomplete({source: sourceEvents, appendTo: "#add-instr-modal"});
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
    $(".source-fields-autocomplete").autocomplete({source: sourceFields, appendTo: "#add-field-modal"});

    var sourceInstr = [
        <?php
        foreach ($instrument_names as $unique_name => $label)
        {
            print "'$unique_name',";
        }
        ?>
    ]
    $(".source-instr-autocomplete").autocomplete({source: sourceInstr, appendTo: "#add-instr-modal"});

    /**
     * When user goes to add a field or instrument
     * update .table-id item, hidden in each modal,
     * to tell them which trigger's table to update.
     * 
     */
    $("body").on("click", ".add-field-btn, .add-instr-btn", function () {
        var id = $(this).closest(".trigger-and-data-wrapper").find("table").attr("id");
        $(".table-id").val(id);
    });

    $("body").on("click", ".fa-pencil-alt", function () {
        var id = $(this).closest("table").attr("id");
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
                dataType: "json",
                data: {
                    pid: $("#destination-project-select").val(),
                    redcap_csrf_token: window.redcap_csrf_token
                },
                success: function (data) {
                    updateAutocompleteItems(data);
                    $(".dest-fields-autocomplete").autocomplete({source: destFields, appendTo: "#add-field-modal"});
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
            dataType: "json",
            data: {
                pid: $(this).val(),
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
        if ($('input[name="redcap_csrf_token"]').length === 0) {
            $('<input type="hidden" name="redcap_csrf_token">')
            .val(window.redcap_csrf_token)
            .appendTo('#det-form');
        }
        $.ajax({
            url: "<?php print $module->getUrl("SubmitForm.php");?>",
            type: "POST",
            dataType: "json",
            data: $("form").serialize(),
            success: function (data) {
                // var errors = JSON.parse(data);  // JORDAN LOOK HERE
                var errors = data;
                
                $(document).find('.error-msg').remove();
                $(document).find(".error").removeClass("error");

                if (errors.success != true)
                {
                    if (errors.linkSourceEvent) 
                    {
                        addError('linkSourceEvent', errors.linkSourceEvent);
                    }

                    if (errors.linkDestEvent)
                    {
                        addError('linkDestEvent', errors.linkDestEvent);
                    }

                    if (errors.linkSource) 
                    {
                        addError('linkSource', errors.linkSource);
                    }

                    if (errors.linkDest)
                    {
                        addError('linkDest', errors.linkDest);
                    }
                    
                    if (errors.pipingSourceEvents)
                    {
                        addTableErrors(errors.pipingSourceEvents, "pipingSourceEvents");
                    }

                    if (errors.pipingSourceFields)
                    {
                        addTableErrors(errors.pipingSourceFields, "pipingSourceFields");
                    }

                    if (errors.pipingDestEvents)
                    {
                        addTableErrors(errors.pipingDestEvents, "pipingDestEvents");
                    }

                    if (errors.pipingDestFields)
                    {
                        addTableErrors(errors.pipingDestFields, "pipingDestFields");
                    }

                    if (errors.setDestEvents)
                    {
                        addTableErrors(errors.setDestEvents, "setDestEvents");
                    }

                    if (errors.setDestFields)
                    {
                        addTableErrors(errors.setDestFields, "setDestFields");
                    }

                    if (errors.sourceInstrEvents)
                    {
                        addTableErrors(errors.sourceInstrEvents, "sourceInstrEvents");
                    }

                    if (errors.sourceInstr)
                    {
                        addTableErrors(errors.sourceInstr, "sourceInstr");
                    }

                    if (errors.trigger_errors)
                    {
                        var triggers = $('.det-trigger');
                        for (var index in errors.trigger_errors)
                        {
                            var item = errors.trigger_errors[index];
                            var msg = "";
                            item.forEach(function(m) {
                                msg += m + "<br/>";
                            });
                            $(triggers[index]).find("input").addClass("error")
                            $(triggers[index]).after("<p class='error-msg'><i>" + msg + "</i></p>")
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
        // $('#source-input').toggle();
        $('#source-select').toggleClass('d-none');
        $('#source-input').toggleClass('d-none');
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
