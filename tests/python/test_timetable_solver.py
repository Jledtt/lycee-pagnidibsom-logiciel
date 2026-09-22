import unittest

from scripts.timetable_solver import solve


def slot(key: str, day: str, period_id: int, order: int) -> dict:
    return {
        "key": key,
        "day": day,
        "period_id": period_id,
        "period_order": order,
    }


def assignment(
    identifier: int,
    class_id: int,
    teacher_id: int,
    required: int,
    allowed: list[str],
    preferred: list[str] | None = None,
    fixed: list[str] | None = None,
    max_per_day: int = 2,
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
                ["monday|1", "monday|2", "tuesday|1"],
                preferred=["monday|2"],
                fixed=["tuesday|1"],
                max_per_day=1,
            ),
        ]))

        self.assertIn(result["status"], ["OPTIMAL", "FEASIBLE"])
        selected = {item["slot_key"] for item in result["assignments"]}
        self.assertEqual({"monday|2", "tuesday|1"}, selected)
        fixed = next(item for item in result["assignments"] if item["slot_key"] == "tuesday|1")
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


if __name__ == "__main__":
    unittest.main()
