import json
import unittest
from unittest.mock import patch

from ops import device_bridge as bridge


class BridgeTests(unittest.TestCase):
    def setUp(self):
        self.database = bridge.open_spool(":memory:")

    def tearDown(self):
        self.database.close()

    def event(self, number):
        return {"employeeNoString": "7", "time": "2026-10-25T09:00:00+03:00", "serialNo": number}

    def test_pagination_overlap_deduplicates_and_checkpoints_only_after_all_pages(self):
        with patch.object(bridge, "post_json", side_effect=[
            {"AcsEvent": {"InfoList": [self.event(1)], "totalMatches": 2}},
            {"AcsEvent": {"InfoList": [self.event(2)], "totalMatches": 2}},
        ]) as post:
            bridge.collect(self.database, object(), "http://terminal", "2026-10-25T00:00:00+03:00")
        self.assertEqual(post.call_args_list[1].args[2]["AcsEventCond"]["searchResultPosition"], 1)
        self.assertEqual(self.database.execute("SELECT COUNT(*) FROM events").fetchone()[0], 2)
        checkpoint = self.database.execute("SELECT value FROM state").fetchone()[0]
        with patch.object(bridge, "post_json", side_effect=[
            {"AcsEvent": {"InfoList": [self.event(1)], "totalMatches": 2}}, OSError("offline"),
        ]):
            with self.assertRaises(OSError):
                bridge.collect(self.database, object(), "http://terminal", "2026-10-25T00:00:00+03:00")
        self.assertEqual(self.database.execute("SELECT value FROM state").fetchone()[0], checkpoint)
        self.assertEqual(self.database.execute("SELECT COUNT(*) FROM events").fetchone()[0], 2)

    def test_shadow_and_failed_delivery_retain_events_then_success_marks_sent(self):
        self.database.execute("INSERT INTO events VALUES ('event', '2026-10-25', ?, 0)", (json.dumps(self.event(1)),))
        self.database.commit()
        for response in ({"data": {"applied": False}}, OSError("offline")):
            with patch.object(bridge, "post_json", side_effect=[response]):
                try:
                    bridge.deliver(self.database, "https://erp/bridge", "private-token")
                except OSError:
                    pass
            self.assertEqual(self.database.execute("SELECT sent FROM events").fetchone()[0], 0)
        with patch.object(bridge, "post_json", return_value={"data": {"applied": True}}):
            bridge.deliver(self.database, "https://erp/bridge", "private-token")
        self.assertEqual(self.database.execute("SELECT sent FROM events").fetchone()[0], 1)

    def test_device_error_does_not_advance_checkpoint(self):
        with patch.object(bridge, "post_json", return_value={"ResponseStatus": {"statusCode": 4}}):
            with self.assertRaises(ValueError):
                bridge.collect(self.database, object(), "http://terminal", "2026-10-25T00:00:00+03:00")
        self.assertIsNone(self.database.execute("SELECT value FROM state").fetchone())


if __name__ == "__main__":
    unittest.main()
