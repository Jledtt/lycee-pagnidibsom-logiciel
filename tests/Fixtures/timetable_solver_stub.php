<?php

$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$result = [];
$usedClasses = [];
$usedTeachers = [];

foreach ($input['assignments'] as $assignment) {
    $selected = array_values(array_unique($assignment['fixed_slot_keys']));
    $dailyCounts = [];
    $allowed = array_flip($assignment['allowed_slot_keys']);
    foreach ($selected as $slotKey) {
        [$day] = explode('|', $slotKey);
        $dailyCounts[$day] = ($dailyCounts[$day] ?? 0) + 1;
        $usedClasses[$assignment['class_id'].'|'.$slotKey] = true;
        $usedTeachers[$assignment['teacher_id'].'|'.$slotKey] = true;
    }

    $remaining = $assignment['required_slots'] - count($selected);
    $maxPerDay = max(1, (int) ($assignment['max_slots_per_day'] ?? 2));
    while ($remaining > 0) {
        $blockSize = min($maxPerDay, $remaining);
        $block = null;

        foreach ($input['days'] as $day) {
            if (($dailyCounts[$day] ?? 0) > 0) {
                continue;
            }

            $daySlots = array_values(array_filter(
                $input['slots'],
                function (array $slot) use ($allowed, $assignment, $day, $usedClasses, $usedTeachers): bool {
                    $slotKey = $slot['key'];

                    return $slot['day'] === $day
                        && isset($allowed[$slotKey])
                        && ! isset($usedClasses[$assignment['class_id'].'|'.$slotKey])
                        && ! isset($usedTeachers[$assignment['teacher_id'].'|'.$slotKey]);
                },
            ));
            usort(
                $daySlots,
                fn (array $left, array $right): int => $left['period_order'] <=> $right['period_order'],
            );

            for ($index = 0; $index <= count($daySlots) - $blockSize; $index++) {
                $candidate = array_slice($daySlots, $index, $blockSize);
                $orders = array_column($candidate, 'period_order');
                $isConsecutive = true;
                for ($orderIndex = 1; $orderIndex < count($orders); $orderIndex++) {
                    if ((int) $orders[$orderIndex] !== (int) $orders[$orderIndex - 1] + 1) {
                        $isConsecutive = false;

                        break;
                    }
                }
                if ($isConsecutive) {
                    $block = $candidate;

                    break 2;
                }
            }
        }

        if ($block === null) {
            break;
        }

        foreach ($block as $slot) {
            $slotKey = $slot['key'];
            $selected[] = $slotKey;
            $dailyCounts[$slot['day']] = ($dailyCounts[$slot['day']] ?? 0) + 1;
            $usedClasses[$assignment['class_id'].'|'.$slotKey] = true;
            $usedTeachers[$assignment['teacher_id'].'|'.$slotKey] = true;
        }
        $remaining -= count($block);
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
