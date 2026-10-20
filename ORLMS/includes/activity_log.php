<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function logActivity(?int $userId,string $action,string $details=''): void
{
    try{
        static $systemIdResolved=false;
        static $systemId=null;

        if(!$systemIdResolved){
            $systemIdResolved=true;
            $stmt=db()->prepare("SELECT id FROM systems WHERE code='ordinance' LIMIT 1");
            $stmt->execute();
            $id=$stmt->fetchColumn();
            $systemId=$id!==false?(int)$id:null;
        }

        $ip=substr((string)($_SERVER['REMOTE_ADDR']??''),0,45);
        $agent=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500);

        $stmt=db()->prepare(
            'INSERT INTO activity_logs
             (user_id,system_id,action,details,ip_address,user_agent,created_at)
             VALUES(:user,:system,:action,:details,:ip,:agent,NOW())'
        );
        $stmt->execute([
            ':user'=>$userId,
            ':system'=>$systemId,
            ':action'=>$action,
            ':details'=>$details?:null,
            ':ip'=>$ip?:null,
            ':agent'=>$agent?:null,
        ]);
    }catch(Throwable $e){
        error_log('ORLMS activity log failed: '.$e->getMessage());
    }
}
