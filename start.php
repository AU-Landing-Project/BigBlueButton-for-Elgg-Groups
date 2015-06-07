<?php 
	/* big blue button integration
		supports one instance of bbb for each group
		group admins get admin rights
		users go in as users
	*/
	
function au_bbb_init(){
				
		// allow enable/disable
		add_group_tool_option('au_bbb', elgg_echo('au_bbb:bbb'), true);
		// allow enable/disable of open access (non group members)
		add_group_tool_option('au_bbb_open', elgg_echo('au_bbb:open'), false);
		elgg_register_plugin_hook_handler('register', 'menu:owner_block', 'au_bbb_owner_block_menu');	
		elgg_register_page_handler('au_bbb','au_bbb_page_handler');


}	
	
	
elgg_register_event_handler('init', 'system', 'au_bbb_init');


// Add menu if is active in group options
function au_bbb_owner_block_menu($hook, $type, $return, $params) { 
	$group = elgg_extract("entity", $params);
	// only show in groups, with plugin enabled. Do not show to non-members unless open login enabled
	if (elgg_instanceof($group, 'group') && $group->au_bbb_enable != "no" && elgg_is_logged_in() && ($group->au_bbb_open_enable == "yes" || $group->isMember() || elgg_is_admin_logged_in()) ){
		$groupID = $group->getGUID();
		if(au_bbb_is_running($groupID)){
			$running=elgg_echo("au_bbb:running");
			$participantcount = au_bbb_meeting_info($groupID)->participantCount;
			$participantcount = ($participantcount=='')?'0' : $participantcount;
		} else {
			$participantcount='0';
			$running=elgg_echo("au_bbb:notrunning");
		}
		$pages = dirname(__FILE__).'/pages';
		$groupID = $group->getGUID();
		$url = 'au_bbb/'.$groupID.'/webinar';		
		$item = new ElggMenuItem('au_bbb2', elgg_echo('au_bbb:webinar')." (".$running.$participantcount.")", $url);
		$return[] = $item;

	}
	return $return;
}

// there is only ever one meeting per group so no need to have multi pages
function au_bbb_page_handler($page){
			$groupID = $page[0];
			// only works if we can identify a group
			if ($groupID){
				if(au_bbb_is_running($groupID)){
						$running=elgg_echo("au_bbb:running");
						$participantcount = au_bbb_meeting_info($groupID)->participantCount;
					} else {
						$participantcount='0';
						$running=elgg_echo("au_bbb:notrunning");
				}
					
				//create the meeting - it is idempotent so no harm duplicating. Ensures meeting is always there 
				au_bbb_create($groupID);		
					
				//join the meeting - just creates the URL - join on click
				$url = au_bbb_join($groupID);
				
				//show back links and direct open links
				$content= "<p>".elgg_view('output/url', array(
						'href' => elgg_get_site_url()."groups/profile/$groupID",
						'text' => elgg_echo('au_bbb:return')
					))." | ". elgg_view('output/url', array(
						'href' => $url,
						'text' => elgg_echo('au_bbb:ownpage')
					))."</p>";
				// show content in an iframe	
				$content.="<iframe src=\"$url\" frameborder=\"0\" style=\"overflow:hidden;height:700px;width:100%\" height=\"700px\" width=\"100%\">".
				elgg_view('output/url', array(
						'href' => $url,
						'text' => elgg_echo('au_bbb:webinar')
					)).
					"</iframe>";
			
				
				//generate the page	
				$body = elgg_view_layout('one_column', array('content'=>$content));
				echo elgg_view_page(elgg_echo('au_bbb:webinar'), $body);
			}

}	


//check whether bbb meeting is running (true if true)	
function au_bbb_is_running($groupID){
	$salt = elgg_get_plugin_setting('key','au_bbb');
	$server = elgg_get_plugin_setting('server','au_bbb');
	$adminpwd = elgg_get_plugin_setting('admin','au_bbb');
	$userpwd = elgg_get_plugin_setting ('user', 'au_bbb');
	if ($group = get_entity($groupID)){
		$is_runningID= "isMeetingRunning?meetingID=" . $groupID;
		$check= "isMeetingRunningmeetingID=" . $groupID . $salt;
		$checksumcheck= sha1($check);
		$urlcheck= $server . "api/" . $is_runningID .  "&checksum=" .$checksumcheck;
		$checkxml= au_bbb_processXmlResponse($urlcheck); 
		if ($checkxml->running == FALSE){
			register_error(elgg_echo('au_bbb:notrunning'));
			return false;
		} else {
			system_message($checkxml->running);
			return true;
		}
	} else {
		register_error(elgg_echo('au_bbb:nogroup'));
		return false;
	}
}		


