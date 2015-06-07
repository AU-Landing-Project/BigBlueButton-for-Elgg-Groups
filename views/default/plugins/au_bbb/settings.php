<?php
	/* settings for bbb
		requires
		server
		key
		admin password
		user password
		
	*/	
	echo '<br />';
	echo elgg_echo('au_bbb:server');
	
	echo elgg_view('input/text', array(
		'name' => 'params[server]',
		'id' => 'server',
		'value' => $vars['entity']->server, 
	));
	
		echo '<br /><br />';
	echo elgg_echo('au_bbb:key');
	
	echo elgg_view('input/text', array(
		'name' => 'params[key]',
		'id' => 'key',
		'value' => $vars['entity']->key, 
	));
	
	
		echo '<br /><br />';
	echo elgg_echo('au_bbb:admin');
	
	echo elgg_view('input/text', array(
		'name' => 'params[admin]',
		'id' => 'admin',
		'value' => $vars['entity']->admin, 
	));
	
	
		echo '<br /><br />';
	echo elgg_echo('au_bbb:user');
	
	echo elgg_view('input/text', array(
		'name' => 'params[user]',
		'id' => 'user',		
		'value' => $vars['entity']->user, 
	));