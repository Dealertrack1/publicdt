<?php
/**
* Copyright 2013 Customer Focused Marketing, Inc.
**/
if(!defined('sugarEntry'))define('sugarEntry', true);    
  
require_once 'cfm_framework/libs/models/db_model.php';

function sendSMS($toaddress, $body)
{   
    $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

    $headers .= 'From: do_not_reply@autoaccelerator2.com' . "\r\n";
    $subject = ''; //'New Opportunity';
    
    mail($toaddress, $subject, $body, $headers);
}

$query = "
select
    p.entrydate, p.id, p.namefirst, p.namelast, p.yearmanufactured, p.make, p.model, p.stock, p.sms_mobilephone, e.email_address
from
    prospect_targets_from_fusion p
inner join
    users u
    on
        p.sms_assigned_user_id = u.id
left join
    cfm_advanced_notifications ca
    on
        u.id = ca.assigned_user_id and
        ca.email_opportunities = 1 and 
    ca.deleted = 0
left join
    email_addr_bean_rel er
    on
        ca.assigned_user_id = er.bean_id and
        bean_module = 'Users'
left join
    email_addresses e
    on
        er.email_address_id = e.id and
        e.deleted = 0
where
    p.sms_enabled = 1 and p.sms_sentdate is null
";
 
$result = $db->query($query);       
                                                
while($row = $db->fetchByAssoc($result))    
{
    if($row['namefirst'] != '' || $row['namelast'] != ''){
        $smsbody = 'A new opportunity has been assigned to you!<br><br>' .
                   'Name: ' . $row['namefirst'] . ' ' . $row['namelast'] . '<br><br>' .
                   'Year: ' . $row['yearmanufactured'] . '<br>Make: ' . $row['make'] . '<br>Model: ' . $row['model'] . '<br>';               
        if($row['email_address'] != ''){
            sendSMS($row['sms_mobilephone']. ';' . $row['email_address'],$smsbody);
        }else{
            sendSMS($row['sms_mobilephone'],$smsbody); 
        }
    }
    
    $nquery = "update prospect_targets_from_fusion set sms_sentdate = now() where id = '" . $row['id'] . "'";
    $db->query($nquery);   
}
  
  
?>
