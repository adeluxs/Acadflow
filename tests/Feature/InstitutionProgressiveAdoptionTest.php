<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CommercialAccount;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\InstitutionAccessRequest;
use App\Models\InstitutionEmailDomain;
use App\Models\InstitutionOnboardingRequest;
use App\Models\University;
use App\Models\User;
use App\Services\InstitutionAdoptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionProgressiveAdoptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_catalogue_institution_claim_reuses_existing_record(): void
    {
        $university=University::factory()->create(['adoption_status'=>'unclaimed','name'=>'Catalogue University','code'=>'CATUNI']);
        $representative=User::factory()->create(['account_type'=>'university_representative','role'=>'member']);
        $admin=User::factory()->create(['role'=>'super_admin','account_type'=>'academic_staff']);

        $claim=app(InstitutionAdoptionService::class)->createClaim($representative,$university,['representative_title'=>'Registrar']);
        $this->assertSame($university->id,$claim->university_id);
        $this->assertSame('claim_existing',$claim->request_type);
        $this->assertDatabaseCount('universities',1);

        $this->actingAs($admin)->post(route('admin.institution-onboarding.approve',$claim),['review_note'=>'Verified.'])->assertRedirect();
        $this->assertDatabaseCount('universities',1);
        $this->assertDatabaseHas('users',['id'=>$representative->id,'university_id'=>$university->id,'role'=>'university_admin']);
        $this->assertDatabaseHas('user_institution_affiliations',['user_id'=>$representative->id,'university_id'=>$university->id,'status'=>'verified','affiliation_type'=>'university_admin']);
    }

    public function test_independent_student_does_not_receive_institution_benefits_until_verified_and_active(): void
    {
        $university=University::factory()->create(['adoption_status'=>'unclaimed']);
        $student=User::factory()->create(['university_id'=>$university->id,'role'=>'student']);
        $service=app(InstitutionAdoptionService::class);
        $service->recordSelfDeclared($student);
        $this->assertFalse($service->canReceiveInstitutionBenefits($student));

        $admin=User::factory()->create(['role'=>'super_admin']);
        $service->verifyAffiliation($student,$university,null,'student',$admin,'admin_review');
        $this->assertFalse($service->canReceiveInstitutionBenefits($student->fresh()));

        $university->update(['adoption_status'=>'active','adopted_at'=>now()]);
        $this->assertTrue($service->canReceiveInstitutionBenefits($student->fresh()));
    }

    public function test_department_adoption_preserves_independence_when_institution_activates(): void
    {
        $university=University::factory()->create(['adoption_status'=>'unclaimed']);
        $faculty=Faculty::factory()->create(['university_id'=>$university->id]);
        $department=Department::factory()->create(['faculty_id'=>$faculty->id,'adoption_status'=>'unclaimed']);
        $rep=User::factory()->create(['account_type'=>'department_representative','role'=>'member','university_id'=>$university->id,'faculty_id'=>$faculty->id,'department_id'=>$department->id]);
        $admin=User::factory()->create(['role'=>'super_admin']);
        $service=app(InstitutionAdoptionService::class);

        $request=$service->createDepartmentAdoption($rep,$university,$faculty,$department,['department_name'=>$department->name,'representative_title'=>'Head of Department']);
        $approved=$service->approveDepartmentAdoption($request,$admin);
        $this->assertSame($department->id,$approved->id);
        $this->assertSame('independent_active',$approved->fresh()->adoption_status);

        $university->update(['adoption_status'=>'active']);
        $service->migrateIndependentDepartments($university,$admin);
        $this->assertSame('independent_active',$department->fresh()->adoption_status);
        $this->assertDatabaseCount('departments',1);
    }

    public function test_department_admin_cannot_verify_user_in_another_department(): void
    {
        $university=University::factory()->create(['adoption_status'=>'unclaimed']);
        $faculty=Faculty::factory()->create(['university_id'=>$university->id]);
        $one=Department::factory()->create(['faculty_id'=>$faculty->id,'adoption_status'=>'independent_active']);
        $two=Department::factory()->create(['faculty_id'=>$faculty->id,'adoption_status'=>'independent_active']);
        $admin=User::factory()->create(['role'=>'department_admin','university_id'=>$university->id,'faculty_id'=>$faculty->id,'department_id'=>$one->id]);
        $student=User::factory()->create(['role'=>'student','university_id'=>$university->id,'faculty_id'=>$faculty->id,'department_id'=>$two->id]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(InstitutionAdoptionService::class)->verifyAffiliation($student,$university,$two,'student',$admin);
    }

    public function test_verified_email_domain_cannot_be_reassigned_to_another_institution(): void
    {
        $first=University::factory()->create();$second=University::factory()->create();$admin=User::factory()->create(['role'=>'super_admin']);
        $service=app(InstitutionAdoptionService::class);
        $domain=$service->proposeEmailDomain($first,'example.edu.ng',$admin);$service->verifyEmailDomain($domain,$admin);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->proposeEmailDomain($second,'example.edu.ng',$admin);
    }

    public function test_active_institution_uses_access_request_not_second_claim(): void
    {
        $university=University::factory()->create(['adoption_status'=>'active']);
        $representative=User::factory()->create(['account_type'=>'university_representative','role'=>'member']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(InstitutionAdoptionService::class)->createClaim($representative,$university,['representative_title'=>'ICT Director']);
    }
}
