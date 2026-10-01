# Moodle Gradebook Doctor

`local_gradebookdoctor` is a read-only diagnostic and explanation tool for the Moodle gradebook. It is designed for
Moodle and depends on [`local_ai_bridge`](https://github.com/EduardoKrausME/moodle-local_ai_bridge)

The central design rule is simple: **Moodle calculates the grade; AI only explains Moodle's result**.

## What it does

### Explain this grade

An authorised grader selects a user and a grade item, category total, or course total. The plugin builds a deterministic
tree using Moodle gradebook records and core grade classes. The tree includes:

- category hierarchy;
- grade items and category totals;
- raw and final grades;
- min/max range;
- aggregation method;
- `aggregationstatus` (`used`, `excluded`, `dropped`, `novalue`, `extra`);
- Moodle's stored `aggregationweight`;
- excluded grades;
- manual overrides;
- locked grades/items;
- hidden items;
- extra credit;
- `needsupdate` state;
- grade formulas and referenced grade items.

The AI receives a reduced JSON representation of that deterministic tree and produces a human explanation. It is
explicitly instructed not to recalculate or replace Moodle's final value.

### Diagnose gradebook

The deterministic diagnostic currently checks for situations such as:

- empty categories;
- simultaneous keep-high/drop-low rules;
- manual Natural aggregation weights, including zero or unusually summed manual weights;
- grade items marked `needsupdate`;
- locked items and locked user grades;
- non-default multiplicator/offset adjustments;
- suspicious numeric min/max ranges;
- formula validation through Moodle core plus self/unresolved formula references;
- items with no final grades;
- manual grade overrides;
- hidden items that Moodle records as participating in aggregation;
- unusually concentrated effective weights, using Moodle's own stored `aggregationweight`.

These checks deliberately distinguish **"needs review"** from **"wrong"**. An unusual gradebook configuration can be
intentional.

For diagnosis, the model is required to return JSON. Invalid JSON is rejected and never replaces the deterministic
findings.

### Compare two students

An authorised grader can compare two enrolled users for the same grade item or total. The comparison reports factual
differences in final values, aggregation status, excluded state, overrides, locks, and effective weighting. The AI is
instructed not to make judgements about either student.

## Security

Access requires all of the following in the course context:

- `local/gradebookdoctor:view`;
- `gradereport/grader:view`;
- `moodle/grade:viewall`.

This intentionally follows the core grader report access model. Students cannot use Gradebook Doctor to inspect another
user's grades.

All report actions that can consume AI credits use POST plus `sesskey` validation.

The plugin does not modify grades, aggregation settings, formulas, exclusions, overrides, or locks. It also does not
force `grade_regrade_final_grades()` from the UI; if Moodle marks an item as `needsupdate`, the plugin reports that
state instead of silently changing gradebook data.

## Privacy

The plugin persists no personal data of its own. It does not persist prompts or AI responses. User names are used only
in the local teacher-facing interface; the AI payload does not include a user's name and labels users generically when
comparing them.

The Privacy API is implemented as a `null_provider` because there are no plugin tables or persisted user-specific
records.

AI Bridge itself may store usage metadata according to its own privacy implementation, but it does not persist
prompt/response text.
