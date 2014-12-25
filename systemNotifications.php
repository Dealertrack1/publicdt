<?php
/**
* Copyright 2013 Customer Focused Marketing, Inc.
**/
if(!defined('sugarEntry'))define('sugarEntry', true);
require_once 'modules/CFM_Advanced_Notifications/CFM_Advanced_Notifications.php';
require_once 'cfm_framework/app/AutoAccelerator/modules/Emails/controllers/emails_controller.php';
require_once 'custom/include/custom_utils.php';
global $current_user, $sugar_config, $timedate;
 $con = mysql_connect($sugar_config['dbconfig']['db_host_name'],$sugar_config['dbconfig']['db_user_name'],$sugar_config['dbconfig']['db_password']);
mysql_select_db($sugar_config['dbconfig']['db_name'],$con);
$notification_data = array();
$sqlite_connected = false;

//connecting with sqliteDB
try{		
	$sqlite_db = new PDO("sqlite:notifications/notifications.sqlite3");
	$sqlite_connected = true;
}catch(PDOException $e){		
	echo $e->getMessage();
}	

//if successfully connected to sqliteDB
if($sqlite_connected){	

	if($_REQUEST['getCount']){
	}elseif((isset($_REQUEST['nid']) || empty($_REQUEST['nid'])) && !empty($_REQUEST['update']) && $_REQUEST['update']){
		$time_shown = date("Y-m-d H:i:s");
		$update_query = "UPDATE notifications SET date_shown = '".$_REQUEST['time']."', is_shown = 1 WHERE id = '".$_REQUEST['nid']."'";
		$sqlite_db->query($update_query);	
	}
	else if((!isset($_REQUEST['nid']) || empty($_REQUEST['nid'])) && (!isset($_REQUEST['id_mantain']) && empty($_REQUEST['id_mantain']))){
		$adv = new CFM_Advanced_Notifications();
		$adv->retrieve_by_string_fields(array('assigned_user_id' => $current_user->id));
		$current_minutes = date("i");
		if($current_minutes % 5 == 0){
			 if($adv->alert_emails == 1){
				$email = new EmailsCFMController();		
				$count_email = $email->get_unread_emails_count(array('count_only' => true));
				$SQL_Query_email = "SELECT id, contact_id FROM notifications WHERE notification_module='Emails' and assigned_user_id = '".$current_user->id."'";
				$result_email = $sqlite_db->query($SQL_Query_email);
				$result_email->setFetchMode(PDO::FETCH_ASSOC);
				$result_email_arr = $result_email->fetchAll();
				$new_insertion = true;								
				foreach($result_email_arr as $row)
				{					
					
					if(!empty($row['id'])){
						
						$new_insertion = false;
						if($row['contact_id'] != $count_email && $count_email > 0){
							
							$SQL_Query_update ="UPDATE notifications SET time_out = '".$adv->timeout_emails."' , is_seen = 0 , description = 'You have <b>".$count_email."</b> new Emails' , contact_id='".$count_email."' WHERE id ='".$row['id']."'";
							
							try{
								$sqlite_db->exec($SQL_Query_update);
							}catch(PDOException $e){
								$GLOBALS['log']->fatal("Record not updated ERROR:: ".$e->getMessage());
							}
						}
					}
				}
				if($new_insertion && $count_email > 0){
					$SQL_Query_insert ="INSERT into notifications (id , name , is_sticky , date_entered , description , assigned_user_id , is_seen , user_image_path , callback_function , notification_module , relate_id , date_shown , is_shown , opportunity_id , contact_id , activity_date_time , is_clearall , cleared_at , cleared_id , time_out) VALUES ('".create_guid()."' , 'email_notification' , '".$adv->sticky_emails."' , datetime('now') , 'You have <b>".$count_email."</b> new Emails', '".$current_user->id."' , '0' , '' , '1' , 'Emails' , '','' , '0' , '','".$count_email."' ,'' , '0' ,'' ,'' , '".$adv->timeout_emails."' ) ";
					$sqlite_db->query($SQL_Query_insert);
				}
			} 
		}

		$SQL_Query = "SELECT * FROM notifications WHERE is_seen = 0 and assigned_user_id = '".$current_user->id."' and notification_module not IN('Meetings_Reminder')";
		$result = $sqlite_db->query($SQL_Query);
		foreach($result as $row){
			$row['main_screen'] = "0";
			$notification_data['notifications'][$row['notification_module']][] = $row;
		}
		$QUERY = "Select value from config where category='system' and name='notification_time'";
		$result_time = mysql_query($QUERY);
		$min= mysql_fetch_assoc($result_time);
		$minutes = $min['value'];
		if(empty($minutes))
		{
			$minutes = 60 ;
		}
		$tz = getTimeZone();
		$default_timezone = date_default_timezone_get();
		date_default_timezone_set($tz);
		$user_time = date("Y-m-d H:i:s");
		date_default_timezone_set($default_timezone);
		$prior_user_time = date('Y-m-d H:i:s', strtotime($user_time.' + '.$minutes.' minutes') );

		$SQL_Query_Reminder = "SELECT * FROM notifications WHERE is_seen = 0 and assigned_user_id = '".$current_user->id."' and notification_module IN('Meetings_Reminder') and activity_date_time BETWEEN '".$user_time."' and '".$prior_user_time."'";
		try{
			$result_Reminder = $sqlite_db->query($SQL_Query_Reminder);
		}catch(PDOException $e){
			echo $e->getMessage();
		}		
		foreach($result_Reminder as $row_reminder){
			$row_reminder['main_screen'] = "0";
			$notification_data['notifications'][$row_reminder['notification_module']][] = $row_reminder;
		}

		echo $_GET['callback']. '('. json_encode($notification_data) . ')';
		
	}elseif(isset($_REQUEST['id_mantain']) && !empty($_REQUEST['id_mantain'])){
			
			$idCollection = explode(',' , $_REQUEST['id_mantain']);
			$id = '' ;
			$sep = '';
			for($i = 0 ; $i < count($idCollection) ; $i++)
			{
				$id .= $sep."'".$idCollection[$i]."'";
				$sep = ', ';
			}
			
			$mark_seen = "UPDATE notifications SET is_seen = 1 , is_clearall = 1 , cleared_at =  UTC_TIMESTAMP() , cleared_id = '".$current_user->id."' WHERE id IN (".$id.")";
			try{
				$sqlite_db->query($mark_seen);
				echo 'marked';
			}catch(PDOException $e){
				echo $e->getMessage();
			}
	}else
		{
			$mark_seen = "UPDATE notifications SET is_seen = 1 WHERE id = '".$_REQUEST['nid']."'";
			try{
				$sqlite_db->query($mark_seen);
				echo 'marked';
			}catch(PDOException $e){
				echo $e->getMessage();
			}
		
		}
}
else
	echo "sqliteDB connection failure";
	
	

	function getInactivityNotifications(){
		
		global $timedate,$current_user,$db;
		
		// Get Current Date Time according to User Time Zone and get the hours from time;
		$user_time =  $timedate->to_display_date_time(gmdate("Y/m/d h:i:s"),true,true,$current_user);
		if(!empty($_SESSION['nxt_to_show_at']) && isset($_SESSION['nxt_to_show_at'])){
			$ms_user_time = strtotime($user_time);
			$ms_nxt_to_show = strtotime($_SESSION['nxt_to_show_at']);
			if($ms_user_time >= $ms_nxt_to_show){	
			}else{
				return false;
			}
		}
		//adding time for next notification//
		$_SESSION['noti_shown_at'] = $user_time;	
		$date = new SugarDateTime($user_time);
		$date->modify('+3 hours');
		$_SESSION['nxt_to_show_at'] = $date->format("m/d/Y h:iA");

		//calling the notificaiton//
		$adv = new CFM_Advanced_Notifications();
		$adv->retrieve_by_string_fields(array('assigned_user_id' => $current_user->id));

		if($adv->alert_inactivity == 1){
			$userRoles = getUserRoles();		
			if(!in_array('Salesperson',$userRoles)){
				$teamIds = getTeamIds($current_user);
				if(!empty($teamIds) && $teamIds != $current_user->id){
					$userName = getActiveUserName($teamIds);
					$lastAcitivity_ara = getDayofActivityDate($userName,$user_time);
					$dealer_specific_days = '';
					$result = $db->query("SELECT value FROM config WHERE category = 'system' AND name = 'inactivity_days_range'");
					$row = $db->fetchByAssoc($result);
					$messages =array();
					foreach($lastAcitivity_ara as $k => $v){					
						if($v['date_diff'] != '' && ($v['date_diff'] > '580')){
							$message = "The User <b>".$v['name']."</b> is not following their Cusomters <br />Since: <b>".$v['date_diff']." days</b>";
							$messages[] = $message;
						}
					}	
				return $messages;
				}	
			}
		}	
	}
	/*
		getUserRoles get the role of current user.
		Params: none
		Return: return the user's roles
	*/
	
	function getUserRoles(){
		global $current_user;
		$roleSQL = "SELECT name FROM acl_roles WHERE id IN(SELECT role_id FROM acl_roles_users WHERE user_id = '".$current_user->id."' AND deleted='0') and deleted='0'";
		$res = $current_user->db->query($roleSQL);
		$role = array();
		while($row = $current_user->db->fetchByAssoc($res)){
			$role[] = $row['name'];
		}		
		return $role;
	}
	
	/*
		getTeamIds
		Params: $current_user
		Return: 
	*/
	function getTeamIds($current_user)
	{
		$teamId = '';
		$idsSQL = "SELECT distinct user_id FROM securitygroups_users WHERE deleted = '0' AND securitygroup_id IN (SELECT securitygroup_id FROM securitygroups_users INNER JOIN securitygroups ON securitygroups.id = securitygroups_users.securitygroup_id AND securitygroups.deleted='0'  WHERE user_id = '".$current_user->id."' AND securitygroups_users.deleted = '0')";
		$res = $current_user->db->query($idsSQL);
		$sep='';
		while($row = $current_user->db->fetchByAssoc($res)){
			if($row['user_id'] != $current_user->id){
				$teamId .= $sep . "'" . $row['user_id'] . "'";
				$sep=',';
			}
		}		
		if (empty($teamId)){
			$teamId = $current_user->id;			
		}
		return str_replace(",''", "", $teamId);
	}
	
	/*
		getDayofActivityDate
		Params: $ids
				$current_time			
		Return: 
	*/
	
	function getDayofActivityDate($ids,$current_time){
		global $db,$timedate;		
		$SQL = "SELECT
					IFNULL(date_modified, 'Unknown') AS LA, user_id
				FROM
					tracker
				WHERE
					user_id IN ('".implode("','",array_keys($ids))."')
				AND date_modified != '' and date_modified is not null 
				GROUP BY user_id
				ORDER BY
					date_modified DESC";			
		$result = $db->query($SQL);
		while($row = $db->fetchByAssoc($result)){		
			
			$ids[$row['user_id']]['date_diff'] = dateDiff_Notify($current_time,$timedate->to_display_date_time($row['LA']));
		}		
		return $ids;
	}
	
	/*
		dateDiff calculate the difference b/w current date and last activity date
		Params: $d1 is the current date time in User Time zone.
				$d2 is the last activity date time in user time zone.
		return: Return the number of days between the two dates:		
		
	*/	
	function dateDiff_Notify($d1, $d2) {
		return round(abs(strtotime($d1)-strtotime($d2))/86400);		
	} 
	
	function getActiveUserName($ids){
		global $db;
		$SQL = "SELECT
					id, CONCAT(IFNULL(first_name, ''),' ',IFNULL(last_name,'')) AS name
				FROM
					users
				WHERE
					id IN (".$ids.")
				AND deleted = '0' and status = 'Active'";
		$result = $db->query($SQL);
		while($row = $db->fetchByAssoc($result)){
			$userName[$row['id']]['name'] = $row['name'];
		}		
		return $userName;	
	}
	
	function getTimeZone(){
		$rs = $GLOBALS['db']->query("SELECT contents FROM user_preferences WHERE category='global' AND assigned_user_id='".$GLOBALS['current_user']->id."' AND deleted='0'");
		$result = $GLOBALS['db']->fetchByAssoc($rs);
		$result = unserialize(base64_decode($result['contents']));
		$user_timezone = $result["timezone"];
		return $user_timezone;
	}
	
exit();

?>