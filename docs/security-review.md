# Security Review and Threat Model

## Executive finding

**Finding XSS-001 — Stored input reaches an unsafe DOM sink (CWE-79).** A submitted URL is stored and later concatenated into `innerHTML`. A harmless image-error payload demonstrates script execution when the stored entry is rendered. The fixed implementation rejects unsupported submissions and replaces HTML-string construction with direct DOM creation.

**Disposition:** remediated for the exercised path in the fixed page; intentionally retained in `vulnerable.php` for localhost comparison. The regression suite is finite, not proof of absence of all XSS.

## Assets, actors, and boundaries

| Element | Scope |
| --- | --- |
| Assets | Integrity of saved entries, rendered links, and JavaScript executing under the guestbook origin. |
| Attacker capability | Submit arbitrary form data; browser-required fields are not treated as server controls. |
| Victim action | Visit a page that renders the saved entry. |
| Boundaries | Untrusted POST → PHP → filesystem JSON → encoded HTML → DOM value → renderer. |
| Trusted components | Application code and local filesystem. Stored content remains untrusted. |
| Exclusions | No login/session system, payments, actual customer records, multiuser deployment, or university server access in this reproduction. |

The key boundary is the second parser: a string safely carried in an encoded attribute becomes dangerous when subsequently interpreted as HTML.

## Secure code review record

| Review area | Finding or decision | Verification |
| --- | --- | --- |
| Input shape | `is_string()` handles array-shaped `url[]` submissions without calling `trim()` on an array. | HTTP test. |
| Input policy | Anchored PHP pattern accepts a narrow HTTPS YouTube watch-URL format and checks before append/write. | Valid input accepted; multiple invalid forms leave storage bytes unchanged. |
| Initial output | `htmlspecialchars()` encodes attribute and text values. | HTTP response check with seeded legacy payload. |
| DOM sink | Vulnerable page uses concatenation and `innerHTML`; fixed page uses DOM methods. | Chromium comparison: vulnerable alert vs fixed rejection text and no event-handler elements. |
| Destination attributes | Fixed renderer derives canonical link and image URLs from a validated video ID. | Browser assertion checks exact destinations. |
| Existing data | Renderer treats existing JSON entries as untrusted, even after server validation is added. | Legacy-payload browser test. |
| Repeat submissions | POST redirects with 303; GET does not append. Duplicate intentional submissions remain allowed. | HTTP refresh/persistence check. |
| Data separation | Fixed and vulnerable versions use different ignored files outside the document root. | Storage-isolation test. |

The PHP and browser parsers are not identical: the browser canonicalizes a parsed URL and may render some old values with extra parameters, whereas PHP rejects extra parameters on new submissions. Safe construction prevents those old values from injecting markup, but unifying policy is a future maintainability improvement.

## Impact and prioritization

Observed impact is JavaScript execution under the local guestbook origin, including on a later visit. In a hypothetical authenticated application, origin-level script execution could alter page content, initiate actions as the user, or read data accessible to JavaScript. Those outcomes were not implemented or demonstrated here. An alert does not prove credential theft, payment compromise, or account takeover.

For a shared application this path warrants prompt remediation because one stored submission can affect subsequent visitors without a video click. The localhost scope limits actual exposure in this project. No numeric CVSS score is assigned because deployment, privileges, user population, and accessible data are not established.

## Control decisions and residual risk

| Decision | Reason | Remaining limit |
| --- | --- | --- |
| Fix the sink as well as validation | Validation can change; old data already exists. | Other future output contexts require their own review. |
| Permit only a narrow watch URL | Makes the accepted contract explicit and testable. | Real YouTube short/mobile/share URLs are not fully supported. |
| Keep unsafe comparison separate | Preserves a reproducible teaching example. | Never deploy the vulnerable page publicly. |
| Use disposable test storage | Prevents regression runs from changing personal demo entries. | JSON remains a single-user storage design. |
| Block external thumbnails during tests | Keeps security assertions independent of external availability. | Browser tests verify URLs/DOM, not live image downloads. |

Open items: CSRF defenses and abuse limits; authentication if shared; transactional storage for concurrent updates; corrupted-JSON and filesystem error handling; broader browser/payload coverage; dependency review if runtime packages are introduced. These are explicit scope limits, not completed mitigations.

## References

- [OWASP Cross Site Scripting Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html): output contexts and safe sinks.
- [OWASP DOM based XSS Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/DOM_based_XSS_Prevention_Cheat_Sheet.html): safe DOM population.
- [CWE-79](https://cwe.mitre.org/data/definitions/79.html): improper neutralization during web-page generation.
