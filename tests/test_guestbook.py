"""HTTP regression checks in a temporary copy; never edits personal guestbook data."""
import html
import json
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import unittest
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
VALID = 'https://www.youtube.com/watch?v=bGpB9q6Oym0'
PAYLOAD = VALID + '\"><img src=x onerror="alert(\'Hello\')">'

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None

class GuestbookTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp = tempfile.TemporaryDirectory()
        cls.root = Path(cls.temp.name)
        shutil.copytree(ROOT / 'guestbook', cls.root / 'guestbook')
        (cls.root / 'storage').mkdir()
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        cls.base = f'http://127.0.0.1:{port}'
        cls.server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(cls.root / 'guestbook')], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        for _ in range(100):
            try:
                urllib.request.urlopen(cls.base, timeout=1).close()
                break
            except (OSError, urllib.error.URLError):
                time.sleep(.05)
        else:
            cls.server.terminate()
            cls.server.wait()
            cls.temp.cleanup()
            raise RuntimeError('Temporary PHP server did not start')

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate()
        cls.server.wait(timeout=5)
        cls.temp.cleanup()

    def setUp(self):
        for path in (self.root / 'storage').glob('*.json'):
            path.unlink()

    def post(self, value, path='/', key='url'):
        data = urllib.parse.urlencode({key: value}).encode()
        try:
            response = urllib.request.build_opener(NoRedirect).open(self.base + path, data, timeout=5)
        except urllib.error.HTTPError as response_error:
            response = response_error
        with response:
            return response.status, response.headers, response.read().decode()

    def test_valid_url_saves_and_get_does_not_duplicate(self):
        status, headers, _ = self.post(VALID)
        self.assertEqual(status, 303)
        self.assertEqual(headers['Location'], '/')
        path = self.root / 'storage/entries.json'
        self.assertEqual(json.loads(path.read_text()), [VALID])
        before = path.read_bytes()
        with urllib.request.urlopen(self.base) as response:
            self.assertIn(VALID, response.read().decode())
        self.assertEqual(path.read_bytes(), before)

    def test_invalid_inputs_leave_storage_unchanged(self):
        path = self.root / 'storage/entries.json'
        path.write_text(json.dumps([VALID]))
        before = path.read_bytes()
        for value in [PAYLOAD, 'javascript:alert(1)', VALID.replace('youtube.com', 'youtube.com.evil.test'), VALID.replace('https:', 'http:'), VALID + '&extra=1', VALID[:-1], '']:
            with self.subTest(value=value):
                status, _, body = self.post(value)
                self.assertEqual(status, 200)
                self.assertTrue('Enter a YouTube URL' in body or 'Please enter a URL.' in body)
                self.assertEqual(path.read_bytes(), before)

    def test_array_input_is_rejected(self):
        _, _, body = self.post('bad', key='url[]')
        self.assertIn('Please enter a URL.', body)
        self.assertFalse((self.root / 'storage/entries.json').exists())

    def test_legacy_payload_is_encoded_in_response(self):
        (self.root / 'storage/entries.json').write_text(json.dumps([PAYLOAD]))
        with urllib.request.urlopen(self.base) as response:
            body = response.read().decode()
        self.assertIn(html.escape(PAYLOAD, quote=True).replace('&#x27;', '&#039;'), body)
        self.assertNotIn('<img src=x onerror=', body)

    def test_vulnerable_demo_uses_separate_storage(self):
        status, headers, _ = self.post(PAYLOAD, '/vulnerable.php')
        self.assertEqual(status, 303)
        self.assertEqual(headers['Location'], '/vulnerable.php')
        self.assertEqual(json.loads((self.root / 'storage/vulnerable-entries.json').read_text()), [PAYLOAD])
        self.assertFalse((self.root / 'storage/entries.json').exists())

if __name__ == '__main__':
    unittest.main(verbosity=2)
