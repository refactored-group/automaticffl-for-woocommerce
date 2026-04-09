# Development Workflow

## Overview
This document defines the development workflow for the Automatic FFL for WooCommerce project, managed by Conductor.

## Task Execution Methodology

### Implementation Flow
For each task in a track's `plan.md`, follow this sequence:

1. **Understand** — Read the task description and related spec requirements.
2. **Implement** — Write the code changes required by the task.
3. **Verify** — Manually verify the implementation works as expected.
4. **Commit** — Commit the changes with a descriptive message.

### No Test Requirements
This project does not enforce automated test coverage. Manual testing is the primary verification method. Use the built-in sandbox mode for testing API integrations.

## Version Control

### Commit Frequency
- **Commit after every task** — Each completed task gets its own commit.
- Do not batch multiple tasks into a single commit.
- If a task requires no code changes (e.g., a verification-only task), no commit is needed.

### Commit Message Format
```
conductor(<track_id>): <brief description of change>

Task: <task description from plan.md>
Phase: <phase name>
```

### Task Summaries
- Use **Git Notes** to record task summaries.
- After each task commit, attach a git note summarizing what was done and any decisions made.
- Format: `git notes add -m "<summary>"`

## Code Style
- Follow the code style guides in `conductor/code_styleguides/`.
- PHP: WordPress PHP Coding Standards (see `code_styleguides/php.md`)
- JavaScript: WordPress JavaScript Standards + React/Blocks conventions (see `code_styleguides/javascript.md`)

## Security Checklist
Before completing any task that touches user input or output:
- [ ] All input sanitized (`sanitize_text_field()`, `absint()`, etc.)
- [ ] All output escaped (`esc_html()`, `esc_attr()`, `esc_url()`)
- [ ] Nonces verified on form submissions
- [ ] Capabilities checked for admin actions

## Phase Completion Verification and Checkpointing Protocol

At the end of each phase, the following verification steps MUST be performed:

1. **Review all tasks** — Confirm every task in the phase is marked `[x]` in `plan.md`.
2. **Manual verification** — The user must manually verify the phase's functionality works as expected in a WordPress/WooCommerce environment.
3. **Update metadata** — Update the track's `metadata.json` with the current timestamp in `updated_at`.
4. **Checkpoint commit** — Create a commit marking the phase as complete:
   ```
   conductor(<track_id>): complete phase '<phase_name>'
   ```
5. **User sign-off** — The user must explicitly confirm the phase is complete before proceeding to the next phase.

## File Modification Rules
- Follow existing patterns in the codebase — match the style of surrounding code.
- Prefer editing existing files over creating new ones.
- When creating new PHP classes, follow the PSR-4 naming convention (`class-{kebab-case}.php`).
- When adding new WooCommerce Blocks components, place them in `assets/js/blocks/` and register via `block.json`.
- Always run `npm run build` after modifying Blocks source files.

## Version Management
When a track results in a new release:
1. Update `changelog.txt` with new version entry
2. Update `Version:` header in `automaticffl-for-woocommerce.php`
3. Update `AFFL_VERSION` constant in `automaticffl-for-woocommerce.php`
4. Update `README.md` version and changelog
5. Update `claude.md` "Current Version"
