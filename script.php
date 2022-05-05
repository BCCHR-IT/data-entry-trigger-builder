<?php
    $metadata = $data_entry_trigger_builder->retrieveProjectMetadata($module->getProjectId());
    $instrument_names = REDCap::getInstrumentNames();
?>
<script>
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

        // Show the main form area if it's not already visible
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

                $(document).find('.error-msg').remove();
                $(document).find(".error").removeClass("error");

                if (errors.success != true)
                {
                    for (let i = 0; i < errors.length; i++)
                    {
                        var errors_obj = errors[i];

                        if (errors_obj.trigger_errors)
                        {
                            var item = errors_obj.trigger_errors;
                            var msg = "";
                            item.forEach(function(m) {
                                msg += m + "<br/>";
                            });
                            $('.det-trigger:eq(' + i + ')').find("textarea").addClass("error");
                            $('.det-trigger:eq(' + i + ')').after("<p class='error-msg'><i>" + msg + "</i></p>");
                        }

                        if (errors_obj.linkSourceEvent) 
                        {
                            addError(i, 'linkSourceEvent', errors_obj.linkSourceEvent);
                        }

                        if (errors_obj.linkDestEvent)
                        {
                            addError(i, 'linkDestEvent', errors_obj.linkDestEvent);
                        }

                        if (errors_obj.linkSource) 
                        {
                            addError(i, 'linkSource', errors_obj.linkSource);
                        }

                        if (errors_obj.linkDest)
                        {
                            addError(i, 'linkDest', errors_obj.linkDest);
                        }
                        
                        if (errors_obj.pipingSourceEvents)
                        {
                            addTableErrors(i, errors_obj.pipingSourceEvents, "pipingSourceEvents");
                        }

                        if (errors_obj.pipingSourceFields)
                        {
                            addTableErrors(i, errors_obj.pipingSourceFields, "pipingSourceFields");
                        }

                        if (errors_obj.pipingDestEvents)
                        {
                            addTableErrors(i, errors_obj.pipingDestEvents, "pipingDestEvents");
                        }

                        if (errors_obj.pipingDestFields)
                        {
                            addTableErrors(i, errors_obj.pipingDestFields, "pipingDestFields");
                        }

                        if (errors_obj.setDestEvents)
                        {
                            addTableErrors(i, errors_obj.setDestEvents, "setDestEvents");
                        }

                        if (errors_obj.setDestFields)
                        {
                            addTableErrors(i, errors_obj.setDestFields, "setDestFields");
                        }

                        if (errors_obj.sourceInstrEvents)
                        {
                            addTableErrors(i, errors_obj.sourceInstrEvents, "sourceInstrEvents");
                        }

                        if (errors_obj.sourceInstr)
                        {
                            addTableErrors(i, errors_obj.sourceInstr, "sourceInstr");
                        }

                        if (errors_obj.destInstrEvents)
                        {
                            addTableErrors(i, errors_obj.destInstrEvents, "destInstrEvents");
                        }

                        if (errors_obj.surveyUrlEvent) 
                        {
                            addError(i, 'surveyUrlEvent', errors_obj.surveyUrlEvent);
                        }

                        if (errors_obj.surveyUrl)
                        {
                            addError(i, 'surveyUrl', errors_obj.surveyUrl);
                        }

                        if (errors_obj.saveUrlEvent) 
                        {
                            addError(i, 'saveUrlEvent', errors_obj.saveUrlEvent);
                        }

                        
                        if (errors_obj.saveUrlField)
                        {
                            addError(i, 'saveUrlField', errors_obj.saveUrlField);
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
    });

    $('#add-trigger-btn').click(function() {
        addTrigger();

        // Bind autocomplete functionality

        /** 
         * Code to populate the populate
         * the autocomplete fields for the 
         * source project
         */
        var sourceEvents = [];

        <?php if (REDCap::isLongitudinal()): ?>
        sourceEvents = [<?php foreach ($metadata["events"] as $event) { print "'$event',"; }?>];
        $(".source-events-autocomplete" ).autocomplete({source: sourceEvents});
        <?php endif;?>
                    
        var sourceFields = [<?php foreach ($metadata["fields"] as $field) { print "'$field',"; } ?>];
        $(".source-fields-autocomplete").autocomplete({source: sourceFields});

        var sourceInstr = [<?php foreach ($instrument_names as $unique_name => $label) { print "'$unique_name',"; } ?>];
        $(".source-instr-autocomplete").autocomplete({source: sourceInstr});

        /**
            Call to retrieve destination project's fields and instruments and update autcomplete items
        */
        $.ajax({
            url: "<?php print $module->getUrl("getDestinationFields.php") ?>",
            type: "POST",
            data: {
                pid: $("#destination-project-select").val()
            },
            success: function (data) {
                updateAutocompleteItems(data);
            },
            error: function (data, status, error) {
                console.log("Returned with status " + status + " - " + error);
            }
        });
    });

    $('#add-field-btn, #add-instr-btn').click(function () {
        updateTable(this);
    });

    /**
     * Code to make sure only one option of the prefix/postfix choice is selected
     **/
     $('#det-form').on('click', '.prefixOrPostfix', function () {
        if ($(this).val() == "pre")
            $(this).siblings('.prefixOrPostfix[value="post"]').prop('checked', false);
        else 
            $(this).siblings('.prefixOrPostfix[value="pre"]').prop('checked', false);
     });
</script>