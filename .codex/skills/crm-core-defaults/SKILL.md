---
name: crm-core-defaults
description: Use for work in the crm-core project when making code changes, reviews, or debugging. Captures local coding preferences for this PHP CRM codebase: inspect existing patterns first, keep changes minimal, preserve user edits, prefer fast repo search with rg, use apply_patch for edits, avoid destructive git operations, and verify behavior with focused checks.
---

# CRM Core Defaults

Use this skill for routine work in this repository. Treat it as the default project posture, not as a rigid script.

## General stance

- Read the existing implementation before proposing or making changes.
- Default to the smallest change that solves the problem cleanly.
- Preserve the style already present in the files you touch.
- Prefer direct, practical solutions over abstract cleanup.
- State assumptions, blockers, and verification plainly.

## Editing rules

- Prefer `rg` and `rg --files` for search.
- Use `apply_patch` for manual file edits.
- Keep diffs tight and easy to review.
- Never revert unrelated work in a dirty tree.
- Avoid destructive git operations unless explicitly requested.

## Project fit

- This codebase is plain PHP with local ownership pushed toward `templates/` and `pages/`, while shared backend code still sits in `includes/`, `class/`, and `objects/`.
- Work with that structure instead of trying to modernize it by default.
- Reuse existing helpers, classes, and conventions before adding new abstractions.
- Put logic near the layer that already owns it. Do not move behavior around without a concrete reason.

## Current structure

- `templates/basic.crm/basic.crm.php` is the authenticated shell.
- `templates/basic.crm/basic.crm.css` and `templates/basic.crm/basic.crm.js` belong to that shell.
- `templates/basic.crm/app.php` is the page registry for the shell.
- `templates/login/login.php` owns the login screen.
- `templates/reset-password/reset-password.php` owns the reset-password screen.
- `pages/<page>/<page>.php`, `pages/<page>/<page>.css`, and `pages/<page>/<page>.js` should stay together when the file is owned by one page.
- Page-local action endpoints should live next to the page when they only exist for that page.
- `class/` and `includes/` still hold shared backend/core code. Treat those as unresolved shared ownership until the repo decides where the global core should live.

## Main page map

- The main screen is `basic.crm`, registered in `templates/basic.crm/app.php`.
- `pages/basic.crm/bootstrap.php` builds `$pageState`: filters, preferences, options, results, and pagination.
- `pages/basic.crm/basic.crm.php` renders the full page directly.
- `pages/basic.crm/basic.crm.css` owns the page layout and floating filter panel styles.
- `pages/basic.crm/basic.crm.js` owns the page controller: DOM and modal caching, event binding, filter parsing, pagination and table selection, plus export workflow and progress polling.
- Page-local helpers and actions live beside it in `pages/basic.crm/payments.php`, `pages/basic.crm/start.php`, `pages/basic.crm/progress.php`, and `pages/basic.crm/download.php`.

## PHP preferences

- Favor straightforward PHP over framework-like patterns.
- Keep template files focused on rendering when practical.
- If a helper or class already owns a concern, extend that instead of duplicating logic elsewhere.
- Avoid introducing new dependencies unless they clearly reduce complexity.

## Frontend preferences

- Preserve the current Bootstrap and vanilla JS approach unless the task explicitly requires a larger rewrite.
- Scope CSS and JS changes to the relevant screen or feature.
- Avoid unnecessary build tooling, packages, or frontend abstractions.

## Validation rules

- Validate the changed path with the smallest useful check first.
- Prefer focused verification over broad runs when no clear project test command exists.
- If validation is partial, say exactly what was and was not checked.

## Review posture

- Prioritize bugs, regressions, edge cases, data risks, and missing validation.
- Keep summaries short.
- Put findings first and include file references when possible.

## How to extend this file

- Keep this file short and opinionated.
- Add separate reference files for feature-specific workflows if the skill grows.
- Add scripts only for repeated or fragile procedures that benefit from deterministic execution.
