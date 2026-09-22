<?php

$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$assignment = $input['assignments'][0];
$allowed = array_flip($assignment['allowed_slot_keys']);
$slots = array_values(array_filter(
    $input['slots'],
    fn (array $slot): bool => isset($allowed[$slot['key']]),
));
usort($slots, fn (array $left, array $right): int => [
    $left['day'],
    $left['period_order'],
] <=> [
    $right['day'],
    $right['period_order'],
]);

$first = $slots[0];
$second = null;
foreach ($slots as $slot) {
    if ($slot['day'] === $first['day']
        && (int) $slot['period_order'] > (int) $first['period_order'] + 1) {
        $second = $slot;

        break;
    }
}

if (! $second) {
    echo json_encode(['status' => 'INFEASIBLE', 'assignments' => []], JSON_THROW_ON_ERROR);
    exit(0);
}

$result = array_map(
    fn (array $slot): array => [
        'class_subject_id' => $assignment['id'],
        'class_id' => $assignment['class_id'],
        'teacher_id' => $assignment['teacher_id'],
        'day' => $slot['day'],
        'period_id' => $slot['period_id'],
        'slot_key' => $slot['key'],
        'is_fixed' => false,
    ],
    [$first, $second],
);

echo json_encode([
    'status' => 'FEASIBLE',
    'objective' => 1,
    'wall_time_seconds' => 0.01,
    'assignments' => $result,
], JSON_THROW_ON_ERROR);
