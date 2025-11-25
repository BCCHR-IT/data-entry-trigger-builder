<?php
/*

$data_entry_trigger_builder = new BCCHR\DataEntryTriggerBuilder\DataEntryTriggerBuilder();
$fields = $data_entry_trigger_builder->retrieveProjectMetadata($_POST["pid"]);
*/
// $det_builder = new BCCHR\DETBuilder\DETBuilder();
// $fields = $det_builder->retrieveProjectMetadata($_POST["pid"]);

// print json_encode($fields);

use BCCHR\DETBuilder\DETBuilder;
use ExternalModules\AbstractExternalModule;

$det_builder = new DETBuilder();
$raw = $det_builder->retrieveProjectMetadata($_POST['pid'] ?? null);

if (!is_array($raw)) {
    $raw = [
        'fields'         => [],
        'events'         => [],
        'isLongitudinal' => false,
    ];
}

$fields         = isset($raw['fields']) && is_array($raw['fields']) ? $raw['fields'] : [];
$events         = isset($raw['events']) && is_array($raw['events']) ? $raw['events'] : [];
$isLongitudinal = !empty($raw['isLongitudinal']);

$sanitized = [
    'fields'         => [],
    'events'         => [],
    'isLongitudinal' => (bool) $isLongitudinal,
];

foreach ($fields as $field) {
    $sanitized['fields'][] = $module->escape((string) $field);
}

foreach ($events as $event) {
    $sanitized['events'][] = $module->escape((string) $event);
}

header('Content-Type: application/json');

echo json_encode($sanitized);
