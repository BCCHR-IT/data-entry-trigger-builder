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
        var sourceFieldElem = "<input class='setDestFieldsValues' type='hidden' name='triggers[" + index + "][setDestFieldsValues][]' value='" + $('#field-value').val() + "'/>";

        if ($('#dest-event-select').val() && $('#dest-event-select').val() != '') {
            destEvent = '[' + $('#dest-event-select').val() + ']';
            destEventElem = "<input class='setDestEvents' type='hidden' name='triggers[" + index + "][setDestEvents][]' value='" + $('#dest-event-select').val() + "'/>";
        }
        var destFieldElem = "<input class='setDestFields' type='hidden' name='triggers[" + index + "][setDestFields][]' value='" + $('#dest-field-select').val() + "'/>";
        var editFunction = 'fillFieldForm(this)'
    }
    else {
        if ($('#event-select').val() && $('#event-select').val() != '') {
            sourceEvent = '[' + $('#event-select').val() + ']';
            sourceEventElem =  "<input class='pipingSourceEvents' type='hidden' name='triggers[" + index + "][pipingSourceEvents][]' value='" + $('#event-select').val() + "'/>";
        }
        var sourceField = '[' + $('#field-select').val() + ']';
        var sourceFieldElem = "<input class='pipingSourceFields' type='hidden' name='triggers[" + index + "][pipingSourceFields][]' value='" + $('#field-select').val() + "'/>";

        if ($('#dest-event-select').val() && $('#dest-event-select').val() != '') {
            destEvent = '[' + $('#dest-event-select').val() + ']';
            destEventElem = "<input class='pipingDestEvents' type='hidden' name='triggers[" + index + "][pipingDestEvents][]' value='" + $('#dest-event-select').val() + "'/>";
        }
        var destFieldElem = "<input class='pipingDestFields' type='hidden' name='triggers[" + index + "][pipingDestFields][]' value='" + $('#dest-field-select').val() + "'/>";
        var editFunction = 'fillPipingFieldForm(this)'
    }

    let html = "<tr class='trigger-field-row'>" +
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
        sourceEvent = '[' + $('#instr-event-select').val() + ']';
        sourceEventElem = "<input class='sourceInstrEvents' type='hidden' name='triggers[" + index + "][sourceInstrEvents][]' value='" + $('#instr-event-select').val() + "'/>";
    }
    var sourceInstr = '[' + $('#instr-select').val() + ']';
    var sourceInstrElem = "<input class='sourceInstr' type='hidden' name='triggers[" + index + "][sourceInstr][]' value='" + $('#instr-select').val() + "'/>";

    if ($('#dest-event-instrument').val() && $('#dest-event-instrument').val() != '') {
        var destEvent = '[' + $('#dest-event-instrument').val() + ']';
        var destEventElem = "<input class='destInstrEvents' type='hidden' name='triggers[" + index + "][destInstrEvents][]' value='" + $('#dest-event-instrument').val() + "'/>";
    }
    else {
        var destEvent = "<i>Data is moving to a classic project, so there are no events</i>";
        var destEventElem = "";
    }

    let html = "<tr class='trigger-field-row'>" +
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
            let id = $(".table-id").val();
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
    let triggers = $(".trigger-and-data-wrapper");
    let trigNum = triggers.length;
    let sourceIsLongitudinal = $("form").attr("data-source-is-longitudinal");
    
    let html = "<div class='form-group trigger-and-data-wrapper new-wrapper'>" +
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
                    "<textarea rows='1' name=\"triggers[" + trigNum + "][trigger]\" class='form-control det-trigger-input' required></textarea>" +
                "</div>" +
                "<h6 style='margin-top:10px'>Select a Linked Project</h6>" + 
                "<p>The module will move the data into the chosen project.</p>" + 
                "<div class='form-group'>" + 
                    "<select name='triggers[" + trigNum + "][dest-project]' class='destination-project-select form-control selectpicker' data-live-search='true' required>" + 
                        "<option value='' disabled selected>Select a project</option>";

    for (let i in projectOptions)
    {
        html = html + projectOptions[i];
    }

    html = html +  
            "</select>" + 
            "</div>" + 
            "<h6>Record Linkage</h6>" +
            "<p>" + 
                "Create subjects/push data to linked project using variables in source and linked project. When the trigger is met, then records between the source and linked project will be linked via the chosen fields. An option to pick events will appear, if a project is longitudinal." + 
                "<b> When linking projects with anything other than the record ID fields, 'Auto-numbering for records' must be turned on in the destination project.</b>" +
            "</p>" + 
            "<div class='row link-field form-group'>" + 
                "<div class='col-sm-12' style='margin-bottom:10px'>" + 
                    "<div class='class-sm-12'><label>Link source project field</label></div>" + 
                    "<div class='row'>";

    if (sourceIsLongitudinal == "yes")
    {
        html = html +  "<div class='col-sm-6'>" +
                "<input class='linkSourceEvent source-events-autocomplete form-control' name=\"triggers[" + trigNum + "][linkSourceEvent]\" placeholder='Type to search for event' required>" +
            "</div>";
    }

    html = html + "<div class='col-sm-6'>" + 
                            "<input class='linkSource source-fields-autocomplete form-control' name=\"triggers[" + trigNum + "][linkSource]\" placeholder='Type to search for field' required>" +
                        "</div>" + 
                    "</div>" +
                "</div>" +
                "<div class='col-sm-12' style='margin-bottom:20px'>" + 
                    "<div class='class-sm-12' id='link-source-text'><label>To linked project field</label></div>" + 
                    "<div class='row'>" + 
                        "<div class='col-sm-6 dest-event-wrapper'>" + 
                            "<input class='linkDestEvent dest-events-autocomplete form-control' name=\"triggers[" + trigNum + "][linkDestEvent]\" placeholder='Type to search for event' required>" +
                        "</div>" + 
                        "<div id='link-source-wrapper' class='col-sm-6'>" + 
                            "<input class='linkDest dest-fields-autocomplete form-control' name=\"triggers[" + trigNum + "][linkDest]\" placeholder='Type to search for field' required>" +
                        "</div>" +
                    "</div>" + 
                "</div>" + 
                "<div class='col-sm-6' style='margin-bottom:20px'>" + 
                    "<h6>Create Empty Records</h6>" + 
                    "<div class='class-sm-12'><label>If 'yes' is chosen, then an empty record is created when the trigger is met. Use this option when you don't want any data moved with the triggers.</label></div>" + 
                    "<div class='form-check col-sm-12'>" + 
                        "<input type='radio' name=\"triggers[" + trigNum + "][create-empty-record]\" class='create-empty-record form-check-input' value='1' required><label class='form-check-label'>Yes</label>" + 
                        "<br>" + 
                        "<input type='radio' name=\"triggers[" + trigNum + "][create-empty-record]\" class='create-empty-record form-check-input' value='0' required><label class='form-check-label'>No</label>" + 
                    "</div>" + 
                "</div>" + 
                "<div class='col-sm-6'>" + 
                    "<h6>Add Pre/Postfix to Linked Field (Optional)</h6>" + 
                    "<div class='class-sm-12'><label>Add a static prefix or postfix to the linked source field when moving data. If neither prefix or postfix is selected, then a prefix is used.</label></div>" +
                    "<div class='row'>" + 
                        "<div class='col-sm-6'>" +
                            "<input class='form-control' name=\"triggers[" + trigNum + "][prefixPostfixStr]\" placeholder='Enter your prefix/postfix'>" +
                        "</div>" + 
                        "<div class='col-sm-6'>" + 
                            "<input type='checkbox' name=\"triggers[" + trigNum + "][prefixOrPostfix]\" class='prefixOrPostfix form-check-input' value='pre'><label class='form-check-label'>Prefix</label>" + 
                            "<br>" +
                            "<input type='checkbox' name=\"triggers[" + trigNum + "][prefixOrPostfix]\" class='prefixOrPostfix form-check-input' value='post'><label class='form-check-label'>Postfix</label>" +
                        "</div>" +
                    "</div>" +
                "</div>" +
            "</div>" +
            "<h6>Data Movement</h6>" + 
            "<p>Copy the data below from source project to linked project when the trigger is met:</p>" +
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
            "<h6>Generate Survey URLs (Optional)</h6>" + 
            "<p>If specified, the destination project will generate a survey url for the participant to redirect to. If the trigger is met, a survey url will generate after the data is moved.</p>" + 
            "<div class='row'>" + 
                "<div class='form-group col-6'>" + 
                    "<label>Specify the destination instrument the module will generate a survey url from.</label>" + 
                    "<div class='row'>" + 
                        "<div class='col-sm-6 ui-front dest-event-wrapper'>" +
                            "<input class='surveyUrlEvent dest-events-autocomplete form-control' name=\"triggers[" + trigNum + "][surveyUrlEvent]\" placeholder='Type to search for event'>" + 
                        "</div>" +
                        "<div class='col-sm-6 ui-front'>" + 
                            "<input class='surveyUrl form-control' name=\"triggers[" + trigNum + "][surveyUrl]\" placeholder='Type to search for instrument'>" + 
                        "</div>" + 
                    "</div>" + 
                "</div>" + 
                "<div class='form-group col-6'>" + 
                    "<label>Specify the source field the survey url will be saved to, for redirection.</label>" + 
                    "<div class='row'>";

    if (sourceIsLongitudinal == "yes")
    {
        html = html + "<div class='col-sm-6'>" + 
                    "<input class='saveUrlEvent source-events-autocomplete form-control' name=\"triggers[" + trigNum + "][saveUrlEvent]\" placeholder='Type to search for event'>" + 
                "</div>";
    }
 
    html = html +   "<div class='col-sm-6'>" +
                        "<input class='saveUrlField source-fields-autocomplete form-control' name=\"triggers[" + trigNum + "][saveUrlField]\" placeholder='Type to search for field'>" + 
                    "</div>" + 
                "</div>" + 
            "</div>" + 
        "</div>" + 
        "<h6>Confirm the following</h6>" +
        "<div class='row form-group'>" + 
            "<div class='col-sm-6'>" + 
                "<label>Overwrite data in destination project every time data is saved? This determines whether to push blank data over to the destination project.</label>" + 
                "<div class='form-check col-sm-12'>" + 
                    "<input type='radio' name='triggers[" + trigNum + "][overwrite-data]' class='form-check-input' value='overwrite' required><label class='form-check-label'>Yes</label>" +
                    "<br>" + 
                    "<input type='radio' name='triggers[" + trigNum + "][overwrite-data]' class='form-check-input' value='normal' required><label class='form-check-label'>No</label>" +
                "</div>" + 
            "</div>" + 
            "<div class='col-sm-6'>" +
                "<label>Import data access groups (DAGs) every time data is saved? The module can only import DAGs if they have a one-to-one relationship with the destination project.</label>" +
                "<div class='form-check col-sm-12'>" + 
                    "<input type='radio' name='triggers[" + trigNum + "][import-dags]' class='form-check-input' value='1' required><label class='form-check-label'>Yes</label>" +
                    "<br>" +
                    "<input type='radio' name='triggers[" + trigNum + "][import-dags]' class='form-check-input' value='0' required><label class='form-check-label'>No</label>" +
                "</div>" + 
            "</div>" +
        "</div>" +
    "</div>";
    
    if (triggers.length == 0)
    {
        $("#trigger-instr").after(html);

    }
    else if (triggers.length < 10)
    {
        triggers.last().after(html);
        $(".destination-project-select").selectpicker();
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

    if (row.find(".pipingDestEvents") && $('#dest-event-select').attr('data-is-longitudinal') == 'yes')
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

    if (row.find(".setDestEvents") && $('#dest-event-select').attr('data-is-longitudinal') == 'yes')
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

    if (row.find(".destInstrEvents") && $('#dest-event-instrument').attr('data-is-longitudinal') == 'yes')
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

function updateElemAutocompleteItems(elem, data)
{
    let metadata = JSON.parse(data);
    let isLongitudinal = metadata.isLongitudinal;

    destFields = metadata.fields;
    destEvents = metadata.events;
    destInstruments = metadata.instruments;

    if (isLongitudinal) {
        elem.find(".dest-events-autocomplete").autocomplete({source: destEvents});
        elem.find(".dest-events-autocomplete").prop("required", true);
        elem.find(".dest-event-wrapper").show();
        $("#add-instr-label-event-div").show();
        $("#dest-event-instrument, #dest-event-select").attr("data-is-longitudinal", "yes");
    }
    else {
        elem.find(".dest-events-autocomplete").val("");
        elem.find(".dest-events-autocomplete").prop("required", false);
        elem.find(".dest-event-wrapper").hide();
        $("#add-instr-label-event-div").hide();
        $("#dest-event-instrument, #dest-event-select").attr("data-is-longitudinal", "no");
    }
    elem.find(".surveyUrlEvent").prop("required", false); // This field should always be optional
    elem.find(".dest-fields-autocomplete").autocomplete({source: destFields});
    elem.find(".surveyUrl").autocomplete({source: destInstruments});
}

function addError(index, className, error)
{
    $('.' + className + ':eq(' + index + ')').addClass("error");
    $('.' + className + ':eq(' + index + ')').after("<p class='error-msg'><i>" + error + "</i></p>");
}

function addTableErrors(index, errors, inputName)
{
    let items = $("td > input[name='triggers[" + index + "][" + inputName + "][]']");
    for(let i in errors)
    {
        let msg =  errors[i];
        $(items[i]).after("<p class='error-msg'><i>" + msg + "</i></p>");
    }   
}