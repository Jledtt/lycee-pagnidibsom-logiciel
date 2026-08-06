<?php

$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$result = [];
$usedClasses = [];
$usedTeachers = [];

foreach ($input['assignments'] as $assignment) {
    $selected = [];
    $dailyCounts = [];
    foreach (array_unique([...$assignment['fixed_slot_keys'], ...$assignment['allowed_slot_keys']]) as $slotKey) {
        [$day] = explode('|', $slotKey);
        $classKey = $assignment['class_id'].'|'.$slotKey;
        $teacherKey = $assignment['teacher_id'].'|'.$slotKey;
        if (isset($usedClasses[$classKey])
            || isset($usedTeachers[$teacherKey])
            || ($dailyCounts[$day] ?? 0) >= ($assignment['max_slots_per_day'] ?? 2)) {
            continue;
        }
        $selected[] = $slotKey;
        $dailyCounts[$day] = ($dailyCounts[$day] ?? 0) + 1;
        $usedClasses[$classKey] = true;
        $usedTeachers[$teacherKey] = true;
        if (count($selected) === $assignment['required_slots']) {
            break;
        }
    }

    if (count($selected) !== $assignment['required_slots']) {
        echo json_encode(['status' => 'INFEASIBLE', 'assignments' => []]);
        exit(0);
    }

    foreach ($selected as $slotKey) {
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
}

echo json_encode([
    'status' => 'FEASIBLE',
    'objective' => 1,
    'wall_time_seconds' => 0.01,
    'assignments' => $result,
], JSON_THROW_ON_ERROR);
