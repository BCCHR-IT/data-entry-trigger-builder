var row = null;
var destFields = [];
var destEvents = [];

function createFieldRow()
{
    var id = $(".table-id").val();
    var index = id.substring(id.length - 1, id.length);
    var sourceEvent = '';
    var sourceEventElem = '';
    var destEvent = '';
    var destEventElem = '';
    var destField = '[' + $('#dest-field-select').val() + ']';
    
    if ($('#field-value').val() != '') {
        var sourceField = "'" + $('#field-value').val() + "'";
        var sourceFieldElem = "<input class='setDestFieldsValues' type='hidden' name='setDestFieldsValues[" + index + "][]' value='" + $('#field-value').val() + "'/>";

        if ($('#dest-event-select').val() && $('#dest-event-select').val() != '') {
            var destEvent = '[' + $('#dest-event-select').val() + ']';
            var destEventElem = "<input class='setDestEvents' type='hidden' name='setDestEvents[" + index + "][]' value='" + $('#dest-event-select').val() + "'/>";
        }
        var destFieldElem = "<input class='setDestFields' type='hidden' name='setDestFields[" + index + "][]' value='" + $('#dest-field-select').val() + "'/>";
        var editFunction = 'fillFieldForm(this)'
    }
    else {
        if ($('#event-select').val() && $('#event-select').val() != '') {
            var sourceEvent = '[' + $('#event-select').val() + ']';
            var sourceEventElem =  "<input class='pipingSourceEvents' type='hidden' name='pipingSourceEvents[" + index + "][]' value='" + $('#event-select').val() + "'/>";
        }
        var sourceField = '[' + $('#field-select').val() + ']';
        var sourceFieldElem = "<input class='pipingSourceFields' type='hidden' name='pipingSourceFields[" + index + "][]' value='" + $('#field-select').val() + "'/>";

        if ($('#dest-event-select').val() && $('#dest-event-select').val() != '') {
            var destEvent = '[' + $('#dest-event-select').val() + ']';
            var destEventElem = "<input class='pipingDestEvents' type='hidden' name='pipingDestEvents[" + index + "][]' value='" + $('#dest-event-select').val() + "'/>";
        }
        var destFieldElem = "<input class='pipingDestFields' type='hidden' name='pipingDestFields[" + index + "][]' value='" + $('#dest-field-select').val() + "'/>";
        var editFunction = 'fillPipingFieldForm(this)'
    }

    var html = "<tr class='trigger-field-row'>" +
                    "<td>" + sourceEvent + sourceField + sourceEventElem + sourceFieldElem + "</td>" +
                    "<td>" + destEvent + destField + destEventElem + destFieldElem + "</td>" +
                    "</td><td><span class='fa fa-pencil-alt' onclick='" + editFunction + "'></span></td>" +
                    "<td><span class='fa fa-trash-alt delete-trigger-field'></span></td>" + 
                "</tr>";

    return html;
}

function createInstrRow()
{
    var id = $(".table-id").val();
    var index = id.substring(id.length - 1, id.length);
    var sourceEvent = '';
    var sourceEventElem = '';

    if ($('#instr-event-select').val() && $('#instr-event-select').val() != '') {
        var sourceEvent = '[' + $('#instr-event-select').val() + ']';
        var sourceEventElem = "<input class='sourceInstrEvents' type='hidden' name='sourceInstrEvents[" + index + "][]' value='" + $('#instr-event-select').val() + "'/>";
    }
    var sourceInstr = '[' + $('#instr-select').val() + ']';
    var sourceInstrElem = "<input class='sourceInstr' type='hidden' name='sourceInstr[" + index + "][]' value='" + $('#instr-select').val() + "'/>";

    if ($('#dest-event-instrument').val() && $('#dest-event-instrument').val() != '') {
        var destEvent = '[' + $('#dest-event-instrument').val() + ']';
        var destEventElem = "<input class='destInstrEvents' type='hidden' name='destInstrEvents[" + index + "][]' value='" + $('#dest-event-instrument').val() + "'/>";
    }
    else {
        var destEvent = "<i>Data is moving to a classic project, so there are no events</i>";
        var destEventElem = "";
    }

    var html = "<tr class='trigger-field-row'>" +
                    "<td>" + sourceEvent + sourceInstr + sourceEventElem + sourceInstrElem + "</td>" +
                    "<td>" + destEvent + destEventElem + "</td>" +
                    "</td><td><span class='fa fa-pencil-alt' onclick='fillInstrForm(this)'></span></td>" +
                    "<td><span class='fa fa-trash-alt delete-trigger-field'></span></td>" + 
                "</tr>"

    return html;
}

function updateTable(elem)
{
    if ($(elem).attr("id") == "add-field-btn" && validateFieldForm())
    {
        var newRow = createFieldRow();
        clearFieldForm();
    }   
    else if (validateInstrumentForm())
    {
        var newRow = createInstrRow();
        clearInstrForm();
    }

    if (newRow)
    {
        if ($(elem).text() == 'Update')
        {
            row.after(newRow)
            row.remove();
            $(elem).text("Add");
        }
        else
        {
            var id = $(".table-id").val();
            $("#" + id).find("tbody").append(newRow);
        }
    }
    else
    {
        alert("Please make sure all fields are filled out before, clicking 'Add'!");
    }
}

