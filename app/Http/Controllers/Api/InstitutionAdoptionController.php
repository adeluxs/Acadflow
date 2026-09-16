<?php

declare(strict_types=1);
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\University;
use App\Models\UserInstitutionAffiliation;
use App\Services\InstitutionAdoptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InstitutionAdoptionController extends Controller
{
    public function __construct(private readonly InstitutionAdoptionService $adoption) {}
    public function search(Request $request): JsonResponse
    {
        $data=$request->validate(['q'=>['required','string','min:2','max:120']]);
        $items=$this->adoption->searchInstitutions($data['q'])->map(fn($u)=>['id'=>$u->id,'name'=>$u->name,'short_name'=>$u->short_name,'code'=>$u->code,'state'=>$u->state,'type'=>$u->institution_type,'adoption_status'=>$this->adoption->institutionState($u)]);
        return response()->json(['success'=>true,'data'=>$items]);
    }
    public function status(Request $request): JsonResponse
    {
        $user=$request->user();
        $affiliations=UserInstitutionAffiliation::query()->where('user_id',$user->id)->with(['university:id,name,short_name,adoption_status','department:id,name'])->get();
        return response()->json(['success'=>true,'data'=>['university_id'=>$user->university_id,'department_id'=>$user->department_id,'can_receive_institution_benefits'=>$this->adoption->canReceiveInstitutionBenefits($user),'affiliations'=>$affiliations]]);
    }
    public function requestAccess(Request $request): JsonResponse
    {
        $data=$request->validate(['university_id'=>['required','integer','exists:universities,id'],'department_id'=>['nullable','integer','exists:departments,id'],'requested_role'=>['required',Rule::in(['student','lecturer','researcher','department_admin','university_admin','staff'])],'identifier'=>['nullable','string','max:150'],'verification_method'=>['nullable',Rule::in(['admin_review','institution_email','student_id','staff_id','invitation'])]]);
        $u=University::findOrFail((int)$data['university_id']);$d=!empty($data['department_id'])?Department::with('faculty')->findOrFail((int)$data['department_id']):null;
        $access=$this->adoption->requestAffiliationVerification($request->user(),$u,$d,$data['requested_role'],$data['identifier']??null,$data['verification_method']??'admin_review');
        return response()->json(['success'=>true,'message'=>'Institution access request submitted.','data'=>$access],201);
    }
}
