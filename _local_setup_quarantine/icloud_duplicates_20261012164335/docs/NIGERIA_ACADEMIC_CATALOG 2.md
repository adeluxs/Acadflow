# Nigeria Academic Catalog Integration

AcadFlow keeps the existing `universities -> faculties -> departments -> courses` structure. The enhancement does not introduce a parallel academic model.

## Registry sync

Run:

```bash
php artisan acadflow:sync-nigeria-catalog
```

The command synchronises Nigerian university records from the configured NUC registries and attempts the NBTE approved-polytechnic registry. If the Digital NBTE listing cannot be parsed/reached, the configured polytechnic fallback snapshot is used instead. Every imported institution stores its catalog provenance.

The normal database seeder runs this sync when `ACADEMIC_CATALOG_REMOTE_SYNC=true`, then ensures every active institution has a usable starter faculty/department/course structure when `ACADEMIC_CATALOG_SEED_TEMPLATES=true`.

## Curriculum accuracy

There is no single regulator page in this project that provides the exact faculty, department and course curriculum for every Nigerian institution. AcadFlow therefore does **not** label a generic curriculum as institution-approved. Starter rows are saved with:

- `catalog_source = starter_template`
- `is_catalog_template = true`
- an institution verification flag in `universities.settings.academic_catalog`

Institution administrators can replace or complement starter rows with their verified curriculum.

## Import an exact institution catalog

Copy `database/data/academic-catalog-import-template.csv`, populate it with the institution's verified data, then run:

```bash
php artisan acadflow:sync-nigeria-catalog --csv=/absolute/path/to/catalog.csv
```

To import exact curriculum data without creating starter template rows afterward:

```bash
php artisan acadflow:sync-nigeria-catalog --csv=/absolute/path/to/catalog.csv --no-templates
```

Required columns are:

- `institution_name`
- `faculty_name`
- `department_name`
- `course_code`
- `course_name`

The importer scopes codes to their tenant hierarchy so normal codes such as `CSC`, `ENG`, `ACC`, and `CSC101` can safely exist in more than one institution.

## Lecturer and student membership

Lecturer self-assignment is controlled by `lecturer_self_assignment_enabled`. A lecturer may only self-assign to an active course in the lecturer's own institution and department, and the assignment is tied to the active semester.

Lecturers on the active-semester teaching team can directly enrol a matching student or create an expiring email invitation. Student department restriction is controlled by `restrict_course_membership_to_department`. Invitation tokens are stored only as SHA-256 hashes.
