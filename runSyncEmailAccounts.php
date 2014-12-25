<?php
/**
* Copyright 2013 Customer Focused Marketing, Inc.
**/
                                             
if($_REQUEST['email'] != "" && $_REQUEST['user'] != "" && $_REQUEST['sub'] == 1){
   echo ' <div id="prog">
        <table width="100%" height="100%">
          <tr>
            <td align="center" valign="middle">Adding user, please wait...</ br><img src="images/scanner_spinner.gif" /></td>
          </tr>
        </table>
     </div>';
     exit(0);
}else{
    if(($_REQUEST['email'] == "" || $_REQUEST['user'] == "") && $_REQUEST['sub'] == 1){
        echo '<font color="red">Please add username and email address!</ br></font>';
    }
    
    echo '<div id="prog">
            <table width="100%" height="100%">
              <tr>
                <td align="center" valign="middle">
                <form name="input" action="runSyncEmailAccounts.php" method="get">
                    <table>
                    <tr>
                        <td>
                            Username: <input type="text" name="user" width="20px" />
                        </td>
                    </tr>
                    <tr>
                        <td>
                            Email Address: <input type="text" name="email" width="40px" />
                        </td>
                    </tr>
                    <tr>
                        <td><input type="submit" value="Submit" /></td>
                        <input type="hidden" name="sub" value="1" />
                    </tr>
                    </form> 
                </td>
              </tr>
            </table>
            </div>';
            
        exit(0);
}


if(!defined('sugarEntry'))define('sugarEntry',true);

require_once('include/entryPoint.php');
require_once('cfm_framework/libs/models/db_model.php');    
require_once("modules/Emails/Email.php");
require_once("modules/InboundEmail/InboundEmail.php");
require_once("include/OutboundEmail/OutboundEmail.php");
require_once("include/ytree/Tree.php");
require_once("include/ytree/ExtNode.php");
require_once("modules/Users/UserSignature.php");



