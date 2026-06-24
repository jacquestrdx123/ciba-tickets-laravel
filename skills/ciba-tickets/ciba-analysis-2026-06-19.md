# CibaRebuildSystem — Ticket & Codebase Analysis
*Generated 2026-06-19 | Evaluation only — no code changes made*

---

## 1. Code Issues Mapped to Open Tickets

These were found by cross-referencing ticket descriptions against the actual codebase. Each item below includes the specific file, the bug, the fix scope, and the linked ticket.

---

### 🔴 Ticket 958 — ECMS Letter of Good Standing Download Not Working
**ITSM-202606-0044 | No branch**

- **File:** `resources/views/pdfs/good-standing.blade.php` lines 71–72
- **Bug:** The Blade template loops over member products and calls `$designation->subscribable->name` with no null guard. If any product row has a null `subscribable` (orphaned FK), the PDF throws a fatal error and the download fails entirely.
- **Fix:** Add `@if($designation->subscribable)` guard around the loop body. Single-line change, zero risk.
- **Notes:** This is the most likely root cause for all "good standing letter won't download" reports.

---

### 🔴 Ticket 874 — Suspended Member Incorrectly Showing as In Good Standing
**ITSM-202605-0029 | No branch**

- **File:** `app/Services/GoodStandingAssessmentService.php` line 43
- **Bug:** The assessment only processes members with `ACTIVE` or `BAD_STANDING` status. Suspended members are skipped entirely, so their `membership_standing` field is never updated on suspension. `Member::isInGoodMembershipStanding()` reads `membership_standing` (not `status`), so a suspended member can still report as in good standing.
- **Fix:** Include `SUSPENDED` status in the assessment query, or explicitly set `membership_standing = NOT_IN_GOOD_STANDING` when the member is suspended.
- **Risk:** Low for code; medium for business logic — confirm the correct expected behaviour with the team before implementing.

---

### 🟡 Ticket 703 — Old Designation Names Still Present on Letter of Good Standing
**ITSM-202602-0017 | Branch exists — 0 ahead, 15 565 behind (abandoned)**

- **File:** `resources/views/pdfs/good-standing.blade.php` lines 114–172
- **Bug:** Designation descriptions are hardcoded as static HTML using old names. When designations are renamed or new ones are added, the letter silently shows stale content. The branch for this ticket is 15 000+ commits stale — treat it as abandoned.
- **Fix:** Pull descriptions dynamically from the `subscribable` model. Verify which field carries the long-form description before implementing.
- **Risk:** Low code risk; medium content risk — verify correct field with a designations admin.

---

### 🟡 Ticket 876 — Disability Dropdown Does Not Show Anything
**ITSM-202605-0031 | Branch exists — 0 ahead (work already merged or never done)**

- **File:** `app/Http/Controllers/Inertia/Member/Registration/AssociateRegistrationController.php`
- **Bug:** Controller correctly passes `Disability::orderBy('name')->get()` and `DisabilitySeverity::orderBy('id')->get()`, so the code is fine. The production `disabilities` and `disability_severities` tables are empty — no reference data has been seeded.
- **Fix:** Seed/migrate the reference data in production. No application code change required.
- **Action:** Run the appropriate seeder on the production server, or create a data migration if seeders don't exist yet.

---

### 🟡 Ticket 885 — CPD Entries Export Not Showing CPD Units Logged
**ITSM-202605-0040 | No branch**

- **File:** `app/Http/Controllers/Inertia/CpdController.php`
- **Bug:** The CPD export includes `total_hours_decimal` as "Hours" but has no "units" column. Members and staff expect a units figure per the IFAC CPD framework (typically `hours / 2` for verifiable CPD).
- **Fix:** Add a units column to the export. Confirm the expected formula with the team first.
- **Risk:** Low.

---

### 🟢 Ticket 441 — "Back Me Up Letter" Implementation Request
**ITSM-202511-0001 | No branch**

- **Status:** **Already fully implemented.**
  - Controller: `MemberMembershipController.php` lines 449–534 (`backMeUpLetter()` and `generateBackMeUpLetter()`)
  - Routes: `vue.member.membership.back-me-up-letter` and `.generate` (registered in `routes/web/member.php` lines 165–166)
  - UI link: `GoodStanding.vue` line 17 already links to the route
- **Action:** Test end-to-end on staging/prod. If it works, close the ticket.

---

## 2. Git Branch Status for All ITSM Tickets

26 tickets have matching git branches in CibaRebuildSystem.

### Active branches — work done, NOT yet merged