function addTrigger()
{
    var triggers = $(".trigger-and-data-wrapper");
    var trigNum = triggers.length;
    
    var html = "<div class='form-group trigger-and-data-wrapper new-wrapper'>" +
                "<div class='det-trigger'>" +
                    "<div class='row'>" + 
                        "<div class='col-sm-2'>" +
                            "<h6>Trigger:</h6>" +
                        "</div>" +
                        "<div class='col-sm-9'></div>" +
                        "<div class='col-sm-1' style='text-align: center;'>" +
                            "<span class='fa fa-trash-alt delete-trigger-btn'></span>" +
                        "</div>" +
                    "</div>" +
                    "<textarea rows='1' name='triggers[]' class='form-control det-trigger-input' required></textarea>" +
                "</div>" +
                "<h7>Record Linkage</h7>" +
                "<p>" + 
                    "Create subjects/push data to linked project using variables in source and linked project." +  
                    "When at least one of the triggers are met, then records between the source and linked project will be linked via the chosen fields." + 
                "</p>" + 
                "<p><b>IMPORTANT: When linking projects with anything other than the record ID fields, \"Auto-numbering for records\" must be turned on in the destination project.</b></p>" + 
                "<div class='row link-field form-group'>"
                    "<div class='col-sm-12' style='margin-bottom:10px'>" + 
                        "<div class='class-sm-12'><label>Link source project field</label></div>" + 
                        "<div class='row'>" + 
                            "<div class='col-sm-6'>" +
                                "<input id='linkSourceEvent' class='source-events-autocomplete form-control' name='linkSourceEvent' placeholder='Type to search for event' value='<?php print htmlspecialchars($settings['linkSourceEvent'], ENT_QUOTES); ?>' required>" +
                            "</div>" +
                            "<div class='col-sm-6'>" + 
                                "<input id='linkSource' class='source-fields-autocomplete form-control' name='linkSource' placeholder='Type to search for field' value='<?php print htmlspecialchars($settings['linkSource'], ENT_QUOTES); ?>' required>" +
                            "</div>"
                        "</div>" +
                    "</div>" +
                    "<div class='col-sm-12' style='margin-bottom:20px'>" + 
                        "<div class='class-sm-12' id='link-source-text'><label>To linked project field</label></div>" + 
                        "<div class='row'>" + 
                            "<div class='col-sm-6 dest-event-wrapper'>" + 
                                "<input id='linkDestEvent' class='dest-events-autocomplete form-control' name='linkDestEvent' placeholder='Type to search for event' required>" +
                            "</div>" + 
                            "<div id='link-source-wrapper' class='col-sm-6'>" + 
                                "<input id='linkDest' class='dest-fields-autocomplete form-control' name='linkDest' placeholder='Type to search for field' required>" +
                            "</div>" +
                        "</div>" + 
                    "</div>" + 
                    "<div class='col-sm-6' style='margin-bottom:20px'>" + 
                        "<h6>Create Empty Records</h6>" + 
                        "<div class='class-sm-12'><label>If 'yes' is chosen, then an empty record is created when at least one of the triggers below is met. Use this option when you don't want any data moved with the triggers.</label></div>" + 
                        "<div class='form-check col-sm-12'>" + 
                            "<input type='radio' name='create-empty-record' class='form-check-input' value='1' required><label class='form-check-label'>Yes</label>" + 
                            "<br>" + 
                            "<input type='radio' name='create-empty-record' class='form-check-input' value='0' required><label class='form-check-label'>No</label>" + 
                        "</div>" + 
                    "</div>" + 
                    "<div class='col-sm-6'>" + 
                        "<h6>Add Pre/Postfix to Linked Field (Optional)</h6>" + 
                        "<div class='class-sm-12'><label>Add a static prefix or a postfix to the linked source field when moving data. Enter what you'd like to append, and select whether its a prefix or postfix. If no option is selected, then a prefix is used.</label></div>" +
                        "<div class='row'>" + 
                            "<div class='col-sm-6'>" +
                                "<input id='prefixPostfixStr' class='form-control' name='prefixPostfixStr' placeholder='Enter your prefix/postfix'>" +
                            "</div>" + 
                            "<div class='col-sm-6'>" + 
                                "<input type='checkbox' name='prefixOrPostfix' class='form-check-input' value='pre'><label class='form-check-label'>Prefix</label>" + 
                                "<br>" +
                                "<input type='checkbox' name='prefixOrPostfix' class='form-check-input' value='post'><label class='form-check-label'>Postfix</label>" +
                            "</div>" +
                        "</div>" +
                    "</div>" +
                "</div>" +
                "<h7>Copy the following instruments/fields from source project to linked project when the above condition is true:</h7>" +
                "<button type='button' data-toggle='modal' data-target='#add-field-modal' class='btn btn-primary btn-xs add-field-btn'>Add Field</button> " + 
                "<button type='button' data-toggle='modal' data-target='#add-instr-modal' class='btn btn-primary btn-xs add-instr-btn'>Add Instrument</button>" +
                "<br/><br/>" + 
                "<table class='table' id='table-" + trigNum + "'>" +
                    "<thead>" + 
                        "<tr>" + 
                            "<th>From Source Project</th>" +
                            "<th>To Linked Project</th>" +
                            "<th>Edit?</th>" +
                            "<th>Delete?</th>" +
                        "</tr>" + 
                    "</thead>" +
                    "<tbody>" +
                    "</tbody>" +
                "</table>" + 
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
}