//get meeting info (returns object - see bbb api documentation for possible values)
// we so far just use this to find the number of people in the meeting	
function au_bbb_meeting_info($groupID){
	$salt = elgg_get_plugin_setting('key','au_bbb');
	$server = elgg_get_plugin_setting('server','au_bbb');
	$adminpwd = elgg_get_plugin_setting('admin','au_bbb');
	$userpwd = elgg_get_plugin_setting ('user', 'au_bbb');
	if ($group = get_entity($groupID)){
		$groupname= $group->name;	
		$preurl="meetingID=".$groupID."&moderatorPW=".urlencode($adminpwd);
		$checksum = sha1('getMeetingInfo'.$preurl.$salt);
		$url=$server."api/getMeetingInfo?".$preurl."&checksum=".$checksum;
		$checkxml=au_bbb_processXmlResponse($url);
		if($checkxml->returncode=="SUCCESS"){
			//
		} else {
			//register_error(elgg_echo('au_bbb:noinfo'));
		}
		return $checkxml;
		//}
	} else {
		register_error(elgg_echo('au_bbb:nogroup'));
		return false;
	}
}		
	
//create meeting (true if true)	
function au_bbb_create($groupID){
	$salt = elgg_get_plugin_setting('key','au_bbb');
	$server = elgg_get_plugin_setting('server','au_bbb');
	$adminpwd = elgg_get_plugin_setting('admin','au_bbb');
	$userpwd = elgg_get_plugin_setting ('user', 'au_bbb');
	if ($group = get_entity($groupID)){
		$groupname= $group->name;	
		// need this to return to the site
		$thisurl = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
		$preurl="name=".urlencode($groupname)."&meetingID=".$groupID."&welcome=".urlencode(elgg_echo('au_bbb:welcome',array($groupname)))."&logoutURL=".urlencode($thisurl)."&attendeePW=".urlencode($userpwd)."&moderatorPW=".urlencode($adminpwd);
		$checksum = sha1('create'.$preurl.$salt);
		$url=$server."api/create?".$preurl."&checksum=".$checksum;
		$checkxml=au_bbb_processXmlResponse($url);
		if($checkxml->returncode=="SUCCESS"){
			//
		} else {
			register_error(elgg_echo('au_bbb:notcreated').$checkxml->returncode);
		}
		return $url;
		//}
	} else {
		register_error(elgg_echo('au_bbb:nogroup'));
		return false;
	}
}		

//create the URL to join the meeting (returns URL of meeting or false)
function au_bbb_join($groupID){
	if (elgg_is_logged_in()){
		$user = elgg_get_logged_in_user_entity();
		$name = $user->name;
		$avatar = $user->getIconURL('tiny');
		if (!au_bbb_is_running($groupID)){
			register_error(elgg_echo('au_bbb:notrunning'));
			return false;
		}
		if ($group = get_entity($groupID)){				
				$groupname=$group->name;
				$salt = elgg_get_plugin_setting('key','au_bbb');
				$server = elgg_get_plugin_setting('server','au_bbb');
				$adminpwd = elgg_get_plugin_setting('admin','au_bbb');
				$userpwd = elgg_get_plugin_setting ('user', 'au_bbb');
			if ($group->canEdit()){
				// user is admin or group admin
				$preurl="meetingID=".$groupID."&fullName=".urlencode($name)."&avatarURL=".urlencode($avatar)."&password=".urlencode($adminpwd);
			} else {
				// user is attendee - no moderator rights
				$preurl="meetingID=".$groupID."&fullName=".urlencode($name)."&avatarURL=".urlencode($avatar)."&password=".urlencode($userpwd);
			}
			$checksum = sha1('join'.$preurl.$salt);
			$url=$server."api/join?".$preurl."&checksum=".$checksum;
			return $url;
		} else {
			// no group found for this ID
			register_error(elgg_echo('au_bbb:nogroup'));
			return false;
		}
	} else {
		// not logged in, cannot join
		register_error(elgg_echo('au_bbb:notloggedin'));
		return false;
	}
}


// from https://github.com/bigbluebutton/bigbluebutton/blob/master/labs/bbb-api-php/includes/bbb-api.php - allows 
// direct server-to-server communication with the server to return objects with relevant return codes
// tried but failed to use simpleXML for this

function au_bbb_processXmlResponse($url, $xml = ''){
	/* 
	A private utility method used by other public methods to process XML responses.
	*/
		if (extension_loaded('curl')) {
			$ch = curl_init() or die ( curl_error() );
			$timeout = 10;
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false);	
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeout);
			if(!empty($xml)){
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                                       'Content-type: application/xml',
                                       'Content-length: ' . strlen($xml)
                                     ));
			}
			$data = curl_exec( $ch );
			curl_close( $ch );
			if($data)
				return (new SimpleXMLElement($data));
			else
				return false;
		}
		if(!empty($xml))
			throw new Exception('Set xml, but curl not installed.');
		return (simplexml_load_file($url));	
}