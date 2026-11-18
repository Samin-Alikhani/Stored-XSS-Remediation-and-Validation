# Security Review and Threat Model

## Finding

**Finding XSS-001 — Stored input reaches an unsafe DOM sink (CWE-79).** A submitted URL is stored and later concatenated into `innerHTML`. A harmless image-error payload demonstrates script execution when the stored entry is rendered. The fixed implementation rejects unsupported submissions and replaces HTML-string construction with direct DOM creation.

**Status:** fixed in `index.php` and retained in `vulnerable.php` for local comparison.

## Classification

**OWASP Top 10:2025 A05 — Injection; CWE-79 (Cross-site Scripting).** [OWASP A05 reference](https://top10.owasp.org/2025/A05_2025-Injection/)

## Assets, actors, and boundaries

| Element | Scope |
| --- | --- |
| Assets | Integrity of saved entries, rendered links, and JavaScript executing under the guestbook origin. |
| Attacker capability | Submit arbitrary form data; browser-required fields are not treated as server controls. |
| Victim action | Visit a page that renders the saved entry. |
| Boundaries | Untrusted POST → PHP → filesystem JSON → encoded HTML → DOM value → renderer. |
| Trusted components | Application code and local filesystem. Stored content remains untrusted. |
| Exclusions | No login/session system, payments, actual customer records, multiuser deployment, or university server access in this reproduction. |

The critical boundary is the later `innerHTML` assignment, which turns an attribute value back into parsed HTML.

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

PHP rejects extra parameters on new submissions. The browser can render some older URLs with extra parameters, but rebuilds their destinations from validated IDs. Aligning these policies would simplify maintenance.

## Impact and prioritization

The demonstrated impact is JavaScript execution under the guestbook origin on a later visit. In an authenticated application, this could allow page manipulation, actions as the user, or access to data available to JavaScript. Those are potential consequences, not results demonstrated in this project.

For a shared application this path warrants prompt remediation because one stored submission can affect subsequent visitors without a video click. The localhost scope limits actual exposure in this project. No numeric CVSS score is assigned because deployment, privileges, user population, and accessible data are not established.

## Control decisions and residual risk

| Decision | Reason | Remaining limit |
| --- | --- | --- |
| Fix the sink as well as validation | Validation can change; old data already exists. | Other future output contexts require their own review. |
| Permit only a narrow watch URL | Makes the accepted contract explicit and testable. | Real YouTube short/mobile/share URLs are not fully supported. |
| Keep unsafe comparison separate | Preserves a reproducible teaching example. | Never deploy the vulnerable page publicly. |
| Use disposable test storage | Prevents regression runs from changing personal demo entries. | JSON remains a single-user storage design. |
| Block external thumbnails during tests | Keeps security assertions independent of external availability. | Browser tests verify URLs/DOM, not live image downloads. |

Open items: CSRF defenses and abuse limits; authentication if shared; transactional storage for concurrent updates; corrupted-JSON and filesystem error handling; broader browser/payload coverage; dependency review if runtime packages are introduced. See the [validation guide](validation.md#evidence-provenance-and-limitations) for test coverage and evidence limits.

## References

- [OWASP Cross Site Scripting Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html): output contexts and safe sinks.
- [OWASP DOM based XSS Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/DOM_based_XSS_Prevention_Cheat_Sheet.html): safe DOM population.
- [CWE-79](https://cwe.mitre.org/data/definitions/79.html): improper neutralization during web-page generation.