function setUserSettings($uid, $n, $gid, $eu, $ep, $e)
{
    global $db;
    
    shell_exec("/usr/sbin/useradd -s /sbin/nologin $eu");
    shell_exec("echo \"$ep\" | passwd $eu --stdin");
      
    $db->query("delete from outbound_email where user_id = '$uid'");
    $db->query("delete from inbound_email where group_id = '$uid'");
        
    $oe = new OutboundEmail();
    $oe->name = 'Administrator';
    $oe->type = 'user';
    $oe->user_id = $uid; 
    $oe->mail_sendtype = 'SMTP';
    $oe->mail_smtpserver = 'mail.autoaccelerator2.com';
    $oe->mail_smtpport = 25;
    $oe->mail_smtpuser = 'admin';
    $oe->mail_smtppass = 'a9999';
    $oe->mail_smtpauth_req = 1;
    $oe->mail_smtpssl = 0;
    $mailerId = $oe->save();

    $ie = new InboundEmail();

    echo 'Adding user ' . $n;
    $ie->name = $n;
    $ie->group_id = $gid;
    $ie->status = 'Active';
    $ie->server_url = 'mail.autoaccelerator2.com';
    $ie->email_user = $eu;
    $ie->email_password = $ep;
    $ie->port = 143;
    $ie->delete_seen = 0;
    $ie->is_personal = 1;
    $ie->protocol = 'imap';
    $ie->mailbox = 'INBOX';
    $ie->mailbox_type = 'pick';
    $ie->service = '::::::::::';        

    $id = $ie->save(); 
    $ie->retrieve($id);


    $ie->protocol = 'imap'; // need to set ie again since we save the "service" string to empty explode values
    $opts = $ie->getSessionConnectionString($ie->server_url, $ie->email_user, $ie->port, $ie->protocol);

    if (empty($opts)) {
        $opts = $ie->findOptimumSettings($useSsl);
    }

    $delimiter = $ie->getSessionInboundDelimiterString($ie->server_url, $ie->email_user, $ie->port, $ie->protocol);
   
    $ie->service = $opts['serial'];

    $onlySince = false;

    $focusUser = new User();
    $focusUser->retrieve($uid);
    
    $oe = new OutboundEmail();
   // $oe->getSystemMailerSettings($focusUser, $mailerId);
    $oe->retrieve($mailerId->id);
    
    $stored_options = array();
    $stored_options['from_name'] = $n;
    $stored_options['from_addr'] = $e;
    $stored_options['trashFolder'] = 'Deleted Messages';
    $stored_options['sentFolder'] = '';
    $stored_options['only_since'] = false;
    $stored_options['filter_domain'] = '';
    $stored_options['folderDelimiter'] = $delimiter;
    $stored_options['outbound_email'] = $mailerId->id;
    $ie->stored_options = base64_encode(serialize($stored_options));

    $savedid = $ie->save();

    $query = "select id from users_signatures where user_id = '$uid' and deleted = 0 limit 1;";
    $result = $db->query($query);
    while($row = $db->fetchByAssoc($result))   
    {
            $sigid = $row['id'];
    }
    
    $sig = new UserSignature();
    
    $query = "select
                concat(u.first_name, ' ', u.last_name) as name,
                concat(u.first_name, ' ', u.last_name, ' ', u.address_street,'&nbsp;') as signature,
                concat('<div ><table border=\"0\"><tbody><tr><td><address class=\"H1\"><font size=\"3\">', u.first_name, ' ', 
                u.last_name,'</font></address><address class=\"H1\"><font size=\"3\">', u.address_street, '</font></address><p>
                        <a href=\"http://', ifnull(a.website,''), '\" target=\"_blank\" title=\"', a.name, '\">
                        <img src=\"http://autoaccelerator2.com/d', a.id, '/templateImages/footer.jpg\" alt=\"\" /></a>
                        &nbsp;</p></td></tr></tbody></table></div>') as signature_html
                        from
                            users u, accounts a
                        where
                            u.id = '$uid' limit 1;";
                            
    $result = $db->query($query);
    while($row = $db->fetchByAssoc($result))   
    {
        $name = $row['name'];
        $signature = $row['signature'];
        $signature_html = $row['signature_html'];
    }
        
    if($sigid == ''){
        $sig->name = $name;
        $sig->user_id = $uid;
        $sig->signature = $signature;
        $sig->signature_html = $signature_html;
    }else{
        $sig->retrieve($sigid);
        $sig->name = $name;
        $sig->signature = $signature;
        $sig->signature_html = $signature_html;
    }
    
    $newsigid = $sig->save();
    
    $showFolders = array($savedid);
    $showStore = base64_encode(serialize($showFolders));
    $emailSettings = Array( 
        "emailCheckInterval"=>  "5", 
        "layoutStyle"=>  "2rows" ,
        "alwaysSaveOutbound"=>  "1",
        "sendPlainText"=>  "0", 
        "tabPosition"=>  "top", 
        "showNumInList"=>  "20", 
        "defaultOutboundCharset"=> "ISO-8859-1", 
        "fullScreen"=>  "0" 
    );
    
    $query = "select id from user_preferences where assigned_user_id = '$uid' and category = 'global' and deleted = 0 limit 1;";
    $result = $db->query($query);
    while($row = $db->fetchByAssoc($result))   
    {
            $globalid = $row['id'];
    }
    
    $query = "select id, contents from user_preferences where assigned_user_id = '1' and category = 'global' and deleted = 0 limit 1;";
    $result = $db->query($query);
    while($row = $db->fetchByAssoc($result))   
    {
            $contents = unserialize(base64_decode($row['contents']));
            $contents['signature_default'] = $newsigid;
    }
    
    $focus = new UserPreference();
    
    if($globalid != ''){        
        $db->query("delete from user_preferences where assigned_user_id = '$uid' and category = 'global'");
        //$focus->retrieve($globalid);        
       // $focus->contents = base64_encode(serialize($contents));
    }
    $focus->category = 'global';
    $focus->assigned_user_id = $uid;
    $focus->contents = base64_encode(serialize($contents));   
    $focus->save();
    
    $query = "select id from user_preferences where assigned_user_id = '$uid' and category = 'Emails' and deleted = 0 limit 1;";
    $result = $db->query($query);
    while($row = $db->fetchByAssoc($result))   
    {
            $emailid = $row['id'];
    }
    
    $focus = new UserPreference();
    
    if($emailid != ''){      
        $db->query("delete from user_preferences where assigned_user_id = '$uid' and category = 'Emails'");
    }
    
    $focus->category = 'Emails';
    $focus->assigned_user_id = $uid;  
    $contents = array();
    $contents['email2Preflight'] = true;
    $contents['showFolders'] = $showStore;
    $contents['emailSettings'] = $emailSettings;
    $focus->contents = base64_encode(serialize($contents));   
    $focus->save();
    
    
    if($focusUser->graph_c == ''){
        $db->query("
            insert into users_cstm
            (id_c,graph_c)
            select
                '$uid' as id_c,
                '5^_^3' as graph_c,
                'appointments^_^calls^_^emails^_^tasks^_^reports' as myday_c
            ");
    }else{        
        $db->query("update users_cstm set graph_c = '5^_^3', myday_c = 'appointments^_^calls^_^emails^_^tasks^_^reports' where id_c = '$uid'");
    }

}

global $db;


$account = '';

$query = "select id from accounts limit 1;";

$result = $db->query($query);
while($row = $db->fetchByAssoc($result))   
{
        $account = $row['id'];
}

/*
if($account == '')
{
    exit(0);
}
*/



/*$query = "
        delete inbound_email ie
        from
            inbound_email ie
        left join
            users u
            on
                u.id = ie.group_id
        where
            replace(u.address_street,'@autoaccelerator2.com','') <> ie.email_user and u.id not like '1' and u.user_name not like '%rolus%' and
            ie.server_url = 'mail.autoaccelerator2.com'";
*/


$db->query($query);
    
$query = "
        select
                u.id as `user_id`,
                concat(ifnull(u.first_name,''), ' ', ifnull(u.last_name,'')) as `name`,
                u.id as `group_id`,
                u.address_street as `email_address`,
                replace(u.address_street,'@autoaccelerator2.com','') as `email_user`,
                concat(SUBSTRING_INDEX(u.address_street, '.', 1),(select id from accounts limit 1)) as `email_pass`
        from
                users u
        left join
                email_addr_bean_rel ea
                on
                        u.id = ea.bean_id and
                        ea.bean_module = 'Users' and
                        ea.deleted = 0 and
                        ea.primary_address = 1
        left join
                email_addresses e
                on
                        ea.email_address_id = e.id and
                        ea.deleted = 0
        left join
                inbound_email ie
                on
                        u.id = ie.group_id and
                        ie.deleted = 0
        where
                u.status = 'Active' and u.sugar_login = 1 and u.deleted = 0 and ie.id is null and u.id not like '%-%' and
                u.address_street is not null and u.address_street not like '%test%' and 
                u.address_street not like '%admin%' and u.user_name not like '%rolus%'";               

$result = $db->query($query);

while($row = $db->fetchByAssoc($result))    
{
	$user_id = '';
	$name = '';
	$group_id = '';
	$email_user = '';
	$email_pass = '';
	$email_address;
	extract($row);

    setUserSettings($user_id, $name, $group_id, $email_user, $email_pass, $email_address);   
}

?>
