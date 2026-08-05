<?php

$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$result = [];

foreach ($input['assignments'] as $assignment) {
    $slotKey = $assignment['allowed_slot_keys'][0];
    [$day, $periodId] = explode('|', $slotKey);
    $result[] = [
        'class_subject_id' => $assignment['id'],
        'class_id' => $assignment['class_id'],
        'teacher_id' => $assignment['teacher_id'],
        'day' => $day,
        'period_id' => (int) $periodId,
        'slot_key' => $slotKey,
        'is_fixed' => in_array($slotKey, $assignment['fixed_slot_keys'], true),
    ];
}

echo json_encode([
    'status' => 'FEASIBLE',
    'objective' => 1,
    'wall_time_seconds' => 0.01,
    'assignments' => $result,
], JSON_THROW_ON_ERROR);
