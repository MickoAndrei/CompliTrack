<?php
namespace Database\Seeders\V2;

use App\Models\{ArchiveDocument, Audit, AuditLog, Campus, College, ComplianceCheck, Department, Notification, QmsCategory, Role, User};
use App\Services\{ArchiveEncryptionService, CounterService, QmsCycleRepository};
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB, Hash, Storage};
use Illuminate\Support\Str;
use MongoDB\BSON\ObjectId;
use RuntimeException;

/** Isolated, fictional CET demonstration. No mail, network notification dispatch or legacy import. */
final class V2DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;
    private College $college;
    private string $password;
    private function id($model): ObjectId { return new ObjectId((string) $model->getKey()); }
    private function account(string $name, int $role, ?Department $unit = null, array $assigned = []): User
    {
        $email = strtolower(str_replace([' ', '.'], ['.', ''], $name)).'@example.test';
        return User::create(['userCode'=>app(CounterService::class)->next(['SA','ADM','AUD','ATE'][$role]), 'fullName'=>$name, 'email'=>$email, 'password'=>Hash::make($this->password), 'roleId'=>$role, 'roleSlug'=>['super_admin','admin','auditor','auditee'][$role], 'departmentId'=>$unit ? $this->id($unit) : null, 'assignedDepartmentIds'=>$assigned, 'isActive'=>true, 'archiveResponsibility'=>$role===3 ? 'faculty_staff' : null]);
    }
    private function document(string $title, string $reference, Department $unit, User $owner, string $folder, array $headers, array $rows, string $format='pdf'): ArchiveDocument
    {
        $category = QmsCategory::where('name',$folder)->where('cycleKey',config('qms.currentAuditCycle'))->where('departmentId', $folder==='Approved Audit Plan'||$folder==='Consolidated CAR Log' ? null : $this->id($unit))->firstOrFail();
        $code=app(CounterService::class)->next('DOC',true);
        $bytes=$format==='xlsx' ? SeedDocumentWriter::xlsx($title,$headers,$rows) : SeedDocumentWriter::pdf($title,$reference,$headers,$rows);
        $crypto=app(ArchiveEncryptionService::class); $enc=$crypto->encrypt($bytes); $path='archives/v2/'.$code.'.enc';
        if (!Storage::disk('local')->put($path,$enc['ciphertext']) || $crypto->decrypt($enc['ciphertext'],$enc['iv'],$enc['tag'],$enc['keyId'])!==$bytes) { throw new RuntimeException('Archive write or verification failed.'); }
        return ArchiveDocument::create(['fileCode'=>$code,'originalName'=>$title.'.'.$format,'title'=>$title,'documentReference'=>$reference,'referenceStandard'=>'ISO 9001:2015','recordType'=>'file','departmentId'=>$this->id($unit),'collegeId'=>$this->id($this->college),'uploadedByUserId'=>$this->id($owner),'categoryId'=>$this->id($category),'ownershipVerified'=>true,'visibility'=>in_array($folder,['Approved Audit Plan','Consolidated CAR Log'])?'college':'department','downloadAllowed'=>true,'copyAuthorized'=>true,'signatureRequired'=>false,'signatureStatus'=>'not-required','versionGroupId'=>new ObjectId(),'versionNumber'=>1,'encryptedPath'=>$path,'isEncrypted'=>true,'encryptionIv'=>$enc['iv'],'encryptionTag'=>$enc['tag'],'encryptionKeyId'=>$enc['keyId'],'checksum'=>hash('sha256',$bytes),'fileSize'=>strlen($bytes),'mimeType'=>$format==='xlsx'?'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':'application/pdf']);
    }
    public function run(): void
    {
        $db=DB::connection('mongodb')->getMongoDB();
        $dsn=(string)config('database.connections.mongodb.dsn');
        if (!config('v2.enabled') || !app()->environment(['local','development','testing']) || config('database.default')!=='mongodb' || $db->getDatabaseName()!=='complitrack_v2' || !preg_match('~^mongodb://(?:127\.0\.0\.1|localhost)(?::[0-9]+)?(?:/|$)~',$dsn) || !str_ends_with(config('filesystems.disks.local.root'),'private-v2')) { throw new RuntimeException('V2 seeding requires explicit opt-in and isolated local complitrack_v2 database and storage.'); }
        $this->password=(string)config('v2.seed_password');
        if (strlen($this->password)<16 || !config('app.key') || !config('services.complitrack.encryption_key')) { throw new RuntimeException('Set a private 16+ character seed password and new application/archive keys first.'); }
        $marker='v2-cet-2026'; $run=$db->seed_runs->findOne(['_id'=>$marker]);
        if (($run['status']??null)==='complete') { $this->command?->info('V2 fixture already installed; no changes.'); return; }
        foreach (['users','audits','compliance_checks','archive_documents','notifications','audit_logs'] as $name) { if ($db->selectCollection($name)->countDocuments()>0) { throw new RuntimeException('Database contains existing or partially seeded records. Use a new isolated V2 database; no automatic deletion.'); } }
        if ($run) { throw new RuntimeException('Previous seeding did not finish. Inspect the isolated environment before retrying.'); }
        $db->seed_runs->insertOne(['_id'=>$marker,'status'=>'started','simulation'=>true]);
        try { $this->seedRecords(); $db->seed_runs->updateOne(['_id'=>$marker],['$set'=>['status'=>'complete']]); }
        catch (\Throwable $e) { $db->seed_runs->updateOne(['_id'=>$marker],['$set'=>['status'=>'failed']]); throw $e; }
    }
    private function seedRecords(): void
    {
        $this->call(\Database\Seeders\RoleSeeder::class);
        Role::where('roleId',0)->firstOrFail()->forceFill(['permissions'=>['*','audit_plans.approve']])->save();
        Role::where('roleId',2)->firstOrFail()->forceFill(['permissions'=>['cars.verify','cars.close']])->save();
        $campus=Campus::firstOrCreate(['campusKey'=>'claveria'],['campusName'=>'Claveria','isActive'=>true]);
        $this->college=College::firstOrCreate(['collegeCode'=>'CET'],['collegeName'=>config('qms.collegeName'),'campusName'=>'Claveria','campusId'=>$this->id($campus),'isActive'=>true]);
        $units=[]; foreach (config('qms.departments')+['CET-DEAN'=>'Office of the Dean'] as $code=>$name) {
            $unit=Department::firstOrCreate(['departmentCode'=>$code],['departmentName'=>$name,'collegeName'=>'CET','collegeId'=>$this->id($this->college),'campusName'=>'Claveria','unitType'=>$code==='CET-DEAN'?'college_office':'academic_department','ownershipVerified'=>true,'isActive'=>true]);
            $unit->forceFill(['campusId'=>$this->id($campus)])->save(); $units[]=$unit;
        }
        $dean=array_pop($units); $super=$this->account('Rafael M Villareal',0); $atls=[$this->account('Angela R Mendoza',1,$dean),$this->account('Maribel T Reyes',1,$dean)];
        $first=['Adrian','Patricia','Gabriel','Lianne','Marco','Elena','Noel','Teresa','Vincent','Carla','Camille','Daniel','Janine','Alvin','Bianca','Cedric','Diana','Erwin','Faith','Gino','Hazel','Ivan','Joyce','Kevin','Lea','Marlon','Nina','Oscar','Paula','Quinn','Rina','Samuel','Tessa','Ulysses','Vera','Warren','Yvette','Zandro','Aileen','Bryan','Celine','Darren','Elsa','Felix','Grace'];
        $last=['Navarro','Soriano','Velasco','Dela Cruz','Ramos','Santos','Cruz','Salazar','Aguilar','Flores','Rosales','Manalo','Bautista','Molina','Castillo','Reyes','Abad','Domingo','Lim','Aquino','Garcia','Rivera','Torres','Luna','Santiago','Pascual','Valdez','De Leon','Ocampo','Tan','Lorenzo','Mercado','Cabrera','Gonzales','Chua','Robles','Mendoza','Barrera','Pineda','Del Rosario','Bernardo','Silva','Marquez','Roldan','Mateo'];
        $auditors=[];$auditees=[]; for($i=0;$i<10;$i++){ $home=$units[$i%5];$targets=array_values(array_map(fn($u)=>$this->id($u),array_filter($units,fn($u)=>(string)$u->getKey()!==(string)$home->getKey())));$auditors[]=$this->account($first[$i].' '.chr(65+$i).' '.$last[$i],2,$home,$targets); }
        for($i=0;$i<35;$i++){ $u=$units[intdiv($i,7)];$person=$this->account($first[$i+10].' '.chr(65+($i%26)).' '.$last[$i+10],3,$u);$auditees[intdiv($i,7)][]=$person; if($i%7===0){$person->forceFill(['archiveResponsibility'=>'department_head','archiveUnitIds'=>[$this->id($u)]])->save();$u->forceFill(['headUserId'=>$this->id($person)])->save();}}
        app(QmsCycleRepository::class)->sync();
        $carRows=[];$car=0;$idx=0;
        $processes=['Instruction and curriculum review','Records and laboratory controls'];
        $issues=['Syllabus approval record lacks the final review date.','Equipment inspection log omits the assigned reviewer.','Training attendance register has incomplete attendance totals.','Document revision register does not identify the superseded copy.','Assessment moderation record lacks a supporting review entry.'];
        $base=now()->startOfDay();
        for($cycle=0;$cycle<4;$cycle++){
            $start=$base->copy()->subDays(120-$cycle*30);$end=$start->copy()->addDays(12);$approved=$start->copy()->subDays(7)->utc()->toIso8601String();$atl=$atls[$cycle%2];
            $schedule=[];foreach($units as $ui=>$unit){foreach($processes as $pi=>$process){$schedule[]=[$start->copy()->addDays($ui*2+$pi)->toDateString(),$unit->departmentName,$process,$auditors[($ui+1+$pi*5)%10]->fullName];}}
            $plan=$this->document('Internal Quality Audit Plan - '.$start->format('F Y'),'FM-USTP-007-2',$dean,$atl,'Approved Audit Plan',['Schedule','Department','Process','Assigned auditor'],$schedule);
            $header=['unitIds'=>array_map(fn($u)=>(string)$u->getKey(),$units),'reviewerIds'=>[(string)$atl->getKey()],'scope'=>'CET instructional delivery and documented process controls','criteria'=>'ISO 9001:2015 and registered departmental procedures','scheduleStart'=>$start->toDateString(),'scheduleEnd'=>$end->toDateString(),'procedureReference'=>'IQA independence and conflict-of-interest review procedure'];
            $authorization=['status'=>'authorized']+$header+['approvedBy'=>$this->id($super),'approvedAt'=>$approved,'approvalVersion'=>3];$authorization['unitIds']=array_map(fn($u)=>$this->id($u),$units);$authorization['reviewerIds']=[$this->id($atl)];
            $plan->forceFill(['auditPlanClassification'=>['purpose'=>'audit-plan','verifiedBy'=>(string)$atl->getKey(),'reason'=>'Controlled plan classification for the synthetic audit cycle','at'=>$approved,'documentVersion'=>1,'checksum'=>$plan->checksum],'auditPlan'=>['status'=>'accepted','version'=>3,'header'=>$header,'preparedBy'=>$this->id($atl),'documentVersion'=>1,'documentChecksum'=>$plan->checksum,'approvedBy'=>$this->id($super),'approvedAt'=>$approved,'updatedAt'=>$approved,'history'=>[['decision'=>'approve','actorId'=>$this->id($super),'actorName'=>$super->fullName,'at'=>$approved,'remarks'=>'Plan reviewed and approved.']]],'assignmentAuthorization'=>$authorization])->save();
            foreach($units as $ui=>$unit){foreach($processes as $pi=>$process){$idx++;$auditor=$auditors[($ui+1+$pi*5)%10];$person=$auditees[$ui][($cycle*2+$pi)%7];$date=$start->copy()->addDays($ui*2+$pi);$when=$date->copy()->utc()->toIso8601String();$status=$cycle<3?'accepted':['accepted','accepted','accepted','submitted','submitted','returned','returned','ready','ready','draft'][$ui*2+$pi];
                $doc=$this->document($unit->departmentCode.' - '.$process.' - '.$date->format('Y-m-d'),'DPM-'.$unit->departmentCode.'-0'.($pi+1),$unit,$person,'Digital Files',['Process','Responsible faculty','Review date','Record'],[[$process,$person->fullName,$date->toDateString(),'Departmental records and process documentation reviewed.']]);
                $items=[]; $finding=$status==='accepted'&&$car<24; foreach(['Is the process documented and approved?','Are records maintained and traceable?','Are improvement actions recorded?'] as $j=>$question){$result=$status==='draft'||($status==='ready'&&$j>0)?null:($j===1&&$finding?'N':($j===2&&$idx%3===0?'O':'C'));$items[]=['id'=>(string)Str::uuid(),'question'=>$question,'reference'=>'ISO 9001:2015, 7.5','result'=>$result,'remarks'=>$result==='N'?$issues[$ui]:($result==='O'?'Opportunity to improve the document review schedule.':($result==='C'?'Records reviewed; requirements met.':'')),'finding'=>$result==='N'?$issues[$ui]:null,'evidence'=>$result===null?[]:[['method'=>'digital','reference'=>$doc->documentReference,'archiveId'=>(string)$doc->getKey(),'notes'=>'Reviewed controlled process records.','reviewedBy'=>(string)$auditor->getKey(),'reviewedAt'=>$when]],'evaluatedBy'=>$result===null?null:(string)$auditor->getKey(),'evaluatedAt'=>$result===null?null:$when];}
                $history=[['action'=>'assigned','actor'=>(string)$atl->getKey(),'at'=>$approved]];if($status!=='draft')$history[]=['action'=>'ready','actor'=>(string)$auditor->getKey(),'at'=>$when];if(in_array($status,['accepted','submitted','returned']))$history[]=['action'=>'submit','actor'=>(string)$auditor->getKey(),'at'=>$when];if(in_array($status,['accepted','returned']))$history[]=['action'=>$status==='accepted'?'accept':'return','actor'=>(string)$atl->getKey(),'at'=>$date->copy()->addDay()->utc()->toIso8601String(),'notes'=>$status==='accepted'?'Reviewed and accepted.':'Clarify supporting references.'];
                $audit=Audit::create(['auditCode'=>app(CounterService::class)->next('IQA',true),'assignmentKey'=>hash('sha256',$plan->getKey().'|'.$ui.'|'.$pi),'planId'=>$this->id($plan),'planTitle'=>$plan->title,'planAuthorizationSnapshot'=>$authorization,'title'=>$process,'scope'=>$header['scope'],'criteria'=>$header['criteria'],'departmentId'=>$this->id($unit),'departmentName'=>$unit->departmentName,'auditorId'=>$this->id($auditor),'reviewerId'=>$this->id($atl),'schedule'=>$date->toDateString(),'independenceConfirmed'=>true,'independenceNotes'=>'Cross-department assignment; no recorded conflict.','status'=>$status,'version'=>count($history)+1,'items'=>$items,'interviews'=>[],'history'=>$history,'participants'=>[['userId'=>$this->id($person),'selectedBy'=>$this->id($auditor),'selectedAt'=>$when]],'evidenceRequests'=>[]]);
                if($status==='accepted'){$report=$this->document('Audit Report - '.$audit->auditCode,$audit->auditCode,$unit,$auditor,'Released Audit Results',['Question','Outcome','Remarks'],array_map(fn($item)=>[$item['question'],$item['result'],$item['remarks']],$items));$report->forceFill(['auditId'=>$this->id($audit)])->save();$audit->forceFill(['reportDocumentId'=>$this->id($report)])->save();}
                if($finding){$car++;$carNo=sprintf('CAR-%s-%04d',$base->format('Y'),$car);$state=$car<=6?'open':($car<=11?'in_progress':($car<=15?'awaiting_verification':'closed'));$opened=$date->copy()->addDays(2);$due=$opened->copy()->addDays(14);$closed=$state==='closed'?$opened->copy()->addDays(6):null;$timeline=[['status'=>'open','changedByUserId'=>$this->id($auditor),'timestamp'=>$opened->utc()->toIso8601String(),'remarks'=>'Accepted nonconformity assigned for correction.']];if($state!=='open')$timeline[]=['status'=>'in_progress','changedByUserId'=>$this->id($person),'timestamp'=>$opened->copy()->addDay()->utc()->toIso8601String(),'remarks'=>'Corrective action initiated.'];if(in_array($state,['awaiting_verification','closed']))$timeline[]=['status'=>'awaiting_verification','changedByUserId'=>$this->id($person),'timestamp'=>$opened->copy()->addDays(3)->utc()->toIso8601String(),'remarks'=>'Corrective record submitted for verification.'];if($closed)$timeline[]=['status'=>'closed','changedByUserId'=>$this->id($auditor),'timestamp'=>$closed->utc()->toIso8601String(),'remarks'=>'PASS: updated record reviewed; missing control verified.'];
                    $log=['carNo'=>$carNo,'dateSubmitted'=>$opened->toDateString(),'type'=>'Nonconformity','source'=>'Internal Quality Audit','process'=>$process,'submittedByName'=>$auditor->fullName,'issue'=>$issues[$ui],'assignedTo'=>$person->fullName,'targetClosedOutDate'=>$due->toDateString(),'resultsPass'=>$closed?'Verified corrected record':null,'resultsFail'=>null,'resultsAction'=>$closed?null:($state==='awaiting_verification'?'Review corrective evidence':null),'remarks'=>$closed?'Effectiveness verified.':'Follow-up assigned.'];
                    $links=[$this->id($doc)];if(in_array($state,['awaiting_verification','closed'])){$response=$this->document('Corrective Action Response - '.$carNo,$carNo,$unit,$person,'Corrective Evidence',['Issue','Action','Responsible person','Submitted'],[[$issues[$ui],'Updated record and added documented review.',$person->fullName,$opened->copy()->addDays(3)->toDateString()]]);$links[]=$this->id($response);}
                    $check=ComplianceCheck::create(['checkCode'=>app(CounterService::class)->next('CHK',true),'departmentId'=>$this->id($unit),'auditorId'=>$this->id($auditor),'auditeeId'=>$this->id($person),'referenceStandard'=>'ISO 9001:2015','result'=>'non_compliant','remarks'=>$issues[$ui],'correctiveStatus'=>$state,'statusHistory'=>$timeline,'linkedArchiveIds'=>$links,'dueDate'=>$due,'closedAt'=>$closed,'carLog'=>$log]);$check->forceFill(['sourceAuditId'=>$this->id($audit),'sourceItemId'=>$items[1]['id']])->save();
                    $carRows[]=[$carNo,$opened->toDateString(),'Nonconformity','IQA',$process,$auditor->fullName,$issues[$ui],$person->fullName,$due->toDateString(),$closed?'Pass':'','',$log['resultsAction'],$closed?->toDateString()??'',$log['remarks']];
                    Notification::create(['recipientUserId'=>$this->id($person),'senderUserId'=>$this->id($auditor),'type'=>'corrective_action_created','title'=>'Corrective action assigned','message'=>$carNo.' - '.$issues[$ui],'relatedCheckId'=>$this->id($check),'isRead'=>$state==='closed','isCleared'=>false,'dedupeKey'=>'v2:'.$carNo,'destination'=>'/auditee/my-findings'])->forceFill(['createdAt'=>$opened,'realtimeDeliveredAt'=>$base])->save();
                }
                Notification::create(['recipientUserId'=>$this->id($auditor),'senderUserId'=>$this->id($atl),'type'=>'audit_assigned','title'=>'Audit assignment','message'=>$audit->auditCode.' - '.$unit->departmentName,'isRead'=>$status==='accepted','isCleared'=>false,'dedupeKey'=>'v2:'.$audit->auditCode,'destination'=>'/audits'])->forceFill(['createdAt'=>$date,'realtimeDeliveredAt'=>$base])->save();
                AuditLog::create(['userId'=>$this->id($auditor),'userName'=>$auditor->fullName,'action'=>'Audit '.$status,'entityType'=>'audit','entityId'=>$this->id($audit),'category'=>'audit_workflow','outcome'=>'success'])->forceFill(['createdAt'=>$date])->save();
            }}
        }
        $heads=['CAR No.','Date Submitted','Type','Source','Process','Name of Person Submitting CAR','Issue','Assigned To','Target Closed-Out Date','Results - Pass','Results - Fail','Results - Action','Actual Closed-Out Date','Remarks'];
        foreach(['pdf','xlsx'] as $format)$this->document('Corrective Action Register - '.$base->format('Y'),'FM-USTP-009-2',$dean,$atls[0],'Consolidated CAR Log',$heads,$carRows,$format);
        app(QmsCycleRepository::class)->sync();
        if(Audit::count()!==40||User::count()!==48||ComplianceCheck::count()!==24)throw new RuntimeException('V2 fixture count verification failed.');
        $this->command?->info('Created 48 fictional accounts, 40 assignments, 24 CARs and '.ArchiveDocument::count().' encrypted documents. Login: rafael.m.villareal@example.test (configured seed password).');
    }
}
