# Format vs Topic: clearer publication types and categories (9 November 2026)

Apply on top of the full project from 8 November.

    rsync -av ~/Downloads/acadsyntra-format-vs-topic/ ~/Projects/acadsyntra/ --exclude README.md
    php artisan migrate --force
    php artisan optimize:clear

## What changed

- On the publish form: "Publication type" is now **Format · what are you sharing?** and
  "Subject category" is now **Topic · what is it about?**, each with a one-line example.
- Knowledge Hub filters read "All topics" and "All formats".
- Categories that had the same name as a format were renamed to topics (display name only;
  IDs and slugs unchanged, so links, filters, guides and Writing Challenge tracks keep working):

  | Old name | New name |
  |---|---|
  | Research Outputs | Research & Publications |
  | Research Insights | Research Explained |
  | Study Guides | Courses & Study Skills |
  | Exam Preparation | Exams & Assessments |
  | Tutorials | Practical Skills |
  | Programming Tutorials | Programming & Technology |
  | Career and SIWES | Careers, Internships & SIWES |
  | Projects and Case Studies | Final Year & Group Projects |
  | Digital Resources | Tools & Learning Resources |
  | Campus and Department Guides | Campus & Departments |

  A category you already renamed yourself is left alone. Rename any of these again in
  Admin > Knowledge Hub > Subject categories if you prefer other words.
- Admin can't create a category whose name is already a format (e.g. "Study guides").
- New installs create the topic names directly.
