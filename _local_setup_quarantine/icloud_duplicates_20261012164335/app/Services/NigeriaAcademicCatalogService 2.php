<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\University;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Synchronises the Nigerian higher-institution registry without replacing the
 * existing AcadFlow tenant/faculty/department/course data model.
 *
 * Institution rows come from regulator-maintained registries where possible.
 * Faculty/department/course rows can then be imported from an exact CSV export.
 * When no exact institutional catalogue is available, AcadFlow can create a
 * clearly-marked starter template so lecturers/admins have a usable structure
 * that can be verified and edited instead of pretending generic rows are official.
 */
class NigeriaAcademicCatalogService
{
    private const STATES = [
        'Abia','Adamawa','Akwa Ibom','Anambra','Bauchi','Bayelsa','Benue','Borno','Cross River','Delta',
        'Ebonyi','Edo','Ekiti','Enugu','Gombe','Imo','Jigawa','Kaduna','Kano','Katsina','Kebbi','Kogi',
        'Kwara','Lagos','Nasarawa','Niger','Ogun','Ondo','Osun','Oyo','Plateau','Rivers','Sokoto','Taraba',
        'Yobe','Zamfara','Federal Capital Territory','FCT','Abuja',
    ];

    public function syncInstitutions(bool $allowFallback = true, bool $preserveExisting = false): array
    {
        $result = [
            'nuc' => 0,
            'nbte' => 0,
            'fallback_polytechnics' => 0,
            'warnings' => [],
        ];

        foreach ((array) config('academic_catalog.nuc', []) as $ownership => $url) {
            try {
                $html = $this->http()->get($url)->throw()->body();
                $rows = $this->parseNucTable($html, (string) $ownership, (string) $url);
                foreach ($rows as $row) {
                    $this->upsertInstitution($row, $preserveExisting);
                    $result['nuc']++;
                }
            } catch (Throwable $e) {
                $result['warnings'][] = 'NUC '.ucfirst((string) $ownership).' registry: '.$e->getMessage();
            }
        }

        try {
            $url = (string) config('academic_catalog.nbte.polytechnics');
            $response = $this->http()->get($url)->throw();
            $rows = $this->parseNbtePayload($response->body(), $response->header('content-type'), $url);
            foreach ($rows as $row) {
                $this->upsertInstitution($row, $preserveExisting);
                $result['nbte']++;
            }
        } catch (Throwable $e) {
            $result['warnings'][] = 'NBTE polytechnic registry: '.$e->getMessage();
        }

        // The official Digital NBTE endpoint can occasionally be protected by a
        // transient gateway. The fallback is used only for polytechnic coverage;
        // NUC university data is never replaced with the older snapshot.
        if ($allowFallback && $result['nbte'] === 0) {
            try {
                $result['fallback_polytechnics'] = $this->importInstitutionFallbackCsv(
                    (string) config('academic_catalog.fallback.institutions_csv'),
                    $preserveExisting
                );
            } catch (Throwable $e) {
                $result['warnings'][] = 'Polytechnic fallback registry: '.$e->getMessage();
            }
        }

        if (($result['nuc'] + $result['nbte'] + $result['fallback_polytechnics']) === 0) {
            throw new RuntimeException('No Nigerian institution registry could be reached. Existing local catalogue was left unchanged.');
        }

        return $result;
    }

    public function importExactCatalogCsv(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Catalogue CSV is not readable: {$path}");
        }

        $handle = fopen($path, 'rb');
        if (! $handle) throw new RuntimeException("Unable to open catalogue CSV: {$path}");

        $header = fgetcsv($handle);
        if (! is_array($header)) throw new RuntimeException('Catalogue CSV has no header row.');
        $header = array_map(fn ($value) => Str::snake(trim((string) $value)), $header);
        $required = ['institution_name', 'faculty_name', 'department_name', 'course_code', 'course_name'];
        foreach ($required as $column) {
            if (! in_array($column, $header, true)) {
                throw new RuntimeException("Catalogue CSV is missing required column: {$column}");
            }
        }