| Branch | Ticket | Subject | Ahead | Behind | Assessment |
|--------|--------|---------|-------|--------|------------|
| ITSM-202606-0024 | 938 | ATO logbooks upload and download | 1 | 6 | **Best candidate to ship** — 6 behind, clean diff, tests included |
| ITSM-202604-0037 | 824 | Member Verification redesign | 1 | 18 | Commit labelled "Not_Final" — needs review before merge |
| ITSM-202604-0039 | 826 | ACCESS RIGHTS / Complaints edit page | 1 | 36 | Work in progress; commit "Flushed Out Edit Page" |
| ITSM-202606-0040 | 954 | Tax License Transfer — docs incorrect | 1 | 20 | New `MemberSharedPayload` class + tests; moderate rebase |
| ITSM-202604-0051 | 838 | V2 Member Profile — global search card | 1 | 28 | Adds `GlobalSearchMemberCard`; "Merged" commit label is odd — verify |
| ITSM-202510-0148 | 156 | CPD: Urgent CPD Entry page | 10 | 353 | Contains a revert commit — was previously merged and reverted. Investigate why before re-attempting |
| ITSM-202511-0030 | 470 | Add Logbook to ATO trainee system | 39 | 332 | 39 commits of ATO feature work; 332 behind, major rebase or merge effort required |
| ITSM-202601-0049 | 630 | Other Licenses Spec | 2 | 747 | Only seeder commits; 747 behind — treat as abandoned, start fresh if still needed |

### Stale branches — 0 commits ahead of master (merged or abandoned)

| Branch | Ticket | Subject | Behind | Likely state |
|--------|--------|---------|--------|-------------|
| ITSM-202511-0025 / v2 | 465 | Incorrect automatic membership status update | 401 / 217 | Merged to master |
| ITSM-202601-0030 | 611 | Tax License Certificate date incorrect | 257 | Merged to master |
| ITSM-202601-0093 | 674 | Unable to export Annual Declarations list | 322 | Merged or abandoned |
| ITSM-202601-0102 | 683 | Channel not loaded — payment issue | 321 | Merged to master |
| ITSM-202602-0006 | 692 | IR/BRP/Immigration certificates on ECMS | ~stale | Merged to master |
| ITSM-202602-0017 | 703 | Old designation names on good standing | 15 565 | Abandoned (pre-dates most of the codebase) |
| ITSM-202603-0009 | 752 | Spec: Find an accountant | ~stale | Merged to master |
| ITSM-202603-0026 | 769 | Reinstatement of CBAP application | ~stale | Merged to master |
| ITSM-202604-0020 | 807 | Incorrect payment option (re-opened) | ~stale | Merged to master |
| ITSM-202604-0037 | 824 | Member Verification redesign | 18 | **See active table above** |
| ITSM-202605-0031 | 876 | Disability dropdown empty | ~stale | Code merged; data not seeded in prod |
| ITSM-202605-0047 | 892 | Tax invoices deleted | 3 | Merged or stale |
| ITSM-202606-0013 | 927 | Reinstatement of Cancellation Reason tab | ~stale | Branch created, no commits |
| ITSM-202510-0303 | 311 | TP Renewals | ~stale | Merged to master |
| ITSM-202510-0363 / 0355 | 363 | TP Functionality | ~stale | Merged to master |
| ITSM-202511-0032 | 472 | Marketing Consent workflow | ~stale | Merged to master |
| ITSM-202511-0077 | 517 | Member Overview spec | ~stale | Merged to master |
| ITSM-202512-0013 | 556 | Brevo automated mailer templates | ~stale | Merged or done in Brevo dashboard |

---

## 3. All 69 Tickets by Category

### Bug / Broken Functionality (15)