function clearFieldForm()
{
    $('#event-select').val("");
    $('#field-value').val("");
    $('#field-select').val("");
    $('#dest-event-select').val("");
    $('#dest-field-select').val("");
}

function clearInstrForm()
{
    $('#instr-event-select').val("");
    $('#instr-select').val("");
    $('#dest-event-instrument').val("");
}

function fillPipingFieldForm(elem)
{
    row = $(elem).parent("td").parent("tr");
    $('#source-input').hide();
    $('#source-select').show();

    $('#field-select').val(row.find(".pipingSourceFields").val());
    $('#dest-field-select').val(row.find(".pipingDestFields").val());
    
    if (row.find(".pipingSourceEvents"))
    {
        $('#event-select').val(row.find(".pipingSourceEvents").val());
    }

    if (row.find(".pipingDestEvents") && $('#dest-event-select').is(':visible'))
    {
        $('#dest-event-select').val(row.find(".pipingDestEvents").val());
    }
    
    $('#add-field-btn').text("Update");
    $('#add-field-modal').modal('show');
}

function fillFieldForm(elem)
{
    row = $(elem).parent("td").parent("tr");
    $('#source-input').show();
    $('#source-select').hide();

    $('#field-value').val(row.find(".setDestFieldsValues").val());
    $('#dest-field-select').val(row.find(".setDestFields").val());

    if (row.find(".setDestEvents") && $('#dest-event-select').is(':visible'))
    {
        $('#dest-event-select').val(row.find(".setDestEvents").val());
    }
    
    $('#add-field-btn').text("Update");
    $('#add-field-modal').modal('show');
}

function fillInstrForm(elem)
{
    row = $(elem).parent("td").parent("tr");
    
    $('#instr-select').val(row.find(".sourceInstr").val());

    if (row.find(".sourceInstrEvents"))
    {
        $('#instr-event-select').val(row.find(".sourceInstrEvents").val()); 
    }

    if (row.find(".destInstrEvents") && $('#dest-event-instrument').is(':visible'))
    {
        $('#dest-event-instrument').val(row.find(".destInstrEvents").val());
    }

    $('#add-instr-btn').text("Update");
    $('#add-instr-modal').modal('show');
}

function validateFieldForm()
{
    if ($('#dest-field-select').val() == '' || 
        ($('#dest-event-select').is(':visible') && $('#dest-event-select').val() == '') ||
        ($('#field-value').is(':visible') && $('#field-value').val() == '') ||
        ($('#event-select').is(':visible') && $('#event-select').val() == '') ||
        ($('#field-select').is(':visible') && $('#field-select').val() == ''))
    {
        return false;
    }
    return true;
}

function validateInstrumentForm()
{
    if (($('#instr-event-select') && $('#instr-event-select').val() == '') || 
        $('#instr-select').val() == '' ||
        ($('#dest-event-instrument').is(':visible') && $('#dest-event-instrument').val() == ''))
    {
        return false;
    }
    return true;
}

function updateAutocompleteItems(data)
{
    var metadata = JSON.parse(data);
    destFields = metadata.fields;
    destEvents = metadata.events;
    destInstruments = metadata.instruments;

    var isLongitudinal = metadata.isLongitudinal;

    if (isLongitudinal) {
        $(".dest-events-autocomplete").autocomplete({source: destEvents});
        $(".dest-events-autocomplete").prop("required", true);
        $(".dest-event-wrapper").show();
        $("#add-instr-label-event-div").show();
    }
    else {
        $(".dest-events-autocomplete").val("");
        $(".dest-events-autocomplete").prop("required", false);
        $(".dest-event-wrapper").hide();
        $("#add-instr-label-event-div").hide();
    }
    $("#surveyUrlEvent").prop("required", false); // This field should always be optional
    $(".dest-fields-autocomplete").autocomplete({source: destFields});
    $("#surveyUrl").autocomplete({source: destInstruments});
}

function addError(id, error)
{
    $('#' + id).addClass("error");
    $('#' + id).after("<p class='error-msg'><i>" + error + "</i></p>");
}

function addTableErrors(errors, inputName)
{
    for(var index in errors)
    {
        var items = $("td > input[name='" + inputName + "[" + index + "][]']");
        console.log(items);
        for(var i in errors[index])
        {
            var msg =  errors[index][i];
            $(items[i]).after("<p class='error-msg'><i>" + msg + "</i></p>");
        }   
    }
}