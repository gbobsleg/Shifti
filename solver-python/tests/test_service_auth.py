# -*- coding: utf-8 -*-
import unittest

from service_auth import is_authorized


class IsAuthorizedTests(unittest.TestCase):
    def test_bon_jeton(self):
        self.assertTrue(is_authorized("/api/v1/solve-schedule", "secret", "secret"))

    def test_mauvais_jeton(self):
        self.assertFalse(is_authorized("/api/v1/solve-schedule", "autre", "secret"))

    def test_jeton_absent(self):
        self.assertFalse(is_authorized("/api/v1/solve-schedule", "", "secret"))

    def test_health_reste_public(self):
        self.assertTrue(is_authorized("/health", "", "secret"))

    def test_sans_jeton_attendu_tout_passe(self):
        self.assertTrue(is_authorized("/api/v1/solve-schedule", "", ""))


if __name__ == "__main__":
    unittest.main()
