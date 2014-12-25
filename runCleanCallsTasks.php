<?php
/**
* Copyright 2013 Customer Focused Marketing, Inc.
**/
if(!defined('sugarEntry'))define('sugarEntry',true);
$GLOBALS['sugarEntry'] = true;

$rootDirectory = dirname(__FILE__) . '/';  
ini_set('include_path',$rootDirectory);

require_once('include/entryPoint.php');
require_once('cfm_framework/libs/models/db_model.php');    

global $db;


$curtime = gmdate("Y-m-d H:i:s");

$query = "update calls set deleted = 1, date_modified = '$curtime' where assigned_user_id = '1' and deleted = 0";      
$db->Query($query);
$query = "update calls set deleted = 1, date_modified = '$curtime' where parent_type = 'Opportunities' and parent_id is null and deleted = 0";      
$db->Query($query);

$query = "update calls c, (
                select name, min(date_entered) as date_entered, description, date_start, parent_id
                from
                    calls
                where
                    deleted = 0
                group by
                    NAME,description, date_start, parent_id
                having
                    count(*) > 1) allcalls
            set c.deleted = 1, c.date_modified = '$curtime'
            where
                c.name = allcalls.name and ifnull(c.description,'') = ifnull(allcalls.description,'') and c.date_start = allcalls.date_start and c.parent_id = allcalls.parent_id and c.date_entered <> allcalls.date_entered;";
$db->Query($query);         

$query = "UPDATE tasks t, (
                SELECT NAME, MIN(date_entered) AS date_entered, description, date_due, parent_id
                FROM
                    tasks
                WHERE
                    deleted = 0
                GROUP BY
                    NAME,description, date_due, parent_id
                HAVING
                    COUNT(*) > 1) alltasks
            SET t.deleted = 1
            WHERE
                t.name = alltasks.name AND IFNULL(t.description,'') = IFNULL(alltasks.description,'') AND t.date_due = alltasks.date_due AND t.parent_id = alltasks.parent_id AND t.date_entered <> alltasks.date_entered;";
            
$db->Query($query);
                     
$query = "update calls set deleted = 1, date_modified = '$curtime' where parent_type = 'Opportunities' and deleted = 0 and parent_id in (
            select 
                o.id 
            from 
                opportunities o
            inner join
                opportunities_cstm oc
                on
                    o.id = oc.id_c
            where 
                o.deleted = 1 or oc.contact_id4_c is null)";      
$db->Query($query);
        
$query = "SELECT ca.id, ea.id AS email_id
            FROM
                calls ca
            INNER JOIN
                opportunities_cstm oc
                ON
                    ca.parent_id = oc.id_c
            INNER JOIN
                contacts c
                ON
                    oc.contact_id4_c = c.id
            LEFT JOIN
                email_addr_bean_rel er
                ON
                    c.id = er.bean_id AND er.bean_module = 'Contacts' AND er.deleted = 0
            LEFT JOIN
                email_addresses ea
                ON
                    er.email_address_id = ea.id AND ea.deleted = 0 AND ea.opt_out = 0 AND ea.invalid_email = 0
            WHERE
                 ca.date_entered >= DATE_SUB(NOW(),INTERVAL 7 DAY) AND ca.deleted = 0 AND ca.parent_type = 'Opportunities' AND ca.status <> 'Not Held' AND  
                (NULLIF(NULLIF(c.phone_home,''),'( ) -') IS NULL AND NULLIF(NULLIF(c.phone_work,''),'( ) -') IS NULL AND NULLIF(NULLIF(c.phone_mobile,''),'( ) -') IS NULL) ";
     
$result = $db->query($query);
while($row = $db->fetchByAssoc($result))                    
{
    if($row['id'] <> '' && $row['email_id'] == ''){
        $update = "update calls set status = 'Not Held', date_modified = '$curtime' where id = '" . $row['id'] . "'";  
         $db->query($update);
    }

}                                

$query = "SELECT ca.id, ea.id AS email_id
            FROM
                calls ca
            INNER JOIN
                a9_service_cstm ac
                ON
                    ca.parent_id = ac.id_c
            INNER JOIN
                contacts c
                ON
                    ac.contact_id_c = c.id
            LEFT JOIN
                email_addr_bean_rel er
                ON
                    c.id = er.bean_id AND er.bean_module = 'Contacts' AND er.deleted = 0
            LEFT JOIN
                email_addresses ea
                ON
                    er.email_address_id = ea.id AND ea.deleted = 0 AND ea.opt_out = 0 AND ea.invalid_email = 0
            WHERE
                 ca.date_entered >= DATE_SUB(NOW(),INTERVAL 7 DAY) AND ca.deleted = 0 AND ca.parent_type = 'A9_Service' AND ca.status <> 'Not Held' AND 
                (NULLIF(NULLIF(c.phone_home,''),'( ) -') IS NULL AND NULLIF(NULLIF(c.phone_work,''),'( ) -') IS NULL AND NULLIF(NULLIF(c.phone_mobile,''),'( ) -') IS NULL) ";
     
$result = $db->query($query);
while($row = $db->fetchByAssoc($result))                    
{
    if($row['id'] <> '' && $row['email_id'] == ''){
        $update = "update calls set status = 'Not Held', date_modified = '$curtime' where id = '" . $row['id'] . "'";  
         $db->query($update);
    }

}   
 
$query = "UPDATE calls
                SET status = 'Not Held', date_modified = '$curtime'
            WHERE
                DATE(date_start) = DATE(DATE_SUB(NOW(),INTERVAL 7 DAY)) AND STATUS = 'Planned' AND deleted = 0 AND NAME LIKE '%Prospect%'";
$db->Query($query);                 
$query = "delete from calls_contacts where call_id is null or contact_id is null";
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
                cc.id is null and oc.contact_id4_c is not null and c.deleted = 0 and c.date_entered >= date_sub(now(),interval 7 day)";
                
$db->Query($query); 

$query = "SELECT distinct t.id, ifnull(ea.id,'') AS email_id
            FROM
                tasks t
            INNER JOIN
                contacts c
                ON
                    t.contact_id = c.id
            LEFT JOIN
                email_addr_bean_rel er
                ON
                    c.id = er.bean_id AND er.bean_module = 'Contacts' AND er.deleted = 0
            LEFT JOIN
                email_addresses ea
                ON
                    er.email_address_id = ea.id AND ea.deleted = 0 AND ea.opt_out = 0 AND ea.invalid_email = 0
            WHERE
                 t.name LIKE '%Email%' AND t.date_entered >= DATE_SUB(NOW(),INTERVAL 7 DAY) AND t.deleted = 0 AND t.status <> 'Pending Input' AND
                (NULLIF(NULLIF(c.phone_home,''),'( ) -') IS NULL AND NULLIF(NULLIF(c.phone_work,''),'( ) -') IS NULL AND NULLIF(NULLIF(c.phone_mobile,''),'( ) -') IS NULL)";
     
$result = $db->query($query);
while($row = $db->fetchByAssoc($result))                    
{
    if($row['id'] <> '' && $row['email_id'] == ''){
        $update = "update tasks set status = 'Pending Input', date_modified = '$curtime' where id = '" . $row['id'] . "'";  
         $db->query($update);
    }

}                                
                     
$db->Query($query);

    $query = "UPDATE tasks
                SET status = 'Pending Input', date_modified = '$curtime'
            WHERE
                DATE(date_due) = DATE(DATE_SUB(NOW(),INTERVAL 7 DAY)) AND STATUS = 'Not Started' AND deleted = 0 AND NAME LIKE '%Email%'";
$db->Query($query);   
    
                                                          

?>