| Ticket # | Subject | Branch | Code finding |
|----------|---------|--------|-------------|
| ITSM-202510-0267 | Issue with Automated Tax License Process | — | — |
| ITSM-202511-0025 | Incorrect automatic membership status update | ✅ (merged) | — |
| ITSM-202511-0077 | Member Overview — spec doc | ✅ (merged) | — |
| ITSM-202601-0030 | Tax License Certificate date incorrect | ✅ (merged) | — |
| ITSM-202602-0017 | Old designation names on good standing letter | ✅ (abandoned) | Hardcoded Blade text — see §1 |
| ITSM-202603-0012 | CIBA201801-2566 (member bug) | — | — |
| ITSM-202603-0022 | Unable to Decline Proxy Appointment | — | — |
| ITSM-202604-0003 | ECMS Application Status Discrepancy | — | — |
| ITSM-202604-0020 | Incorrect payment option (re-opened) | ✅ (merged) | — |
| ITSM-202605-0029 | Suspended member showing as in good standing | — | **GoodStandingAssessmentService bug — see §1** |
| ITSM-202605-0031 | Disability dropdown shows nothing | ✅ (data issue) | Empty DB table — see §1 |
| ITSM-202605-0038 | Unable to add Tax License cert to member profile | — | — |
| ITSM-202605-0056 | Incorrect profiles loading | — | — |
| ITSM-202606-0013 | Reinstatement of Cancellation Reason tab | ✅ (no commits) | — |
| ITSM-202606-0044 | Good standing letter download not working | — | **Null crash in Blade — see §1** |

### CPD (5)

| Ticket # | Subject | Branch | Code finding |
|----------|---------|--------|-------------|
| ITSM-202510-0148 | CPD: Urgent CPD Entry page | ✅ (reverted, stale) | Was merged then reverted — investigate |
| ITSM-202511-0004 | CPDs changing verifiable → non-verifiable | — | — |
| ITSM-202605-0040 | CPD Entries Export not showing units | — | **Missing units column — see §1** |
| ITSM-202606-0030 | CPD units required per annum not reflecting | — | — |
| ITSM-202606-0042 | CPD Balance for 2026 incorrect | — | — |

### Data / Reporting (5)

| Ticket # | Subject | Branch | Code finding |
|----------|---------|--------|-------------|
| ITSM-202601-0093 | Unable to export Annual Declarations list | ✅ (merged) | — |
| ITSM-202602-0036 | PPC Dashboard ticket number discrepancy | — | — |
| ITSM-202606-0001 | Duplicate reports received 20 May | — | — |
| ITSM-202606-0016 | List of July billing run members not yet contacted | — | — |
| ITSM-202606-0021 | PPC Collection Export — EFT allocation | — | — |

### ECMS / Applications (7)

| Ticket # | Subject | Branch | Code finding |
|----------|---------|--------|-------------|
| ITSM-202601-0090 | Critical Skills Application to be added to ECMS V2 | — | — |
| ITSM-202602-0006 | IR, BRP and Immigration Certificates on ECMS | ✅ (merged) | — |
| ITSM-202603-0026 | Reinstatement of CBAP application | ✅ (merged) | — |
| ITSM-202603-0029 | Update status for auto-approved applications | — | — |
| ITSM-202603-0043 | CCFM and CCFO application issues | — | — |
| ITSM-202605-0034 | Associate Member with ATO Application | — | — |
| ITSM-202606-0029 | Corporate Membership Documents | — | — |

### Email / Notifications (4)

| Ticket # | Subject | Branch | Code finding |
|----------|---------|--------|-------------|
| ITSM-202512-0013 | Brevo automated mailer templates | ✅ (merged/Brevo) | — |
| ITSM-202602-0042 | ECMS Notifications | — | — |
| ITSM-202606-0019 | Update Brevo ID Mailer 4500 designation approval links | — | — |
| ITSM-202606-0038 | Brevo Templates | — | — |

### Feature / Enhancement Request (16)

| Ticket # | Subject | Branch | Code finding |
|----------|---------|--------|-------------|
| ITSM-202511-0001 | Back Me Up Letter | — | **Already implemented — see §1** |
| ITSM-202511-0030 | Add Logbook to ATO trainee system | ✅ (stale, 332 behind) | — |
| ITSM-202511-0032 | Marketing Consent & High-Profile Applicant | ✅ (merged) | — |
| ITSM-202601-0049 | Other Licenses Spec | ✅ (abandoned) | — |
| ITSM-202603-0009 | Spec: Find an accountant | ✅ (merged) | — |
| ITSM-202603-0013 | Verification Link adjustment | — | — |
| ITSM-202603-0028 | Proxy withdrawal process amendment | — | — |
| ITSM-202603-0042 | Replace pricing image on ECMS | — | — |
| ITSM-202604-0027 | Add all South African universities | — | — |
| ITSM-202604-0037 | Member Verification redesign | ✅ (1 ahead, 18 behind) | Not_Final — review before merge |
| ITSM-202604-0039 | ACCESS RIGHTS | ✅ (1 ahead, 36 behind) | In progress |
| ITSM-202604-0051 | V2 Member Profile — global search card | ✅ (1 ahead, 28 behind) | Global search feature |
| ITSM-202605-0045 | API Interface for members and corporate members | ✅ (claude/ branch) | — |
| ITSM-202605-0064 | List of Institutions | — | — |
| ITSM-202606-0007 | Delete Document Button on Application Page | — | — |
| ITSM-202606-0024 | ATO logbooks upload and download | ✅ **(1 ahead, 6 behind)** | **Best candidate to ship** |

