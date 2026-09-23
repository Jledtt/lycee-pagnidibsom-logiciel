#!/usr/bin/env python3
"""Solve a weekly school timetable from a JSON payload on stdin."""

import json
import math
import random
import sys

from ortools.sat.python import cp_model


def solve(payload: dict) -> dict:
    model = cp_model.CpModel()
    slots = payload["slots"]
    assignments = payload["assignments"]
    variables = {}
    class_occupancies = {}

    for assignment in assignments:
        assignment_id = assignment["id"]
        allowed = set(assignment["allowed_slot_keys"])
        fixed = set(assignment.get("fixed_slot_keys", []))

        for slot in slots:
            key = slot["key"]
            variable = model.new_bool_var(f"a{assignment_id}_{key}")
            variables[(assignment_id, key)] = variable

            if key not in allowed:
                model.add(variable == 0)
            if key in fixed:
                model.add(variable == 1)

        model.add(
            sum(variables[(assignment_id, slot["key"])] for slot in slots)
            == assignment["required_slots"]
        )

        used_days = []
        single_hour_days = []
        max_slots_per_day = max(1, int(assignment.get("max_slots_per_day", 2)))
        for day in payload["days"]:
            day_slots = sorted(
                (slot for slot in slots if slot["day"] == day),
                key=lambda slot: int(slot["period_order"]),
            )
            daily = [variables[(assignment_id, slot["key"])] for slot in day_slots]
            daily_sum = sum(daily)
            model.add(daily_sum <= max_slots_per_day)

            if max_slots_per_day >= 2:
                # A day is either unused, a full 2h block, or (for at most one day per
                # week, to absorb an odd weekly volume) a single isolated hour. Never a
                # 3h-or-more block.
                used_two = model.new_bool_var(f"a{assignment_id}_{day}_two")
                used_one = model.new_bool_var(f"a{assignment_id}_{day}_one")
                model.add(used_one + used_two <= 1)
                model.add(daily_sum == used_one + 2 * used_two)
                day_used = model.new_bool_var(f"a{assignment_id}_{day}_used")
                model.add(day_used == used_one + used_two)
                single_hour_days.append(used_one)
            else:
                day_used = model.new_bool_var(f"a{assignment_id}_{day}_used")
                model.add(day_used == daily_sum)
            used_days.append(day_used)

            starts = []
            previous_variable = None
            previous_order = None
            for slot in day_slots:
                variable = variables[(assignment_id, slot["key"])]
                order = int(slot["period_order"])
                start = model.new_bool_var(f"a{assignment_id}_{day}_{order}_start")

                if previous_variable is None or order != previous_order + 1:
                    model.add(start == variable)
                else:
                    model.add(start <= variable)
                    model.add(start + previous_variable <= 1)
                    model.add(start >= variable - previous_variable)

                starts.append(start)
                previous_variable = variable
                previous_order = order

            model.add(sum(starts) <= 1)

        if single_hour_days:
            model.add(sum(single_hour_days) <= 1)

        minimum_teaching_days = math.ceil(
            int(assignment["required_slots"]) / max_slots_per_day
        )
        model.add(sum(used_days) <= minimum_teaching_days)

    synchronization_groups = {}
    for assignment in assignments:
        group = assignment.get("synchronization_group")
        if group:
            synchronization_groups.setdefault(group, []).append(assignment)

    for group_assignments in synchronization_groups.values():
        if len(group_assignments) < 2:
            continue
        reference_id = group_assignments[0]["id"]
        for assignment in group_assignments[1:]:
            for slot in slots:
                key = slot["key"]
                model.add(
                    variables[(reference_id, key)]
                    == variables[(assignment["id"], key)]
                )

    expected_solution_size = sum(int(item["required_slots"]) for item in assignments)
    for excluded_solution in payload.get("excluded_solutions", []):
        excluded_variables = []
        seen_pairs = set()
        for item in excluded_solution:
            pair = (int(item.get("class_subject_id", 0)), str(item.get("slot_key", "")))
            if pair in variables and pair not in seen_pairs:
                excluded_variables.append(variables[pair])
                seen_pairs.add(pair)
        if len(excluded_variables) == expected_solution_size:
            model.add(sum(excluded_variables) <= expected_solution_size - 1)

    for slot in slots:
        key = slot["key"]
        for class_id in payload["class_ids"]:
            class_variables = [
                variables[(assignment["id"], key)]
                for assignment in assignments
                if assignment["class_id"] == class_id
            ]
            if class_variables:
                model.add(sum(class_variables) <= 1)
                occupied = model.new_bool_var(f"class_{class_id}_{key}_occupied")
                model.add(occupied == sum(class_variables))
                class_occupancies[(class_id, key)] = occupied

        for teacher_id in payload["teacher_ids"]:
            teacher_variables = []
            seen_occupancies = set()
            for assignment in assignments:
                if assignment["teacher_id"] != teacher_id:
                    continue
                group = assignment.get("synchronization_group")
                occupancy = ("shared", group) if group else ("assignment", assignment["id"])
                if occupancy in seen_occupancies:
                    continue
                seen_occupancies.add(occupancy)
                teacher_variables.append(variables[(assignment["id"], key)])
            if teacher_variables:
                model.add(sum(teacher_variables) <= 1)

    # A class must never stop its morning right before the last pre-noon period
    # (e.g. occupied at 10h10-11h05 but free at 11h05-12h00): if it starts that
    # closing pair, it must complete it.
    for first_key, second_key in payload.get("closing_morning_slot_pairs", []):
        for class_id in payload["class_ids"]:
            first_occupied = class_occupancies.get((class_id, first_key))
            second_occupied = class_occupancies.get((class_id, second_key))
            if first_occupied is not None and second_occupied is not None:
                model.add(first_occupied <= second_occupied)

    objective_terms = []
    randomizer = random.Random(int(payload.get("variation_seed", 0)))
    for assignment in assignments:
        preferred = set(assignment.get("preferred_slot_keys", []))
        for slot in slots:
            variable = variables[(assignment["id"], slot["key"])]
            preference_score = 30 if slot["key"] in preferred else 10
            early_score = max(0, 8 - int(slot["period_order"]))
            morning_score = 40 if slot.get("is_morning", False) else 0
            variation_score = randomizer.randint(0, 4)
            objective_terms.append(
                (preference_score + early_score + morning_score + variation_score)
                * variable
            )

    # A free teaching period between two courses is much more disruptive than
    # using a merely available (rather than preferred) period. Official breaks
    # are absent from payload["slots"], so they are never treated as gaps.
    gap_variables = []
    for class_id in payload["class_ids"]:
        for day in payload["days"]:
            day_slots = sorted(
                (slot for slot in slots if slot["day"] == day),
                key=lambda slot: int(slot["period_order"]),
            )
            for index in range(1, len(day_slots) - 1):
                current = class_occupancies[(class_id, day_slots[index]["key"])]
                before = model.new_bool_var(f"class_{class_id}_{day}_{index}_before")
                after = model.new_bool_var(f"class_{class_id}_{day}_{index}_after")
                gap = model.new_bool_var(f"class_{class_id}_{day}_{index}_gap")

                model.add_max_equality(
                    before,
                    [class_occupancies[(class_id, slot["key"])] for slot in day_slots[:index]],
                )
                model.add_max_equality(
                    after,
                    [class_occupancies[(class_id, slot["key"])] for slot in day_slots[index + 1 :]],
                )
                model.add(gap <= before)
                model.add(gap <= after)
                model.add(gap + current <= 1)
                model.add(gap >= before + after - current - 1)
                gap_variables.append(gap)

    # One avoided gap must outweigh every possible preference gain in the run.
    gap_penalty = (len(assignments) * len(slots) * 50) + 1
    objective_terms.extend(-gap_penalty * gap for gap in gap_variables)

    model.maximize(sum(objective_terms))

    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = float(payload.get("time_limit_seconds", 12))
    solver.parameters.num_search_workers = int(payload.get("workers", 4))
    solver.parameters.random_seed = int(payload.get("variation_seed", 0))
    status = solver.solve(model)
    status_name = solver.status_name(status)

    result = {
        "status": status_name,
        "objective": solver.objective_value if status in (cp_model.OPTIMAL, cp_model.FEASIBLE) else None,
        "wall_time_seconds": solver.wall_time,
        "assignments": [],
    }

    if status not in (cp_model.OPTIMAL, cp_model.FEASIBLE):
        return result

    by_id = {assignment["id"]: assignment for assignment in assignments}
    slot_by_key = {slot["key"]: slot for slot in slots}

    for (assignment_id, slot_key), variable in variables.items():
        if solver.value(variable) != 1:
            continue

        assignment = by_id[assignment_id]
        slot = slot_by_key[slot_key]
        result["assignments"].append(
            {
                "class_subject_id": assignment_id,
                "class_id": assignment["class_id"],
                "teacher_id": assignment["teacher_id"],
                "day": slot["day"],
                "period_id": slot["period_id"],
                "slot_key": slot_key,
                "is_fixed": slot_key in set(assignment.get("fixed_slot_keys", [])),
            }
        )

    return result


def main() -> None:
    try:
        payload = json.load(sys.stdin)
        print(json.dumps(solve(payload), ensure_ascii=False))
    except Exception as error:  # pragma: no cover - surfaced to Laravel
        print(json.dumps({"status": "ERROR", "error": str(error)}, ensure_ascii=False))
        raise


if __name__ == "__main__":
    main()
