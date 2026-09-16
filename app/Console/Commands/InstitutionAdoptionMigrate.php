<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\University;
use App\Models\User;
use App\Models\UserInstitutionAffiliation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InstitutionAdoptionMigrate extends Command
{
    protected $signature = 'acadflow:institution-adoption-migrate {--apply : Persist the proposed classifications}';
    protected $description = 'Safely classify legacy institutions, departments and user affiliations for progressive institution adoption. Defaults to dry-run.';

    public function handle(): int
    {
        $apply=(bool)$this->option('apply');
        $this->info($apply ? 'Applying progressive institution-adoption classifications.' : 'DRY RUN: no records will be changed. Use --apply after reviewing this output.');

        $institutionStats=['active'=>0,'unclaimed'=>0,'setup_in_progress'=>0];
        University::query()->withCount(['users'])->chunkById(100,function($universities)use(&$institutionStats,$apply){
            foreach($universities as $u){
                $hasUniversityAdmin=User::query()->where('university_id',$u->id)->where('role','university_admin')->exists();
                $status=$hasUniversityAdmin ? ((int)$u->faculties()->count()>0?'active':'setup_in_progress') : 'unclaimed';
                $institutionStats[$status]++;
                if($apply && !in_array($u->adoption_status,['claim_pending','claim_verified','setup_in_progress','active','suspended','rejected'],true)) $u->update(['adoption_status'=>$status,'adopted_at'=>$status==='active'?($u->adopted_at?:now()):null]);
            }
        });

        $affiliations=0;$verifiedAdmins=0;
        User::query()->whereNotNull('university_id')->chunkById(200,function($users)use(&$affiliations,&$verifiedAdmins,$apply){
            foreach($users as $user){
                $type=match($user->role){'student'=>'student','lecturer'=>'lecturer','department_admin'=>'department_admin','university_admin'=>'university_admin',default=>'staff'};
                $verified=in_array($user->role,['university_admin','department_admin'],true);
                $affiliations++; if($verified)$verifiedAdmins++;
                if($apply){
                    UserInstitutionAffiliation::query()->updateOrCreate(
                        ['user_id'=>$user->id,'university_id'=>$user->university_id,'affiliation_type'=>$type],
                        ['faculty_id'=>$user->faculty_id,'department_id'=>$user->department_id,'status'=>$verified?'verified':'self_declared','verification_method'=>$verified?'legacy_admin_role':null,'source'=>'legacy_migration','verified_by'=>$verified?$user->id:null,'verified_at'=>$verified?($user->updated_at?:now()):null]
                    );
                }
            }
        });

        $departments=0;
        Department::query()->chunkById(200,function($items)use(&$departments,$apply){foreach($items as $d){$departments++;if($apply && !$d->adoption_status)$d->update(['adoption_status'=>'unclaimed']);}});

        $this->table(['Classification','Count'],[
            ['Institutions → active',$institutionStats['active']],['Institutions → setup_in_progress',$institutionStats['setup_in_progress']],['Institutions → unclaimed',$institutionStats['unclaimed']],['Legacy affiliations',$affiliations],['Legacy admin affiliations verified',$verifiedAdmins],['Departments reviewed',$departments],
        ]);
        $this->line($apply?'Migration applied.':'Dry run complete. No data changed.');
        return self::SUCCESS;
    }
}
