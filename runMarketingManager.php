<?php
/**
* Copyright 2013 Customer Focused Marketing, Inc.
**/
if(!defined('sugarEntry'))define('sugarEntry',true);
$GLOBALS['sugarEntry'] = true;
//******************************************************************
//Run Marketing Manager
//******************************************************************
//FOR LINUX USE THE FOLLOWING
$rootDirectory = dirname(__FILE__) . '/';    
ini_set('include_path',$rootDirectory);

require_once('include/entryPoint.php');
require_once('cfm_framework/libs/models/db_model.php');    

global $db;

$query = "select value from config where category = 'marketing_manager' and name = 'enabled' limit 1";      
                
$result = $db->query($query);

while($row = $db->fetchByAssoc($result))    
{
    if($row['value'] == 'true'){
        $MktCampaignsLocation = $rootDirectory ."modules/MKT_Marketing/checkCampaigns.php";
        require_once($MktCampaignsLocation);
        $newCheckCampaigns = new checkCampaigns();
        $newCheckCampaigns->checkCampaignReadyToRun();
        sugar_cleanup();
    }
    
    $query = "update tasks set deleted = 1 where parent_type = 'Accounts' and parent_id is null;";      
    $db->executeQuery($query);
    
    $query = "update tasks set deleted = 1 where parent_type is null or parent_id is null;";      
    $db->executeQuery($query);
    
    exit(0);
}

?>