• Create a brand-new project named `my-bills` from scratch by cloning `crm-core` with maximum structural fidelity.

  Source of truth:
  - Project source: `/var/www/html/crm-core`
  - Clone plans source: `/var/www/html/crm-core/codex/plans`
  - Required clone rule file: `/var/www/html/crm-core/codex/plans/page-clone-from-basic-crm.json`

  Target:
  - Project path: `/var/www/html/my-bills`
  - Database name: `my-bills`

  Main objective:
  - Recreate a working `my-bills` project that uses the same authenticated shell, routing model, page module structure, and `basic.crm` page behavior as
  `crm-core`.
  - The first and only routed page for now must be `history`.
  - `history` must be cloned from `crm-core/pages/basic.crm/*` first, then adapted to bills data without losing the original structure.

  Hard requirements:
  - Follow `/var/www/html/crm-core/codex/plans/page-clone-from-basic-crm.json` strictly.
  - Do not simplify, flatten, modernize, redesign, or reorganize the `basic.crm` page structure unless I explicitly ask.
  - Fidelity to `crm-core` is more important than speed.
  - If the resulting `history` page differs structurally from `crm-core/pages/basic.crm/basic.crm.php`, `crm-core/pages/basic.crm/basic.crm.js`, or `crm-
  core/pages/basic.crm/basic.crm.css`, stop and fix it before considering the task complete.

  The cloned `history` page must preserve the `basic.crm` interaction model:
  - applied filter strip at the top
  - left-aligned collapsible actions section below the filter strip
  - actions block containing:
    - action select
    - export scope select
    - run button
  - gear icon in the sticky toolbar
  - page setup modal for:
    - rows per page
    - table font size
  - floating filter drawer on the right
  - floating filter drawer toggle button
  - table selection behavior with header select-all checkbox
  - core-style pagination structure
  - export setup modal structure
  - async export progress modal behavior

  Shared app requirements:
  - Preserve the `templates/basic.crm/*` shell architecture and behavior from `crm-core`.
  - Register the new page in `templates/basic.crm/app.php`.
  - Use the same routed page/module approach as `crm-core`.
  - Keep hardcoded async endpoint patterns working for the `my-bills` app.
  - Ensure the app runs under `/my-bills`.

  Create these data structures for `my-bills`:
  1. Table `bills_groups`
     - `id` int auto increment primary key
     - `name` varchar(120) not null
     - unique key on `name`

  2. Table `bills`
     - `id` int auto increment primary key
     - `id_group` int not null
     - `name` varchar(255) not null
     - `value` decimal(12,2) not null default 0.00
     - `date` date not null
     - index on `id_group`
     - foreign key from `id_group` to `bills_groups.id`
     - on delete restrict
     - on update cascade

  3. View `view_bills`
     - select all bill fields
     - join group name from `bills_groups`
     - expose `group_name`

  Use this exact SQL shape unless there is a real compatibility issue:
  - `bills_groups`
    - `id` int(11) NOT NULL AUTO_INCREMENT
    - `name` varchar(120) NOT NULL
    - `PRIMARY KEY (id)`
    - `UNIQUE KEY uniq_bills_groups_name (name)`

  - `bills`
    - `id` int(11) NOT NULL AUTO_INCREMENT
    - `id_group` int(11) NOT NULL
    - `name` varchar(255) NOT NULL
    - `value` decimal(12,2) NOT NULL DEFAULT 0.00
    - `date` date NOT NULL
    - `PRIMARY KEY (id)`
    - `KEY idx_bills_id_group (id_group)`
    - `CONSTRAINT fk_bills_group_id FOREIGN KEY (id_group) REFERENCES bills_groups (id) ON DELETE RESTRICT ON UPDATE CASCADE`

  - `view_bills`
    - `CREATE OR REPLACE VIEW view_bills AS`
    - `SELECT b.*, bg.name AS group_name`
    - `FROM bills b`
    - `LEFT JOIN bills_groups bg ON bg.id = b.id_group`

  History page domain requirements:
  - Use bills data from `view_bills`
  - Show table columns for:
    - id
    - group
    - name
    - value
    - date
  - Support filters for:
    - group
    - name
    - date range
  - Support selected-row export
  - Support filtered export
  - Keep export behavior aligned with `basic.crm` structure

  Implementation expectations:
  - Create all project files under `/var/www/html/my-bills`
  - Create the database if missing
  - Import the schema into `my-bills`
  - Add any needed `.env`, constants, views, tables, includes, templates, assets, and routed page files
  - Ensure history page assets are wired into the shell
  - Ensure authentication flow and standard shell boot correctly

  Verification is mandatory. Do not stop before checking:
  - project files exist under `/var/www/html/my-bills`
  - database `my-bills` exists
  - tables `bills` and `bills_groups` exist
  - view `view_bills` exists
  - `index.php?page=history` loads through the normal app shell
  - sidebar includes `History`
  - applied filter strip is present
  - Actions button is left-aligned and expands correctly
  - actions form contains action select, export scope select, and run button in the expected order
  - toolbar gear icon is present
  - setup modal opens and saves rows-per-page and table-font-size
  - floating filter drawer exists and opens
  - filters round-trip through the URL
  - table selection works, including header select-all
  - table renders rows
  - export starts, progresses, and produces a downloadable file

  Working style:
  - Make reasonable assumptions and execute.
  - If something from `crm-core` and `my-bills` conflicts, prefer `crm-core` structure unless I explicitly requested a domain change.
  - If any clone drift appears, fix it immediately instead of leaving a simplified version.
  - Return only when the project, schema, and verification are complete.