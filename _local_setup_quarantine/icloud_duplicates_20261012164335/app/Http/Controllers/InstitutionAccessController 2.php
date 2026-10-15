<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\InstitutionAccessRequest;
use App\Models\University;
use App\Models\UserInstitutionAffiliation;
use App\Services\IndependentAcademicContextService;
use App\Services\InstitutionAdoptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstitutionAccessController extends Controller
{
    public function __construct(private readonly InstitutionAdoptionService $adoption) {}

    public function show(Request $request): View
    {
        $user=$request->user();
        $universities=University::query()->where('is_active',true)->where('code','!=',IndependentAcademicContextService::UNIVERSITY_CODE)->orderBy('name')->get(['id','name','short_name','adoption_status']);
        $requests=InstitutionAccessRequest::query()->where('user_id',$user->id)->with(['university','department'])->latest()->get();
        $affiliations=UserInstitutionAffiliation::query()->where('user_id',$user->id)->with(['university','department'])->latest()->get();
        return view('institution.access',compact('universities','requests','affiliations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data=$request->validate([
            'university_id'=>['required','integer','exists:universities,id'],
            'department_id'=>['nullable','integer','exists:departments,id'],
            'requested_role'=>['required',Rule::in(['student','lecturer','researcher','department_admin','university_admin','staff'])],
            'identifier'=>['nullable','string','max:150'],
            'verification_method'=>['nullable',Rule::in(['admin_review','institution_email','student_id','staff_id','invitation'])],
        ]);
        $university=University::findOrFail((int)$data['university_id']);
        $department=!empty($data['department_id'])?Department::with('faculty')->findOrFail((int)$data['department_id']):null;
        if(in_array($data['requested_role'],['department_admin'],true) && !$department) return back()->withErrors(['department_id'=>'Select the department you want to administer.'])->withInput();
        $method=$data['verification_method']??'admin_review';
        if($method==='institution_email' && !$this->adoption->emailMatchesVerifiedDomain($request->user(),$university)) return back()->withErrors(['verification_method'=>'Your account email does not match a Platform-verified domain for this institution.'])->withInput();
        $this->adoption->requestAffiliationVerification($request->user(),$university,$department,$data['requested_role'],$data['identifier']??null,$method);
        return back()->with('success','Institution access request submitted. Your existing AcadFlow account and history will be preserved after verification.');
    }

    public function adminIndex(Request $request): View
    {
        abort_unless($request->user()->isAdmin(),403);
        $actor=$request->user();
        $query=InstitutionAccessRequest::query()->with(['user','university','department'])->whereIn('status',['pending','in_review']);
        if($actor->isUniversityAdmin()) $query->where('university_id',$actor->university_id)->whereNotIn('requested_role',['university_admin','department_admin']);
        if($actor->isDepartmentAdmin()) $query->where('university_id',$actor->university_id)->where('department_id',$actor->department_id)->whereIn('requested_role',['student','lecturer','researcher','staff']);
        $requests=$query->latest()->paginate(30);
        return view('admin.institution-access.index',compact('requests'));
    }

    public function approve(Request $request, InstitutionAccessRequest $accessRequest): RedirectResponse
    {
        $data=$request->validate(['review_note'=>['nullable','string','max:2000']]);
        $this->adoption->approveAccessRequest($accessRequest,$request->user(),$data['review_note']??null);
        return back()->with('success','Affiliation verified. The existing account is now connected to the official institution structure.');
    }

    public function reject(Request $request, InstitutionAccessRequest $accessRequest): RedirectResponse
    {
        $data=$request->validate(['review_note'=>['required','string','max:2000']]);
        $this->adoption->rejectAccessRequest($accessRequest,$request->user(),$data['review_note']);
        return back()->with('success','Access request rejected.');
    }
}
