# Validation and Reproduction

## Test layers

| Layer | Command | What it proves | Limit |
| --- | --- | --- | --- |
| PHP syntax | `php -l guestbook/index.php` and `php -l guestbook/vulnerable.php` | Files parse. | Not a security scanner. |
| HTTP regression | `python3 tests/test_guestbook.py` | Valid persistence, rejected-input storage integrity, array input handling, initial encoding, separate demo data. | Does not execute JavaScript. |
| Chromium regression | `python tests/test_browser.py` | Stored payload executes in vulnerable page on visit/reload; fixed legacy rendering and form rejection remain safe for tested inputs. | One browser and a finite payload set. |
| Manual browser checks | Development record | Historical thumbnail, fresh-visit, and hash observations. | Reported observations; screenshots alone do not prove persistence. |

Five HTTP test methods and three browser test methods are provided. Individual invalid inputs are subcases, not separate test counts. Both suites copy application files into temporary directories, create their own storage and localhost server, and clean up afterward. They do not operate on an already-running personal guestbook.

## Setup

PHP 8.1+ and Python 3.10+ are prerequisites. Run HTTP tests without extra packages:

```bash
python3 tests/test_guestbook.py
```

For browser tests:

```bash
python3 -m venv .venv
source .venv/bin/activate
python -m pip install -r requirements-dev.txt
python -m playwright install chromium
python tests/test_browser.py
```

Browser tests block external YouTube thumbnail requests. They check DOM destinations and execution behavior rather than availability of videos or images. An expected `Hello` dialog is dismissed automatically by the test and asserted as evidence of the vulnerable behavior. Fixed-page tests assert no dialog, no JavaScript error, rejection text, and absence of injected event-handler elements.

## Reproduce manually

Use a disposable clone and start `php -S 127.0.0.1:8000 -t guestbook` from its root.

1. Open `/vulnerable.php` and submit the harmless payload below.
2. Dismiss `Hello`, revisit `/vulnerable.php` without submitting, and observe execution from saved input.
3. Open `/` and submit the same input. Expect a validation message and no new saved entry.
4. Submit the valid URL alone to `/`. Expect a thumbnail that persists on another visit.
5. Use the browser regression suite for seeded legacy-data testing; it avoids editing real demo storage.

```text
https://www.youtube.com/watch?v=bGpB9q6Oym0"><img src=x onerror="alert('Hello')">
```

The fixed renderer's protection is tested independently of new-submission validation by writing a legacy payload into temporary fixed-page storage before the browser loads it. This prevents a passing input-rejection check from hiding a regression in rendering.

## Results and evidence boundaries

Completion review on November 17, 2026: all five HTTP checks and all three Chromium browser checks passed locally against the unchanged PHP application. Both PHP files also passed syntax validation. Existing screenshots reflect the development stages described in the [development record](local-guestbook.md#local-screenshot-evidence).

These targeted HTTP and browser tests demonstrate dynamic security regression testing. They are not a general DAST scan. No SAST or software-composition-analysis result is claimed. The application has no third-party runtime packages; the browser automation library is a development dependency and still requires maintenance.

## Continuous integration template

A [GitHub Actions template](ci/regression.yml) contains the same syntax, HTTP, and Chromium commands. An initial GitHub attempt failed at workflow startup before creating any jobs; the available API exposed no diagnostic annotations. It is retained as an inactive template rather than presenting unverified CI as a passing control. Local execution of the tests passed.

To enable it after resolving the startup issue, copy the template into `.github/workflows/regression.yml` and inspect the resulting Actions run. No CI badge or hosted-test success is claimed.