### Membership / Member Profile (5)

| Ticket # | Subject | Branch | Code finding |
|----------|---------|--------|-------------|
| ITSM-202602-0054 | Janine Shapiro member issue | — | — |
| ITSM-202604-0011 | Namibia Members | — | — |
| ITSM-202604-0047 | CIBA Membership Verification Guide | — | — |
| ITSM-202605-0045 | Curtis Moolman — member ID | — | — |
| ITSM-202606-0005 | Bulk redistribution of member allocations | — | — |

### Payments / Billing (5)

| Ticket # | Subject | Branch | Code finding |
|----------|---------|--------|-------------|
| ITSM-202601-0102 | Channel not loaded — payment | ✅ (merged) | — |
| ITSM-202605-0047 | Tax invoices deleted | ✅ (merged) | — |
| ITSM-202605-0065 | Invoice creation issue for manually added profiles | — | — |
| ITSM-202606-0018 | Auto credit allocation for Channel 2 TP licence | — | — |
| ITSM-202606-0039 | Promo code issue / adjustments | — | — |

### Tax / Licence (6)

| Ticket # | Subject | Branch | Code finding |
|----------|---------|--------|-------------|
| ITSM-202510-0303 | TP Renewals | ✅ (merged) | — |
| ITSM-202510-0355 | TP Functionality | ✅ (merged) | — |
| ITSM-202511-0002 | Tax Practitioner License Application | — | — |
| ITSM-202603-0011 | Bypass payment option for Tax License | — | — |
| ITSM-202606-0040 | Tax License Transfer — incorrect documents | ✅ (1 ahead, 20 behind) | `MemberSharedPayload` + tests |
| ITSM-202606-0042 | Auto credit allocation for Channel 2 TP | — | — |

### Uncategorised (1)

| Ticket # | Subject | Branch | Code finding |
|----------|---------|--------|-------------|
| ITSM-202606-0046 | Option to allocate CPD entries to specific year | — | — |

---

## 4. Prioritised Action Plan

In recommended order of effort vs. impact:

**Immediate (no branch needed, small code change):**
1. **Ticket 958** — Fix null crash in `good-standing.blade.php` — one Blade guard, zero risk, unblocks all letter downloads
2. **Ticket 441** — Verify Back Me Up Letter works end-to-end; close if confirmed
3. **Ticket 876** — Seed disability reference data in production (no code change)

**Short sprint (branch work nearly ready):**
4. **Ticket 938 / ITSM-202606-0024** — Rebase (6 commits behind), review ATO reference documents feature, merge — lowest effort of all active branches
5. **Ticket 954 / ITSM-202606-0040** — Review `MemberSharedPayload` diff + tests, rebase (20 behind), merge
6. **Ticket 838 / ITSM-202604-0051** — Review GlobalSearch card diff, rebase (28 behind), merge

**Medium effort (logic fixes with tests needed):**
7. **Ticket 874** — Fix `GoodStandingAssessmentService` to include suspended members — confirm expected behaviour, write test
8. **Ticket 703** — Replace hardcoded designation names in good standing letter with dynamic model data
9. **Ticket 885** — Add CPD units column to export

**Larger effort (stale branch + rebase or fresh start):**
10. **Ticket 824 / ITSM-202604-0037** — Member Verification redesign (1 ahead, 18 behind; "Not_Final" — review scope)
11. **Ticket 826 / ITSM-202604-0039** — ACCESS RIGHTS / Complaints edit page (in progress)
12. **Ticket 470 / ITSM-202511-0030** — ATO Logbook (39 commits ahead, 332 behind — needs strategic merge plan)
13. **Ticket 156 / ITSM-202510-0148** — CPD Entry page (investigate why it was reverted before re-attempting)

**Candidates to close (already in master):**
Verify in production and close: tickets 311, 363, 441, 472, 517, 611, 683, 692, 752, 769, 807, 892

**Do not attempt:**
- Ticket 630 / ITSM-202601-0049 (branch 747 commits stale — start fresh from master if feature is still needed)
- ITSM-202602-0017 (branch 15 565 commits behind — effectively abandoned)

---

*End of analysis. No files were modified in CibaRebuildSystem.*