        $stats = ['institutions' => 0, 'faculties' => 0, 'departments' => 0, 'courses' => 0];
        $seenInstitutions = [];

        try {
            while (($values = fgetcsv($handle)) !== false) {
                if (count(array_filter($values, fn ($v) => trim((string) $v) !== '')) === 0) continue;
                $values = array_pad($values, count($header), null);
                $row = array_combine($header, array_slice($values, 0, count($header)));
                if (! is_array($row)) continue;

                DB::transaction(function () use ($row, &$stats, &$seenInstitutions): void {
                    $name = trim((string) ($row['institution_name'] ?? ''));
                    if ($name === '') return;

                    $institution = $this->upsertInstitution([
                        'name' => $name,
                        'short_name' => trim((string) ($row['institution_short_name'] ?? '')) ?: null,
                        'code' => trim((string) ($row['institution_code'] ?? '')) ?: null,
                        'institution_type' => $this->normaliseInstitutionType((string) ($row['institution_type'] ?? 'university')),
                        'ownership' => $this->normaliseOwnership((string) ($row['ownership'] ?? '')),
                        'state' => trim((string) ($row['state'] ?? '')) ?: $this->extractState($name),
                        'regulator' => trim((string) ($row['regulator'] ?? '')) ?: null,
                        'website' => $this->normaliseUrl((string) ($row['website'] ?? '')),
                        'catalog_source' => 'exact_csv',
                        'catalog_verified_at' => now(),
                    ]);

                    if (! isset($seenInstitutions[$institution->id])) {
                        $seenInstitutions[$institution->id] = true;
                        $stats['institutions']++;
                    }

                    $facultyCode = $this->normaliseCode((string) ($row['faculty_code'] ?? ''), (string) $row['faculty_name'], 20);
                    $faculty = Faculty::updateOrCreate(
                        ['university_id' => $institution->id, 'code' => $facultyCode],
                        [
                            'name' => trim((string) $row['faculty_name']),
                            'short_name' => Str::limit(trim((string) ($row['faculty_short_name'] ?? '')) ?: $facultyCode, 80, ''),
                            'is_active' => true,
                            'catalog_source' => 'exact_csv',
                            'is_catalog_template' => false,
                        ]
                    );
                    $stats['faculties']++;

                    $departmentCode = $this->normaliseCode((string) ($row['department_code'] ?? ''), (string) $row['department_name'], 20);
                    $department = Department::updateOrCreate(
                        ['faculty_id' => $faculty->id, 'code' => $departmentCode],
                        [
                            'name' => trim((string) $row['department_name']),
                            'short_name' => Str::limit(trim((string) ($row['department_short_name'] ?? '')) ?: $departmentCode, 80, ''),
                            'is_active' => true,
                            'catalog_source' => 'exact_csv',
                            'is_catalog_template' => false,
                        ]
                    );
                    $stats['departments']++;

                    Course::updateOrCreate(
                        ['department_id' => $department->id, 'code' => Str::upper(trim((string) $row['course_code']))],
                        [
                            'name' => trim((string) $row['course_name']),
                            'description' => trim((string) ($row['course_description'] ?? '')) ?: null,
                            'credit_hours' => max(0, min(12, (int) ($row['credit_hours'] ?? 3))),
                            'level' => trim((string) ($row['level'] ?? '100')) ?: '100',
                            'semester' => trim((string) ($row['semester'] ?? '1st')) ?: '1st',
                            'type' => in_array(Str::lower((string) ($row['type'] ?? 'compulsory')), ['compulsory','elective'], true)
                                ? Str::lower((string) ($row['type'] ?? 'compulsory')) : 'compulsory',
                            'pass_mark' => max(0, min(100, (int) ($row['pass_mark'] ?? 40))),
                            'is_active' => true,
                            'catalog_source' => 'exact_csv',
                            'is_catalog_template' => false,
                        ]
                    );
                    $stats['courses']++;
                });
            }
        } finally {
            fclose($handle);
        }

