<?php
/**
* Copyright 2013 Customer Focused Marketing, Inc.
**/
if(!defined('sugarEntry'))define('sugarEntry',true);
$GLOBALS['sugarEntry'] = true;
//******************************************************************
//Run Process Manager for each customer every minute
//******************************************************************
//FOR LINUX USE THE FOLLOWING
$rootDirectory = dirname(__FILE__) . '/';  
ini_set('include_path',$rootDirectory);

require_once('include/entryPoint.php');
require_once('cfm_framework/libs/models/db_model.php');    

               
global $db;

$query = "insert into pm_process_stage_waiting_todo_deleted
 (id, date_deleted, stage_id, opp_id, process_name, pm_cancel_field, opp_stage)
 select
  uuid() as id, now() as date_deleted, s.id as stage_id, o.id as opp_id, pm.name as process_name, pm.process_object_cancel_field_value as pm_cancel_field, o.sales_stage as opp_stage
 from
  pm_process_stage_waiting_todo s
 inner join
  pm_processmanager pm
  on
    s.process_id = pm.id
 inner join
  opportunities o
  on
    s.object_id = o.id
 where
  case 
    when pm.process_object_cancel_field_operator = '!=' then
      pm.process_object_cancel_field_value <> o.sales_stage
    when pm.process_object_cancel_field_operator = 'does not contain' then
      pm.process_object_cancel_field_value not like concat('%',o.sales_stage,'%') 
    when pm.process_object_cancel_field_operator = '=' then
      pm.process_object_cancel_field_value = o.sales_stage
    when pm.process_object_cancel_field_operator = 'contains' then
      pm.process_object_cancel_field_value like concat('%',o.sales_stage,'%')
  end
";

$db->Query($query); 

$query = "delete from pm_process_stage_waiting_todo where id in (select stage_id from pm_process_stage_waiting_todo_deleted)";
 
$db->Query($query);        

$query = "DELETE pps
FROM
    pm_processmanager pm
INNER JOIN
    pm_process_stage_waiting_todo pps
    ON
        pm.id = pps.process_id
INNER JOIN
    opportunities o
    ON
        pps.object_id = o.id
WHERE
    IFNULL(NULLIF(pm.process_object_cancel_field,'N/A'),'sales_stage') = 'sales_stage'
    AND pm.process_object = 'opportunities' AND 
    ((
        pm.process_object_cancel_field_operator IN ('=','contains') AND
        pm.process_object_cancel_field_value LIKE CONCAT('%',o.sales_stage,'%')
    ) OR
    (
        pm.process_object_cancel_field_operator IN ('!=','does not contain') AND
        pm.process_object_cancel_field_value NOT LIKE CONCAT('%',o.sales_stage,'%')
    ));";

$db->Query($query); 

$query = "select value from config where category = 'process_manager' and name = 'enabled' limit 1";      
                
$result = $db->query($query);

while($row = $db->fetchByAssoc($result))    
{
    if($row['value'] == 'true'){
        // remove duplicates from stage waiting todo table
        $query = "SELECT MAX(id) AS id,object_id, process_id, stage_id
                    FROM 
                        `pm_process_stage_waiting_todo` 
                    GROUP BY    
                        object_id, process_id, stage_id 
                    HAVING
                        COUNT(*) > 1";
     
        $result = $db->query($query);
        while($row = $db->fetchByAssoc($result))                    
        {
            $delete = "DELETE FROM pm_process_stage_waiting_todo WHERE id = '" . $row['id'] . "'";  
            $db->query($delete);                                                                                                            
        }   
        
        $processManagerMainLocation = $rootDirectory ."modules/PM_ProcessManager/ProcessManagerEngine.php";
        require_once($processManagerMainLocation);
        $processManager = new ProcessManagerEngine();
        $processManager->processManagerMain();
        sugar_cleanup();
    }
}

$query = "UPDATE calls cs, 
            (
                SELECT MIN(c.id) AS id, NAME, DATE(c.date_entered) AS date_entered, c.parent_id
                FROM 
                    calls c
                INNER JOIN
                    (SELECT DISTINCT object_id FROM `pm_process_completed_process`) ppcp
                    ON
                        c.parent_id = ppcp.object_id
                WHERE 
                    c.deleted = 0 AND DATE(c.date_entered) >= DATE(DATE_SUB(NOW(),INTERVAL 1 DAY)) AND c.name IS NOT NULL
                GROUP BY
                    NAME, DATE(c.date_entered), c.parent_id
                HAVING 
                    COUNT(*) > 1
            ) alldat
                SET cs.deleted = 1,
                    cs.modified_user_id = '1'
            WHERE
                (cs.name = alldat.name AND
                cs.parent_id = alldat.parent_id AND
                DATE(cs.date_entered) = alldat.date_entered) AND cs.id <> alldat.id";
                
$db->Query($query);               
                
$query = "insert into calls_contacts
            select
                uuid(), c.id, oc.contact_id4_c, 1, 'none', now(), 0
            from
                calls c
            inner join
                opportunities o
                on
                    c.parent_id = o.id and c.parent_type = 'Opportunities' and o.deleted = 0
            inner join
                opportunities_cstm oc
                on
                    o.id = oc.id_c
            left join
                calls_contacts cc
                on
                    c.id = cc.call_id and    
                    oc.contact_id4_c = cc.contact_id
            where
                cc.id is null and nullif(oc.contact_id4_c,'') not null and c.deleted = 0 and c.date_entered >= date_sub(now(),interval 7 day)";
                
$db->Query($query); 
                                                     

?>
