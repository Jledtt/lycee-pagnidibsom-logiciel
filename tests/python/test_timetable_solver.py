import unittest

from scripts.timetable_solver import solve


def slot(
    key: str,
    day: str,
    period_id: int,
    order: int,
    is_morning: bool | None = None,
) -> dict:
    data = {
        "key": key,
        "day": day,
        "period_id": period_id,
        "period_order": order,
    }
    if is_morning is not None:
        data["is_morning"] = is_morning
    return data


def assignment(
    identifier: int,
    class_id: int,
    teacher_id: int,
    required: int,
    allowed: list[str],
    preferred: list[str] | None = None,
    fixed: list[str] | None = None,
    max_per_day: int = 2,
    synchronization_group: str | None = None,
) -> dict:
    return {
        "id": identifier,
        "class_id": class_id,
        "teacher_id": teacher_id,
        "required_slots": required,
        "max_slots_per_day": max_per_day,
        "allowed_slot_keys": allowed,
        "preferred_slot_keys": preferred or [],
        "fixed_slot_keys": fixed or [],
        "synchronization_group": synchronization_group,
    }


class TimetableSolverTest(unittest.TestCase):
    def payload(self, assignments: list[dict]) -> dict:
        return {
            "days": ["monday", "tuesday"],
            "slots": [
                slot("monday|1", "monday", 1, 1),
                slot("monday|2", "monday", 2, 2),
                slot("monday|3", "monday", 3, 3),
                slot("tuesday|1", "tuesday", 1, 1),
            ],
            "class_ids": sorted({item["class_id"] for item in assignments}),
            "teacher_ids": sorted({item["teacher_id"] for item in assignments}),
            "assignments": assignments,
            "time_limit_seconds": 2,
            "workers": 1,
        }

    def test_same_teacher_cannot_teach_two_classes_at_the_same_time(self) -> None:
        result = solve(self.payload([
            assignment(1, 10, 50, 1, ["monday|1"]),
            assignment(2, 20, 50, 1, ["monday|1"]),
        ]))

        self.assertEqual("INFEASIBLE", result["status"])

    def test_same_class_cannot_receive_two_courses_at_the_same_time(self) -> None:
        result = solve(self.payload([
            assignment(1, 10, 50, 1, ["monday|1"]),
            assignment(2, 10, 60, 1, ["monday|1"]),
        ]))

        self.assertEqual("INFEASIBLE", result["status"])

    def test_preferred_and_fixed_slots_are_respected(self) -> None:
        result = solve(self.payload([
            assignment(
                1,
                10,
                50,
                2,
                ["monday|1", "monday|2", "monday|3"],
                preferred=["monday|2", "monday|3"],
                fixed=["monday|2"],
            ),
        ]))

        self.assertIn(result["status"], ["OPTIMAL", "FEASIBLE"])
        selected = {item["slot_key"] for item in result["assignments"]}
        self.assertEqual({"monday|2", "monday|3"}, selected)
        fixed = next(item for item in result["assignments"] if item["slot_key"] == "monday|2")
        self.assertTrue(fixed["is_fixed"])

    def test_an_available_period_fills_an_avoidable_gap_before_a_later_preference(self) -> None:
        result = solve(self.payload([
            assignment(1, 10, 50, 1, ["monday|1"], fixed=["monday|1"]),
            assignment(
                2,
                10,
                60,
                1,
                ["monday|2", "monday|3"],
                preferred=["monday|3"],
            ),
        ]))

        self.assertIn(result["status"], ["OPTIMAL", "FEASIBLE"])
        selected = {item["slot_key"] for item in result["assignments"]}
        self.assertEqual({"monday|1", "monday|2"}, selected)

    def test_two_hours_of_the_same_subject_are_consecutive(self) -> None:
        result = solve(self.payload([
            assignment(
                1,
                10,
                50,
                2,
                ["monday|1", "monday|2", "monday|3"],
                preferred=["monday|1", "monday|3"],
            ),
        ]))

        self.assertIn(result["status"], ["OPTIMAL", "FEASIBLE"])
        selected = sorted(
            item["period_id"] for item in result["assignments"]
        )
        self.assertEqual(1, selected[1] - selected[0])

    def test_two_hours_cannot_be_split_across_two_days(self) -> None:
        result = solve(self.payload([
            assignment(
                1,
                10,
                50,
                2,
                ["monday|1", "tuesday|1"],
            ),
        ]))

        self.assertEqual("INFEASIBLE", result["status"])

    def test_two_hours_cannot_cross_an_official_break(self) -> None:
        payload = self.payload([
            assignment(
                1,
                10,
                50,
                2,
                ["monday|3", "monday|5"],
            ),
        ])
        payload["slots"].append(slot("monday|5", "monday", 5, 5))

        result = solve(payload)

        self.assertEqual("INFEASIBLE", result["status"])

    def test_three_hours_never_form_one_continuous_block(self) -> None:
        result = solve(self.payload([
            assignment(
                1,
                10,
                50,
                3,
                ["monday|1", "monday|2", "monday|3"],
                max_per_day=2,
            ),
        ]))

        self.assertEqual("INFEASIBLE", result["status"])

    def test_odd_hours_split_into_a_two_hour_block_and_one_isolated_hour(self) -> None:
        result = solve(self.payload([
            assignment(
                1,
                10,
                50,
                3,
                ["monday|1", "monday|2", "monday|3", "tuesday|1"],
                max_per_day=2,
            ),
        ]))

        self.assertIn(result["status"], ["OPTIMAL", "FEASIBLE"])
        by_day: dict[str, list[int]] = {}
        for item in result["assignments"]:
            by_day.setdefault(item["day"], []).append(item["period_id"])
        counts = sorted(len(placed) for placed in by_day.values())
        self.assertEqual([1, 2], counts)

    def test_even_required_hours_never_produce_an_isolated_hour(self) -> None:
        payload = self.payload([
            assignment(
                1,
                10,
                50,
                4,
                ["monday|1", "tuesday|1", "monday|2", "tuesday|2"],
                max_per_day=2,
            ),
        ])
        payload["slots"].append(slot("tuesday|2", "tuesday", 2, 2))

        result = solve(payload)

        self.assertIn(result["status"], ["OPTIMAL", "FEASIBLE"])
        by_day: dict[str, list[int]] = {}
        for item in result["assignments"]:
            by_day.setdefault(item["day"], []).append(item["period_id"])
        for placed in by_day.values():
            self.assertEqual(2, len(placed))

    def test_all_subjects_use_consecutive_blocks_without_alternating(self) -> None:
        allowed = [
            "monday|1",
            "monday|2",
            "monday|3",
            "tuesday|1",
            "tuesday|2",
            "tuesday|3",
        ]
        payload = self.payload([
            assignment(1, 10, 50, 2, allowed),
            assignment(2, 10, 60, 2, allowed),
        ])
        payload["slots"].extend([
            slot("tuesday|2", "tuesday", 2, 2),
            slot("tuesday|3", "tuesday", 3, 3),
        ])

        result = solve(payload)

        self.assertIn(result["status"], ["OPTIMAL", "FEASIBLE"])
        for assignment_id in [1, 2]:
            selected = sorted(
                (
                    item["day"],
                    item["period_id"],
                )
                for item in result["assignments"]
                if item["class_subject_id"] == assignment_id
            )
            self.assertEqual(selected[0][0], selected[1][0])
            self.assertEqual(1, selected[1][1] - selected[0][1])

    def test_previous_solution_is_excluded_from_the_next_generation(self) -> None:
        payload = self.payload([
            assignment(
                1,
                10,
                50,
                2,
                ["monday|1", "monday|2", "monday|3"],
            ),
        ])
        first = solve(payload)
        first_slots = {item["slot_key"] for item in first["assignments"]}
        payload["excluded_solutions"] = [[
            {
                "class_subject_id": item["class_subject_id"],
                "slot_key": item["slot_key"],
            }
            for item in first["assignments"]
        ]]
        payload["variation_seed"] = 42

        second = solve(payload)
        second_slots = {item["slot_key"] for item in second["assignments"]}

        self.assertIn(second["status"], ["OPTIMAL", "FEASIBLE"])
        self.assertNotEqual(first_slots, second_slots)

    def test_generation_is_infeasible_when_the_only_solution_was_already_shown(self) -> None:
        payload = self.payload([
            assignment(1, 10, 50, 2, ["monday|1", "monday|2"]),
        ])
        payload["excluded_solutions"] = [[
            {"class_subject_id": 1, "slot_key": "monday|1"},
            {"class_subject_id": 1, "slot_key": "monday|2"},
        ]]

        result = solve(payload)

        self.assertEqual("INFEASIBLE", result["status"])

    def test_shared_course_uses_the_same_slots_for_both_classes(self) -> None:
        result = solve(self.payload([
            assignment(
                1,
                10,
                50,
                2,
                ["monday|1", "monday|2", "monday|3"],
                synchronization_group="2nde:EPS",
            ),
            assignment(
                2,
                20,
                50,
                2,
                ["monday|1", "monday|2", "monday|3"],
                synchronization_group="2nde:EPS",
            ),
        ]))

        self.assertIn(result["status"], ["OPTIMAL", "FEASIBLE"])
        selected_by_assignment = {
            assignment_id: {
                item["slot_key"]
                for item in result["assignments"]
                if item["class_subject_id"] == assignment_id
            }
            for assignment_id in [1, 2]
        }
        self.assertEqual(selected_by_assignment[1], selected_by_assignment[2])

    def test_morning_slot_is_preferred_over_an_equivalent_afternoon_slot(self) -> None:
        payload = self.payload([
            assignment(1, 10, 50, 1, ["monday|1", "tuesday|1"]),
        ])
        for item in payload["slots"]:
            item["is_morning"] = item["key"] == "tuesday|1"

        result = solve(payload)

        self.assertIn(result["status"], ["OPTIMAL", "FEASIBLE"])
        self.assertEqual("tuesday|1", result["assignments"][0]["slot_key"])

    def test_a_class_cannot_stop_right_before_the_last_morning_period(self) -> None:
        payload = self.payload([
            assignment(1, 10, 50, 1, ["monday|5"]),
        ])
        payload["slots"].append(slot("monday|5", "monday", 5, 5))
        payload["slots"].append(slot("monday|6", "monday", 6, 6))
        payload["closing_morning_slot_pairs"] = [["monday|5", "monday|6"]]

        result = solve(payload)

        self.assertEqual("INFEASIBLE", result["status"])

    def test_a_class_can_use_the_closing_morning_pair_when_both_periods_are_filled(self) -> None:
        payload = self.payload([
            assignment(1, 10, 50, 1, ["monday|5"]),
            assignment(2, 10, 60, 1, ["monday|6"]),
        ])
        payload["slots"].append(slot("monday|5", "monday", 5, 5))
        payload["slots"].append(slot("monday|6", "monday", 6, 6))
        payload["closing_morning_slot_pairs"] = [["monday|5", "monday|6"]]

        result = solve(payload)

        self.assertIn(result["status"], ["OPTIMAL", "FEASIBLE"])


if __name__ == "__main__":
    unittest.main()