        return $stats;
    }

    public function seedStarterTemplatesForAll(): array
    {
        $stats = ['institutions' => 0, 'faculties' => 0, 'departments' => 0, 'courses' => 0];

        University::query()->where('is_active', true)->orderBy('id')->chunkById(30, function ($institutions) use (&$stats): void {
            foreach ($institutions as $institution) {
                $one = $this->seedStarterTemplate($institution);
                foreach ($stats as $key => $value) $stats[$key] += $one[$key];
            }
        });

        return $stats;
    }

    public function seedStarterTemplate(University $institution): array
    {
        $stats = ['institutions' => 1, 'faculties' => 0, 'departments' => 0, 'courses' => 0];
        $template = $institution->institution_type === 'polytechnic'
            ? $this->polytechnicTemplate()
            : $this->universityTemplate();

        DB::transaction(function () use ($institution, $template, &$stats): void {
            foreach ($template as $facultyName => $departments) {
                $facultyCode = $this->normaliseCode('', $facultyName, 20);
                $faculty = Faculty::firstOrCreate(
                    ['university_id' => $institution->id, 'code' => $facultyCode],
                    [
                        'name' => $facultyName,
                        'short_name' => $facultyCode,
                        'is_active' => true,
                        'catalog_source' => 'starter_template',
                        'is_catalog_template' => true,
                    ]
                );
                if ($faculty->wasRecentlyCreated) $stats['faculties']++;

                foreach ($departments as $departmentName) {
                    $departmentCode = $this->normaliseCode('', $departmentName, 20);
                    $department = Department::firstOrCreate(
                        ['faculty_id' => $faculty->id, 'code' => $departmentCode],
                        [
                            'name' => $departmentName,
                            'short_name' => $departmentCode,
                            'is_active' => true,
                            'catalog_source' => 'starter_template',
                            'is_catalog_template' => true,
                        ]
                    );
                    if ($department->wasRecentlyCreated) $stats['departments']++;

                    foreach ($this->courseTemplatesFor($departmentName, $departmentCode, $institution->institution_type) as $courseRow) {
                        $course = Course::firstOrCreate(
                            ['department_id' => $department->id, 'code' => $courseRow['code']],
                            $courseRow + [
                                'is_active' => true,
                                'catalog_source' => 'starter_template',
                                'is_catalog_template' => true,
                            ]
                        );
                        if ($course->wasRecentlyCreated) $stats['courses']++;
                    }
                }
            }

            $settings = (array) ($institution->settings ?? []);
            $catalogSettings = (array) ($settings['academic_catalog'] ?? []);

            // Preserve an existing seed marker/timestamp and any administrator
            // catalogue preferences. Defaults are added only when they are missing.
            $catalogSettings += [
                'starter_template_seeded' => true,
                'starter_template_seeded_at' => now()->toIso8601String(),
                'requires_institution_verification' => true,
            ];

            if (($settings['academic_catalog'] ?? null) !== $catalogSettings) {
                $settings['academic_catalog'] = $catalogSettings;
                $institution->forceFill(['settings' => $settings])->save();
            }
        });

        return $stats;
    }

    private function http(): PendingRequest
    {
        return Http::accept('*/*')
            ->withHeaders(['User-Agent' => 'AcadFlow Academic Catalog Sync/1.0'])
            ->timeout((int) config('academic_catalog.http_timeout', 20))
            ->retry(2, 500, throw: false);
    }

    private function parseNucTable(string $html, string $ownership, string $sourceUrl): array
    {
        $rows = [];
        foreach ($this->htmlTableRows($html) as $cells) {
            if (count($cells) < 2) continue;
            $numberIndex = ctype_digit(trim($cells[0]['text'])) ? 0 : null;
            if ($numberIndex === null) continue;
            $name = trim($cells[1]['text']);
            if ($name === '' || Str::contains(Str::lower($name), ['universities', 'vice chancellor'])) continue;

            $website = null;
            foreach ($cells as $cell) if ($cell['href']) { $website = $this->normaliseUrl($cell['href']); if ($website) break; }
            $year = null;
            foreach (array_reverse($cells) as $cell) {
                if (preg_match('/\b(19|20)\d{2}\b/', $cell['text'], $match)) { $year = (int) $match[0]; break; }
            }

            $rows[] = [
                'name' => $name,
                'institution_type' => 'university',
                'ownership' => ucfirst(Str::lower($ownership)),
                'state' => $this->extractState($name),
                'regulator' => 'NUC',
                'website' => $website,
                'catalog_source' => $sourceUrl,
                'catalog_verified_at' => now(),
                'settings' => $year ? ['year_established' => $year] : [],
            ];
        }
        return $this->dedupeByName($rows);
    }

    private function parseNbtePayload(string $payload, ?string $contentType, string $sourceUrl): array
    {
        $decoded = null;
        if (Str::contains(Str::lower((string) $contentType), 'json') || str_starts_with(ltrim($payload), '[') || str_starts_with(ltrim($payload), '{')) {
            $decoded = json_decode($payload, true);
        }
        if (is_array($decoded)) {
            $rows = [];
            $this->walkNbteJson($decoded, $rows, $sourceUrl);
            if ($rows) return $this->dedupeByName($rows);
        }

        $rows = [];
        foreach ($this->htmlTableRows($payload) as $cells) {
            $texts = array_values(array_filter(array_map(fn ($c) => trim($c['text']), $cells)));
            if (! $texts) continue;
            $name = null;
            foreach ($texts as $text) {
                $lower = Str::lower($text);
                if (Str::contains($lower, ['polytechnic', 'college of technology', 'institute of technology'])) {
                    $name = $text;
                    break;
                }
            }
            if (! $name) continue;
            $ownership = null;
            foreach ($texts as $text) {
                $normal = $this->normaliseOwnership($text);
                if ($normal) { $ownership = $normal; break; }
            }
            $website = null;
            foreach ($cells as $cell) if ($cell['href']) { $website = $this->normaliseUrl($cell['href']); if ($website) break; }
            $rows[] = [
                'name' => $name,
                'institution_type' => 'polytechnic',
                'ownership' => $ownership,
                'state' => $this->extractState(implode(' ', $texts)),
                'regulator' => 'NBTE',
                'website' => $website,
                'catalog_source' => $sourceUrl,
                'catalog_verified_at' => now(),
            ];
        }
        return $this->dedupeByName($rows);
    }

    private function walkNbteJson(array $value, array &$rows, string $sourceUrl): void
    {
        if (array_is_list($value)) {
            foreach ($value as $item) if (is_array($item)) $this->walkNbteJson($item, $rows, $sourceUrl);
            return;
        }

        $lowerKeys = [];
        foreach ($value as $key => $item) $lowerKeys[Str::snake((string) $key)] = $item;
        $name = $lowerKeys['institution_name'] ?? $lowerKeys['institution'] ?? $lowerKeys['name'] ?? $lowerKeys['school_name'] ?? null;
        if (is_string($name) && trim($name) !== '') {
            $typeText = Str::lower((string) ($lowerKeys['type'] ?? $lowerKeys['institution_type'] ?? $name));
            if (Str::contains($typeText, ['polytechnic', 'college of technology', 'institute of technology'])) {
                $rows[] = [
                    'name' => trim($name),
                    'institution_type' => 'polytechnic',
                    'ownership' => $this->normaliseOwnership((string) ($lowerKeys['ownership'] ?? $lowerKeys['proprietor'] ?? '')),
                    'state' => trim((string) ($lowerKeys['state'] ?? '')) ?: $this->extractState($name),
                    'regulator' => 'NBTE',
                    'website' => $this->normaliseUrl((string) ($lowerKeys['website'] ?? $lowerKeys['url'] ?? '')),
                    'catalog_source' => $sourceUrl,
                    'catalog_verified_at' => now(),
                ];
            }
        }

        foreach ($value as $item) if (is_array($item)) $this->walkNbteJson($item, $rows, $sourceUrl);
    }

    private function importInstitutionFallbackCsv(string $url, bool $preserveExisting = false): int
    {
        $body = $this->http()->get($url)->throw()->body();
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $body);
        rewind($stream);
        $header = fgetcsv($stream);
        if (! is_array($header)) throw new RuntimeException('Fallback institution CSV has no header.');
        $header = array_map(fn ($v) => Str::snake((string) $v), $header);
        $count = 0;

        while (($values = fgetcsv($stream)) !== false) {
            $values = array_pad($values, count($header), null);
            $row = array_combine($header, array_slice($values, 0, count($header)));
            if (! is_array($row)) continue;
            $type = Str::lower((string) ($row['type'] ?? ''));
            if (! Str::contains($type, 'polytechnic')) continue;
            $name = trim((string) ($row['name_of_institution'] ?? $row['name'] ?? ''));
            if ($name === '') continue;
            $this->upsertInstitution([
                'name' => $name,
                'short_name' => trim((string) ($row['acronym'] ?? '')) ?: null,
                'code' => trim((string) ($row['acronym'] ?? '')) ?: null,
                'institution_type' => 'polytechnic',
                'ownership' => $this->normaliseOwnership((string) ($row['ownership'] ?? '')),
                'state' => $this->extractState($name),
                'regulator' => 'NBTE',
                'website' => $this->normaliseUrl((string) ($row['url'] ?? '')),
                'catalog_source' => 'community_fallback',
                'catalog_verified_at' => null,
                'settings' => isset($row['year']) && is_numeric($row['year']) ? ['year_established' => (int) $row['year']] : [],
            ], $preserveExisting);
            $count++;
        }
        fclose($stream);
        return $count;
    }

    public function upsertInstitution(array $row, bool $preserveExisting = false): University
    {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') throw new RuntimeException('Institution name cannot be empty.');

        $institution = University::query()->whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();
        $desiredCode = $this->normaliseCode((string) ($row['code'] ?? ''), $name, 10);

        // Seeders use preserveExisting=true so re-running db:seed only creates
        // missing institutions and never resets administrator-edited catalogue data.
        // Explicit catalogue sync/import commands keep the default false so a
        // deliberate registry refresh can still update regulator metadata.
        if ($institution && $preserveExisting) {
            return $institution;
        }

        if (! $institution) {
            $code = $desiredCode;
            if (University::where('code', $code)->exists()) {
                $code = Str::upper(Str::limit(substr($desiredCode, 0, 7).substr(sha1(Str::lower($name)), 0, 3), 10, ''));
            }
            $institution = new University(['code' => $code]);
        }

        $incomingSettings = (array) ($row['settings'] ?? []);
        $settings = array_replace_recursive((array) ($institution->settings ?? []), $incomingSettings);
        $institution->fill([
            'name' => $name,
            'short_name' => Str::limit(trim((string) ($row['short_name'] ?? '')) ?: ($institution->short_name ?: $desiredCode), 50, ''),
            'institution_type' => $this->normaliseInstitutionType((string) ($row['institution_type'] ?? $institution->institution_type ?? 'university')),
            'ownership' => $this->normaliseOwnership((string) ($row['ownership'] ?? $institution->ownership ?? '')),
            'state' => trim((string) ($row['state'] ?? '')) ?: $institution->state,
            'regulator' => trim((string) ($row['regulator'] ?? '')) ?: $institution->regulator,
            'website' => $row['website'] ?? $institution->website,
            'catalog_source' => $row['catalog_source'] ?? $institution->catalog_source,
            'catalog_verified_at' => $row['catalog_verified_at'] ?? $institution->catalog_verified_at,
            'timezone' => $institution->timezone ?: 'Africa/Lagos',
            'is_active' => $institution->exists ? $institution->is_active : true,
            'settings' => $settings,
        ]);
        $institution->save();

        return $institution;
    }

    private function htmlTableRows(string $html): array
    {
        if (! class_exists(DOMDocument::class)) throw new RuntimeException('PHP DOM extension is required for regulator HTML sync.');
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) throw new RuntimeException('Unable to parse regulator HTML.');
        $xpath = new DOMXPath($document);
        $rows = [];
        foreach ($xpath->query('//tr') ?: [] as $tr) {
            $cells = [];
            foreach ($xpath->query('./th|./td', $tr) ?: [] as $td) {
                $href = null;
                $anchor = $xpath->query('.//a[@href]', $td)?->item(0);
                if ($anchor instanceof DOMElement) $href = trim($anchor->getAttribute('href'));
                $cells[] = ['text' => trim(preg_replace('/\s+/u', ' ', $td->textContent) ?? ''), 'href' => $href];
            }
            if ($cells) $rows[] = $cells;
        }
        return $rows;
    }

    private function dedupeByName(array $rows): array
    {
        $deduped = [];
        foreach ($rows as $row) $deduped[Str::lower(trim((string) $row['name']))] = $row;
        return array_values($deduped);
    }

    private function normaliseInstitutionType(string $type): string
    {
        return Str::contains(Str::lower($type), ['polytechnic', 'college of technology']) ? 'polytechnic' : 'university';
    }

    private function normaliseOwnership(string $ownership): ?string
    {
        $lower = Str::lower(trim($ownership));
        if ($lower === '') return null;
        foreach (['federal' => 'Federal', 'state' => 'State', 'private' => 'Private'] as $needle => $label) {
            if (Str::contains($lower, $needle)) return $label;
        }
        return null;
    }

    private function normaliseUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || $url === '#') return null;
        if (str_starts_with($url, '//')) $url = 'https:'.$url;
        if (! preg_match('#^https?://#i', $url) && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}/i', $url)) $url = 'https://'.$url;
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private function normaliseCode(string $code, string $name, int $max): string
    {
        $code = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        if ($code === '') {
            $words = preg_split('/[^A-Za-z0-9]+/', Str::ascii($name)) ?: [];
            $stop = ['OF','THE','AND','FOR','IN','AT','DEPARTMENT','FACULTY','SCHOOL','COLLEGE'];
            $letters = '';
            foreach ($words as $word) {
                $word = Str::upper($word);
                if ($word === '' || in_array($word, $stop, true)) continue;
                $letters .= strlen($word) <= 4 ? $word : $word[0];
                if (strlen($letters) >= max(3, $max - 2)) break;
            }
            $code = $letters ?: 'CAT';
        }
        $code = substr($code, 0, $max);
        return $code !== '' ? $code : substr(Str::upper(sha1($name)), 0, min(8, $max));
    }

    private function extractState(string $text): ?string
    {
        foreach (self::STATES as $state) {
            if (preg_match('/\b'.preg_quote($state, '/').'\b/i', $text)) {
                return in_array($state, ['FCT','Abuja'], true) ? 'Federal Capital Territory' : $state;
            }
        }
        return null;
    }

    private function universityTemplate(): array
    {
        return [
            'Faculty of Science' => ['Computer Science','Mathematics','Statistics','Physics','Chemistry','Biological Sciences'],
            'Faculty of Engineering and Technology' => ['Electrical and Electronic Engineering','Mechanical Engineering','Civil Engineering','Computer Engineering','Chemical Engineering'],
            'Faculty of Management Sciences' => ['Accounting','Business Administration','Banking and Finance','Marketing','Public Administration'],
            'Faculty of Social Sciences' => ['Economics','Political Science','Sociology','Psychology','Mass Communication'],
            'Faculty of Arts and Humanities' => ['English and Literary Studies','History and International Studies','Languages and Linguistics','Philosophy'],
            'Faculty of Education' => ['Educational Management','Science Education','Arts and Social Science Education','Guidance and Counselling'],
            'Faculty of Environmental Sciences' => ['Architecture','Estate Management','Quantity Surveying','Urban and Regional Planning'],
        ];
    }

    private function polytechnicTemplate(): array
    {
        return [
            'School of Engineering Technology' => ['Electrical and Electronic Engineering Technology','Mechanical Engineering Technology','Civil Engineering Technology','Computer Engineering Technology'],
            'School of Applied Sciences' => ['Computer Science','Statistics','Science Laboratory Technology'],
            'School of Business and Management Studies' => ['Accountancy','Business Administration and Management','Banking and Finance','Public Administration','Marketing'],
            'School of Environmental Studies' => ['Architecture','Estate Management and Valuation','Quantity Surveying','Urban and Regional Planning','Building Technology'],
            'School of Communication and Information Technology' => ['Mass Communication','Library and Information Science','Office Technology and Management'],
        ];
    }

    private function courseTemplatesFor(string $departmentName, string $departmentCode, string $institutionType): array
    {
        $key = Str::lower($departmentName);
        $specific = match (true) {
            Str::contains($key, 'computer science') => ['Introduction to Computer Science','Programming Fundamentals','Data Structures and Algorithms','Database Systems'],
            Str::contains($key, 'account') => ['Principles of Accounting','Financial Accounting','Cost and Management Accounting','Auditing and Assurance'],
            Str::contains($key, 'business administration') => ['Principles of Management','Business Communication','Organisational Behaviour','Entrepreneurship and Small Business'],
            Str::contains($key, 'electrical') => ['Circuit Theory','Electrical Machines','Digital Electronics','Power Systems'],
            Str::contains($key, 'mechanical') => ['Engineering Mechanics','Thermodynamics','Fluid Mechanics','Machine Design'],
            Str::contains($key, 'civil') => ['Engineering Surveying','Structural Analysis','Soil Mechanics','Highway Engineering'],
            Str::contains($key, 'statistics') => ['Descriptive Statistics','Probability Theory','Statistical Inference','Regression Analysis'],
            Str::contains($key, 'science laboratory') => ['Laboratory Techniques','Analytical Chemistry','Instrumentation','Laboratory Management'],
            Str::contains($key, 'mass communication') => ['Introduction to Mass Communication','News Writing and Reporting','Broadcast Production','Media Law and Ethics'],
            Str::contains($key, 'economics') => ['Principles of Economics','Microeconomics','Macroeconomics','Econometrics'],
            Str::contains($key, 'architecture') => ['Architectural Design','Building Construction','Environmental Design','Professional Practice'],
            Str::contains($key, 'quantity surveying') => ['Measurement of Building Works','Construction Economics','Estimating and Tendering','Project Cost Control'],
            default => [
                'Introduction to '.$departmentName,
                'Principles of '.$departmentName,
                'Applied '.$departmentName,
                $institutionType === 'polytechnic' ? 'Professional Practice and Project' : 'Research Methods and Project',
            ],
        };

        $prefix = substr(preg_replace('/[^A-Z0-9]/', '', Str::upper($departmentCode)) ?: 'CRS', 0, 4);
        $numbers = $institutionType === 'polytechnic' ? [111, 211, 311, 411] : [101, 201, 301, 401];
        $levels = ['100','200','300','400'];
        $rows = [];
        foreach ($specific as $index => $name) {
            $rows[] = [
                'code' => substr($prefix.$numbers[$index], 0, 20),
                'name' => $name,
                'description' => 'Starter catalogue entry. Institution administrators should verify this course against the institution’s approved curriculum.',
                'credit_hours' => 3,
                'level' => $levels[$index],
                'semester' => $index % 2 === 0 ? '1st' : '2nd',
                'type' => 'compulsory',
                'pass_mark' => 40,
            ];
        }
        return $rows;
    }
}
