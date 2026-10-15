<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CommercialAccount;
use App\Models\Department;
use App\Models\DepartmentAdoptionRequest;
use App\Models\Faculty;
use App\Models\InstitutionAccessRequest;
use App\Models\InstitutionEmailDomain;
use App\Models\InstitutionOnboardingRequest;
use App\Models\University;
use App\Models\User;
use App\Services\IndependentAcademicContextService;
use App\Services\InstitutionAdoptionService;
use App\Services\SocialNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstitutionOnboardingController extends Controller
{
    public function __construct(private readonly InstitutionAdoptionService $adoption, private readonly SocialNotificationService $notifications) {}

    public function requestForm(Request $request): View
    {
        abort_unless(in_array($request->user()->account_type,['university_representative','department_representative'],true) || $request->user()->isAdmin(),403);
        $requests=InstitutionOnboardingRequest::query()->where('requester_user_id',$request->user()->id)->latest()->limit(10)->get();
        $departmentRequests=DepartmentAdoptionRequest::query()->where('requester_user_id',$request->user()->id)->with(['university','department'])->latest()->limit(10)->get();
        $oldUniversityId=(int) $request->session()->getOldInput('university_id', 0);
        $selectedUniversity=$oldUniversityId>0 ? University::query()->where('code','!=',IndependentAcademicContextService::UNIVERSITY_CODE)->find($oldUniversityId) : null;
        return view('institution.onboarding-request',compact('requests','departmentRequests','selectedUniversity'));
    }

    public function search(Request $request): JsonResponse
    {
        $data=$request->validate(['q'=>['required','string','min:2','max:120']]);
        $items=$this->adoption->searchInstitutions($data['q'])->map(fn(University $u)=>[
            'id'=>$u->id,'name'=>$u->name,'short_name'=>$u->short_name,'code'=>$u->code,'state'=>$u->state,'type'=>$u->institution_type,
            'regulator'=>$u->regulator,'adoption_status'=>$this->adoption->institutionState($u),'users_count'=>$u->users_count,'faculties_count'=>$u->faculties_count,
        ]);
        return response()->json(['success'=>true,'data'=>$items]);
    }

    public function submitRequest(Request $request): RedirectResponse
    {
        abort_unless($request->user()->account_type === 'university_representative' || $request->user()->isUniversityAdmin(),403);
        $data=$request->validate([
            'request_type'=>['required',Rule::in(['claim_existing','create_new'])],
            'university_id'=>['nullable','integer','exists:universities,id'],
            'institution_name'=>['required_if:request_type,create_new','nullable','string','max:255'],
            'short_name'=>['nullable','string','max:80'],'code'=>['nullable','string','max:20'],
            'institution_type'=>['nullable',Rule::in(['university','polytechnic','college'])],
            'ownership'=>['nullable',Rule::in(['Federal','State','Private'])],'state'=>['nullable','string','max:80'],'regulator'=>['nullable','string','max:30'],
            'email'=>['nullable','email','max:255'],'phone'=>['nullable','string','max:50'],'website'=>['nullable','url','max:500'],'address'=>['nullable','string','max:2000'],
            'representative_title'=>['required','string','max:150'],'evidence'=>['nullable','string','max:3000'],
        ]);
        abort_if(InstitutionOnboardingRequest::query()->where('requester_user_id',$request->user()->id)->whereIn('status',['pending','in_review'])->exists(),422,'You already have an institution onboarding request awaiting review.');

        if($data['request_type']==='claim_existing'){
            $university=University::findOrFail((int)$data['university_id']);
            $this->adoption->createClaim($request->user(),$university,$data);
        } else {
            if($match=$this->adoption->findPotentialMatch($data)){
                return back()->withInput()->withErrors(['institution_name'=>"A possible existing institution was found: {$match->name}. Search and claim that catalogue record instead of creating a duplicate."]);
            }
            InstitutionOnboardingRequest::create($data + ['requester_user_id'=>$request->user()->id,'request_type'=>'create_new','status'=>'pending','metadata'=>['evidence'=>$data['evidence']??null]]);
        }
        return back()->with('success','Your institution request has been submitted for Platform Admin verification.');
    }

    public function submitDepartmentRequest(Request $request): RedirectResponse
    {
        abort_unless(in_array($request->user()->account_type,['department_representative','lecturer','academic_staff'],true) || $request->user()->isDepartmentAdmin(),403);
        $data=$request->validate([
            'university_id'=>['required','integer','exists:universities,id'],'faculty_id'=>['nullable','integer','exists:faculties,id'],'department_id'=>['nullable','integer','exists:departments,id'],
            'department_name'=>['required_without:department_id','nullable','string','max:255'],'department_code'=>['nullable','string','max:30'],
            'representative_title'=>['required','string','max:150'],'evidence'=>['nullable','string','max:3000'],
        ]);
        $university=University::findOrFail((int)$data['university_id']);
        $faculty=!empty($data['faculty_id'])?Faculty::findOrFail((int)$data['faculty_id']):null;
        $department=!empty($data['department_id'])?Department::with('faculty')->findOrFail((int)$data['department_id']):null;
        $this->adoption->createDepartmentAdoption($request->user(),$university,$faculty,$department,$data);
        return back()->with('success','Department adoption request submitted. Your department can be activated independently after Platform Admin verification.');
    }

    public function institutionDashboard(Request $request): View
    {
        abort_unless($request->user()->isUniversityAdmin(),403);
        $university=$request->user()->university; abort_if(!$university,403,'Your account is not linked to an institution.');
        $university->load(['faculties.departments']);
        $account=CommercialAccount::query()->where('university_id',$university->id)->first();
        $adminCount=User::query()->where('university_id',$university->id)->whereIn('role',['university_admin','department_admin'])->count();
        $studentCount=User::query()->where('university_id',$university->id)->where('role','student')->count();
        $lecturerCount=User::query()->where('university_id',$university->id)->where('role','lecturer')->count();
        $pendingAffiliations=InstitutionAccessRequest::query()->where('university_id',$university->id)->whereIn('status',['pending','in_review'])->count();
        return view('institution.setup',compact('university','account','adminCount','studentCount','lecturerCount','pendingAffiliations'));
    }

    public function adminIndex(Request $request): View
    {
        abort_unless($request->user()->isSuperAdmin(),403);
        $requests=InstitutionOnboardingRequest::query()->with(['requester','university','reviewer'])->latest()->paginate(20,['*'],'institution_page');
        $departmentRequests=DepartmentAdoptionRequest::query()->with(['requester','university','department','reviewer'])->latest()->paginate(20,['*'],'department_page');
        $adminAccessRequests=InstitutionAccessRequest::query()->with(['user','university','department'])->whereIn('requested_role',['university_admin','department_admin'])->whereIn('status',['pending','in_review'])->latest()->limit(30)->get();
        $universities=University::query()->withCount(['faculties','users'])->latest()->limit(100)->get();
        return view('admin.institution-onboarding.index',compact('requests','departmentRequests','adminAccessRequests','universities'));
    }

    public function linkExisting(Request $request, InstitutionOnboardingRequest $onboardingRequest): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(),403);
        $data=$request->validate(['university_id'=>['required','integer','exists:universities,id'],'review_note'=>['nullable','string','max:2000']]);
        $university=University::findOrFail((int)$data['university_id']);
        abort_if($university->adoption_status==='active',422,'This institution is already active. Use access verification instead.');
        abort_if(InstitutionOnboardingRequest::query()->whereKeyNot($onboardingRequest->id)->where('university_id',$university->id)->whereIn('status',['pending','in_review'])->exists(),422,'Another claim is already pending for this institution.');
        $onboardingRequest->update(['university_id'=>$university->id,'institution_name'=>$university->name,'request_type'=>'claim_existing','status'=>'in_review','review_note'=>$data['review_note']??'Linked to existing catalogue institution.']);
        $university->update(['adoption_status'=>'claim_pending']);
        return back()->with('success','Request linked to the existing institution.');
    }

    public function requestMoreInformation(Request $request, InstitutionOnboardingRequest $onboardingRequest): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(),403);
        $data=$request->validate(['review_note'=>['required','string','max:2000']]);
        $onboardingRequest->update(['status'=>'in_review','review_note'=>$data['review_note'],'reviewed_by_user_id'=>$request->user()->id]);
        $this->notifications->send($onboardingRequest->requester,'institution_claim_information_required','More information required',$data['review_note'],['request_id'=>$onboardingRequest->id]);
        return back()->with('success','Representative has been asked for more information.');
    }

    public function reject(Request $request, InstitutionOnboardingRequest $onboardingRequest): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(),403);
        $data=$request->validate(['review_note'=>['required','string','max:2000']]);
        $onboardingRequest->update(['status'=>'rejected','review_note'=>$data['review_note'],'reviewed_by_user_id'=>$request->user()->id,'reviewed_at'=>now()]);
        if($onboardingRequest->university && $onboardingRequest->university->adoption_status==='claim_pending') $onboardingRequest->university->update(['adoption_status'=>'unclaimed']);
        $this->notifications->send($onboardingRequest->requester,'institution_claim_rejected','Institution onboarding request reviewed',$data['review_note'],['request_id'=>$onboardingRequest->id]);
        return back()->with('success','Institution request rejected.');
    }

    public function approve(Request $request, InstitutionOnboardingRequest $onboardingRequest): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(),403);
        abort_if(!in_array($onboardingRequest->status,['pending','in_review'],true),422,'This onboarding request has already been reviewed.');
        $data=$request->validate(['review_note'=>['nullable','string','max:2000']]);
        if(!$onboardingRequest->university_id && ($match=$this->adoption->findPotentialMatch($onboardingRequest->toArray()))) {
            return back()->withErrors(['institution'=>"Possible duplicate institution found: {$match->name}. Link this request to that institution before approval."]);
        }
        $university=DB::transaction(function()use($onboardingRequest,$request,$data){
            $u=$onboardingRequest->university_id ? University::query()->lockForUpdate()->findOrFail($onboardingRequest->university_id) : University::create([
                'name'=>$onboardingRequest->institution_name,'short_name'=>$onboardingRequest->short_name ?: Str::limit($onboardingRequest->institution_name,50,''),
                'code'=>$this->uniqueInstitutionCode($onboardingRequest),'institution_type'=>$onboardingRequest->institution_type,'ownership'=>$onboardingRequest->ownership,'state'=>$onboardingRequest->state,
                'regulator'=>$onboardingRequest->regulator,'email'=>$onboardingRequest->email,'phone'=>$onboardingRequest->phone,'website'=>$onboardingRequest->website,'address'=>$onboardingRequest->address,
                'timezone'=>'Africa/Lagos','is_active'=>true,'adoption_status'=>'claim_verified','claimed_at'=>now(),
            ]);
            abort_if($u->adoption_status==='active',422,'This institution is already active.');
            $u->forceFill(['adoption_status'=>'setup_in_progress','claimed_at'=>$u->claimed_at ?: now(),'is_active'=>true])->save();
            $user=$onboardingRequest->requester()->lockForUpdate()->firstOrFail();
            $this->adoption->verifyAffiliation($user,$u,null,'university_admin',$request->user(),'institution_claim',$data['review_note']??null);
            $onboardingRequest->forceFill(['university_id'=>$u->id,'status'=>'approved','reviewed_by_user_id'=>$request->user()->id,'reviewed_at'=>now(),'review_note'=>$data['review_note']??null])->save();
            return $u;
        },3);
        Password::sendResetLink(['email'=>$onboardingRequest->requester->email]);
        $this->notifications->send($onboardingRequest->requester,'institution_claim_approved','Institution claim approved',"{$university->name} is ready for guided setup.",['university_id'=>$university->id]);
        return redirect()->route('admin.institution-onboarding.setup',$university)->with('success','Institution approved. Complete guided setup before official activation.');
    }

    public function approveDepartment(Request $request, DepartmentAdoptionRequest $departmentRequest): RedirectResponse
    {
        $data=$request->validate(['review_note'=>['nullable','string','max:2000']]);
        $department=$this->adoption->approveDepartmentAdoption($departmentRequest,$request->user(),$data['review_note']??null);
        return back()->with('success',"{$department->name} is now active for department-first adoption.");
    }

    public function rejectDepartment(Request $request, DepartmentAdoptionRequest $departmentRequest): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(),403);
        $data=$request->validate(['review_note'=>['required','string','max:2000']]);
        $departmentRequest->update(['status'=>'rejected','review_note'=>$data['review_note'],'reviewed_by_user_id'=>$request->user()->id,'reviewed_at'=>now()]);
        $this->notifications->send($departmentRequest->requester,'department_adoption_rejected','Department adoption request reviewed',$data['review_note'],['request_id'=>$departmentRequest->id]);
        return back()->with('success','Department request rejected.');
    }

    public function setup(Request $request, University $university): View
    {
        abort_unless($request->user()->isSuperAdmin(),403);
        $university->load(['faculties.departments']);
        $account=CommercialAccount::query()->where('university_id',$university->id)->first();
        $adminCount=User::query()->where('university_id',$university->id)->whereIn('role',['university_admin','department_admin'])->count();
        $studentCount=User::query()->where('university_id',$university->id)->where('role','student')->count();
        $lecturerCount=User::query()->where('university_id',$university->id)->where('role','lecturer')->count();
        $candidateCount=\App\Models\UserInstitutionAffiliation::query()->where('university_id',$university->id)->whereIn('status',['self_declared','pending_verification'])->count();
        return view('admin.institution-onboarding.setup',compact('university','account','adminCount','studentCount','lecturerCount','candidateCount'));
    }

    public function addStructure(Request $request, University $university): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(),403);
        $data=$request->validate(['faculty_name'=>['required','string','max:255'],'faculty_code'=>['nullable','string','max:30'],'department_name'=>['nullable','string','max:255'],'department_code'=>['nullable','string','max:30']]);
        DB::transaction(function()use($university,$data){$faculty=Faculty::firstOrCreate(['university_id'=>$university->id,'name'=>$data['faculty_name']],['short_name'=>$data['faculty_name'],'code'=>$data['faculty_code']?:null,'is_active'=>true]);if(!empty($data['department_name']))Department::firstOrCreate(['faculty_id'=>$faculty->id,'name'=>$data['department_name']],['short_name'=>$data['department_name'],'code'=>$data['department_code']?:null,'is_active'=>true,'adoption_status'=>$university->adoption_status==='active'?'institution_managed':'unclaimed']);});
        return back()->with('success','Academic structure saved.');
    }

    public function saveCommercial(Request $request, University $university): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(),403);
        $data=$request->validate(['currency'=>['required','string','size:3'],'max_administrators'=>['nullable','integer','min:1','max:10000'],'student_semester_fee'=>['nullable','regex:/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/'],'invoice_grace_days'=>['nullable','integer','min:0','max:90'],'sponsor_ai_usage'=>['nullable','boolean']]);
        $account=CommercialAccount::firstOrNew(['university_id'=>$university->id]);$metadata=$account->metadata?:[];$metadata['max_administrators']=$data['max_administrators']??null;$metadata['student_semester_fee_minor']=isset($data['student_semester_fee'])?\App\Support\Money::toMinor((string)$data['student_semester_fee']):null;$metadata['invoice_grace_days']=$data['invoice_grace_days']??14;$metadata['sponsor_ai_usage']=$request->boolean('sponsor_ai_usage');$account->fill(['name'=>$university->name.' Commercial Account','currency'=>strtoupper($data['currency']),'status'=>'active','metadata'=>$metadata]);$account->save();
        return back()->with('success','Commercial account configured. Sponsorship becomes available only to verified members after institution activation.');
    }

    public function activate(Request $request, University $university): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(),403);$university->load('faculties.departments');
        abort_unless(User::query()->where('university_id',$university->id)->where('role','university_admin')->exists(),422,'Add a University Administrator before activation.');
        abort_unless($university->faculties->isNotEmpty(),422,'Add at least one faculty or school before activation.');
        $university->update(['is_active'=>true,'adoption_status'=>'active','adopted_at'=>now()]);
        $this->adoption->migrateIndependentDepartments($university,$request->user());
        return redirect()->route('admin.universities.edit',$university)->with('success','Institution onboarding completed. Independent departments can now transition into the official institution and verified members can receive eligible institution benefits.');
    }

    public function proposeDomain(Request $request, University $university): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin() || ($request->user()->isUniversityAdmin() && $request->user()->university_id===$university->id),403);
        $data=$request->validate(['domain'=>['required','string','max:255']]);$this->adoption->proposeEmailDomain($university,$data['domain'],$request->user());
        return back()->with('success','Institution email domain submitted for Platform Admin verification.');
    }

    public function verifyDomain(Request $request, InstitutionEmailDomain $domain): RedirectResponse
    { $this->adoption->verifyEmailDomain($domain,$request->user()); return back()->with('success','Institution email domain verified.'); }

    private function uniqueInstitutionCode(InstitutionOnboardingRequest $r): string
    {
        $base=strtoupper(Str::substr(Str::replace([' ','-'],'',$r->code ?: $r->short_name ?: $r->institution_name),0,10));$base=$base?:'INST';$candidate=$base;$i=2;
        while(University::query()->where('code',$candidate)->exists()){$suffix=(string)$i++;$candidate=Str::substr($base,0,max(1,10-strlen($suffix))).$suffix;}
        return $candidate;
    }
}
