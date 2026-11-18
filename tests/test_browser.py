"""Browser regression checks against a disposable guestbook copy."""
import json
import unittest
from playwright.sync_api import sync_playwright
import test_guestbook as http_tests
from test_guestbook import VALID, PAYLOAD

class BrowserTests(unittest.TestCase):
    setUpClass = classmethod(http_tests.GuestbookTests.setUpClass.__func__)
    tearDownClass = classmethod(http_tests.GuestbookTests.tearDownClass.__func__)

    def setUp(self):
        http_tests.GuestbookTests.setUp(self)
        self.playwright = sync_playwright().start()
        self.browser = self.playwright.chromium.launch()
        self.context = self.browser.new_context()
        # External thumbnails are irrelevant to execution safety; keep tests offline.
        self.context.route('https://i.ytimg.com/**', lambda route: route.abort())
        self.page = self.context.new_page()
        self.dialogs = []
        def dismiss(dialog):
            self.dialogs.append(dialog.message)
            dialog.dismiss()
        self.page.on('dialog', dismiss)
        self.errors = []
        self.page.on('pageerror', lambda error: self.errors.append(str(error)))

    def tearDown(self):
        self.browser.close()
        self.playwright.stop()

    def test_vulnerable_payload_executes_on_fresh_visit(self):
        (self.root / 'storage/vulnerable-entries.json').write_text(json.dumps([PAYLOAD]))
        self.page.goto(self.base + '/vulnerable.php', wait_until='networkidle')
        self.assertIn('Hello', self.dialogs)
        self.dialogs.clear()
        self.page.reload(wait_until='networkidle')
        self.assertIn('Hello', self.dialogs)
        self.assertEqual(self.errors, [])

    def test_fixed_page_rejects_legacy_payload_and_keeps_valid_link(self):
        (self.root / 'storage/entries.json').write_text(json.dumps([VALID, PAYLOAD]))
        self.page.goto(self.base, wait_until='networkidle')
        self.assertEqual(self.dialogs, [])
        self.assertEqual(self.errors, [])
        entries = self.page.locator('.video')
        self.assertEqual(entries.nth(0).inner_text(), 'Invalid YouTube URL — entry not rendered.')
        self.assertEqual(entries.nth(0).locator('img, iframe, script').count(), 0)
        self.assertEqual(entries.nth(1).locator('a').get_attribute('href'), VALID)
        self.assertEqual(entries.nth(1).locator('img').get_attribute('src'), 'https://i.ytimg.com/vi/bGpB9q6Oym0/hqdefault.jpg')
        self.assertEqual(self.page.locator('[onerror]').count(), 0)

    def test_fixed_form_rejects_payload_without_storage_change(self):
        path = self.root / 'storage/entries.json'
        path.write_text(json.dumps([VALID]))
        before = path.read_bytes()
        self.page.goto(self.base)
        self.page.locator('#url').fill(PAYLOAD)
        self.page.get_by_role('button', name='Add video').click()
        self.page.wait_for_load_state('networkidle')
        self.assertIn('Enter a YouTube URL', self.page.locator('body').inner_text())
        self.assertEqual(path.read_bytes(), before)
        self.assertEqual(self.dialogs, [])
        self.assertEqual(self.errors, [])

if __name__ == '__main__':
    unittest.main(verbosity=2)
