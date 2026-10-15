<?php

declare(strict_types=1);
$root=dirname(__DIR__);
$checks=[
 ['database/migrations/2026_09_03_000001_add_progressive_institution_adoption.php','user_institution_affiliations'],
 ['database/migrations/2026_09_03_000001_add_progressive_institution_adoption.php','department_adoption_requests'],
 ['database/migrations/2026_09_03_000001_add_progressive_institution_adoption.php','institution_access_requests'],
 ['app/Services/InstitutionAdoptionService.php','createClaim'],
 ['app/Services/InstitutionAdoptionService.php','recordSelfDeclared'],
 ['app/Services/InstitutionAdoptionService.php','approveDepartmentAdoption'],
 ['app/Services/InstitutionAdoptionService.php','canReceiveInstitutionBenefits'],
 ['app/Services/Commerce/EntitlementService.php','canReceiveInstitutionBenefits'],
 ['app/Services/Commerce/AiUsageBillingService.php','canReceiveInstitutionBenefits'],
 ['app/Http/Controllers/StudentImportController.php','roster_import'],
 ['app/Console/Commands/InstitutionAdoptionMigrate.php','--apply'],
 ['routes/api.php','/institution-affiliation'],
 ['routes/web.php','admin.institution-access.index'],
 ['tests/Feature/InstitutionProgressiveAdoptionTest.php','test_department_can_adopt_independently_then_migrate_in_place'],
];
$failed=[];
foreach($checks as [$file,$needle]){$path=$root.'/'.$file;if(!is_file($path)||!str_contains((string)file_get_contents($path),$needle))$failed[]="$file missing $needle";}
if($failed){fwrite(STDERR,"Progressive institution adoption preflight FAILED:\n - ".implode("\n - ",$failed)."\n");exit(1);} 
echo "Progressive institution adoption preflight: PASS\n";
echo " - catalogue claiming + duplicate protection\n - self-declared/verified affiliations\n - department-first adoption + in-place migration\n - verified sponsorship gating for features and AI\n - access review, roster reconciliation, API/mobile and migration tooling\n";
