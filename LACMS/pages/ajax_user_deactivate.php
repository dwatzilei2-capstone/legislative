<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../includes/lacms_security.php';requireLacmsPermission('lacms.users.manage');if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');requireCsrf();$pdo=db();$id=(int)($_POST['user_id']??0);$mode=clean($_POST['mode']??'revoke_access');if($id<=0)jsonResponse(false,'Invalid user.');if($id===currentUserId()&&$mode==='deactivate_account')jsonResponse(false,'You cannot deactivate your own account.');
try{$pdo->beginTransaction();$q=$pdo->prepare('SELECT u.*,r.name role_name FROM users u LEFT JOIN roles r ON r.id=u.role_id WHERE u.id=:id AND u.deleted_at IS NULL FOR UPDATE');$q->execute([':id'=>$id]);$u=$q->fetch();if(!$u){$pdo->rollBack();jsonResponse(false,'User not found.');}
 if($u['role_name']==='Administrator'&&in_array($mode,['revoke_access','deactivate_account'],true)){
     $targetAccess=$pdo->prepare(
         "SELECT status FROM user_system_access
          WHERE user_id=:user AND system_id=:system LIMIT 1"
     );
     $targetAccess->execute([':user'=>$id,':system'=>lacmsSystemId()]);
     $targetAccessStatus=(string)($targetAccess->fetchColumn()?:'Inactive');

     if($u['status']==='Active'&&$targetAccessStatus==='Active'){
         $remaining=$pdo->prepare(
             "SELECT COUNT(*)
              FROM users ua
              JOIN roles ra ON ra.id=ua.role_id
              JOIN user_system_access usa
                ON usa.user_id=ua.id
               AND usa.system_id=:system
               AND usa.status='Active'
              WHERE ra.name='Administrator'
                AND ua.status='Active'
                AND ua.deleted_at IS NULL
                AND ua.id<>:user"
         );
         $remaining->execute([':system'=>lacmsSystemId(),':user'=>$id]);
         if((int)$remaining->fetchColumn()<1){
             $pdo->rollBack();
             jsonResponse(false,'The last LACMS-accessible Administrator cannot have access revoked or be deactivated.');
         }
     }
 }
 if($mode==='revoke_access'||$mode==='grant_access'){$status=$mode==='grant_access'?'Active':'Inactive';$level=match($u['role_name']){'Administrator'=>'Administrator','Legislative Staff'=>'Staff','Committee Member'=>'Committee',default=>'Standard'};$pdo->prepare('INSERT INTO user_system_access (user_id,system_id,access_level,status,granted_by,granted_at,updated_at) VALUES(:user,:system,:level,:status,:by,NOW(),NOW()) ON DUPLICATE KEY UPDATE access_level=VALUES(access_level),status=VALUES(status),granted_by=VALUES(granted_by),updated_at=NOW()')->execute([':user'=>$id,':system'=>lacmsSystemId(),':level'=>$level,':status'=>$status,':by'=>currentUserId()]);$message=$status==='Active'?'LACMS access granted.':'LACMS access revoked.';lacmsAudit('System Access','Shared User',(string)$id,null,['lacms_access_status'=>$status]);}
 elseif($mode==='deactivate_account'||$mode==='activate_account'){$new=$mode==='activate_account'?'Active':'Inactive';if((int)$u['role_id']===1&&$new==='Inactive'){$count=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role_id=1 AND status='Active' AND deleted_at IS NULL")->fetchColumn();if($count<=1){$pdo->rollBack();jsonResponse(false,'The last active Administrator cannot be deactivated.');}}$pdo->prepare('UPDATE users SET status=:status,updated_at=NOW() WHERE id=:id')->execute([':status'=>$new,':id'=>$id]);if($new==='Inactive')$pdo->prepare('UPDATE user_system_access SET status="Inactive",updated_at=NOW() WHERE user_id=:user AND system_id=:system')->execute([':user'=>$id,':system'=>lacmsSystemId()]);$message='Shared account '.$new.'.';lacmsAudit('Account Status','Shared User',(string)$id,['status'=>$u['status']],['status'=>$new]);}
 else{$pdo->rollBack();jsonResponse(false,'Invalid user action.');}
 lacmsLogActivity(currentUserId(),'LACMS User Administration',"User #{$id}: {$message}");$pdo->commit();jsonResponse(true,$message);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update user.');}
