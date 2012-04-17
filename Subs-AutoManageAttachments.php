<?php
/**
 * Auto-Manage Attachments (AMA)
 *
 * @package AMA
 * @author emanuele
 * @copyright 2012, Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 0.1.11
 */

if (!defined('SMF'))
	die('Hacking attempt...');

function mama_add_admin_javascript ()
{
	global $context, $modSettings, $boarddir;

	if (!($context['current_action'] == 'admin' && $context['current_subaction'] == 'attachments'))
		return false;

	// Saving settings?
	if (isset($_GET['save']))
	{
		checkSession();

		if(isset($_POST['mod_use_subdirectories_for_attchments']) &&
				empty($_POST['mod_basedirectory_for_attchments']))
			$_POST['mod_basedirectory_for_attchments'] = (!empty($modSettings['mod_use_subdirectories_for_attchments']) ? ($modSettings['mod_basedirectory_for_attchments']) : $boarddir);
	}

	$context['mod_valid_basedirectory'] =  empty($modSettings['mod_use_subdirectories_for_attchments']) ? 1 : mod_automanage_attachments_check_directory(true);
	if (empty($context['settings_post_javascript']))
		$context['settings_post_javascript'] = '';

	$context['settings_post_javascript'] .= '
	var storing_type = document.getElementById(\'mod_automanage_attachments\');
	var base_dir = document.getElementById(\'mod_use_subdirectories_for_attchments\');

	mod_addEvent(storing_type, \'change\', mod_toggleSubDir);
	mod_addEvent(base_dir, \'change\', mod_toggleBaseDir);
	mod_toggleSubDir();

	function mod_addEvent(control, ev, fn){
		if (control.addEventListener){
			control.addEventListener(ev, fn, false); 
		} else if (control.attachEvent){
			control.attachEvent(\'on\'+ev, fn);
		}
	}

	function mod_toggleSubDir(){
		var select_elem = document.getElementById(\'mod_automanage_attachments\');
		var use_sub_dir = document.getElementById(\'mod_use_subdirectories_for_attchments\');

		use_sub_dir.disabled = !Boolean(select_elem.selectedIndex);
		mod_toggleBaseDir();
	}
	function mod_toggleBaseDir(){
		var select_elem = document.getElementById(\'mod_automanage_attachments\');
		var sub_dir = document.getElementById(\'mod_use_subdirectories_for_attchments\');
		var dir_elem = document.getElementById(\'mod_basedirectory_for_attchments\');
		if(select_elem.selectedIndex==0){
			dir_elem.disabled = 1;
		} else {
			dir_elem.disabled = !sub_dir.checked;
		}
	}';
	
	if (!empty($modSettings['currentAttachmentUploadDir']))
		$modSettings['attachmentUploadDir'] = serialize($modSettings['attachmentUploadDir']);
}

function mod_automanage_attachments_check_directory ($return=false)
{
	global $boarddir, $modSettings;

	$year = date('Y');
	$month = date('m');
	$day = date('d');
	if (!empty($modSettings['mod_automanage_attachments']))
	{
		if (!is_array($modSettings['attachmentUploadDir']) && !empty($modSettings['currentAttachmentUploadDir']))
			$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);

		$basedirectory = (isset($modSettings['mod_use_subdirectories_for_attchments']) ? ($modSettings['mod_basedirectory_for_attchments']) : $boarddir);
		//Just to be sure: I don't want directory separators at the end
		$sep = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? '\/' : DIRECTORY_SEPARATOR;
		$basedirectory = rtrim($basedirectory, $sep);

		switch ($modSettings['mod_automanage_attachments']){
			case 1:
				$updir = $basedirectory . DIRECTORY_SEPARATOR . $year;
				break;
			case 2:
				$updir = $basedirectory . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;
				break;
			case 3:
				$updir = $basedirectory . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month . DIRECTORY_SEPARATOR . $day;
				break;
			case 4:
				//placeholder: I'll use it to implement "number of files"...later
			case 5:
				$updir = $basedirectory . DIRECTORY_SEPARATOR . 'attachments_' . (isset($modSettings['mod_last_attachments_directory']) ? $modSettings['mod_last_attachments_directory'] : 0);
				break;
			default :
				$updir = '';
		}

		if (!is_array($modSettings['attachmentUploadDir']) || (!in_array($updir, $modSettings['attachmentUploadDir']) && !empty($updir)))
			$outputCreation = mod_automanage_attachments_create_directory($basedirectory, $updir, $return);
		elseif (in_array($updir, $modSettings['attachmentUploadDir']))
			$outputCreation = true;

		if ($outputCreation)
		{
			if (!is_array($modSettings['attachmentUploadDir']) && !empty($modSettings['currentAttachmentUploadDir']))
				$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);

			$modSettings['currentAttachmentUploadDir'] = array_search($updir, $modSettings['attachmentUploadDir']);
			updateSettings(array(
				'currentAttachmentUploadDir' => $modSettings['currentAttachmentUploadDir'],
			));
		}
		return $outputCreation;
	}
}

function mama_get_directory_tree_elements ($directory)
{
	/*
		In Windows server both \ and / can be used as directory separators in paths
		In Linux (and presumably *nix) servers \ can be part of the name
		So for this reasons:
			* in Windows we need to explode for both \ and /
			* while in linux should be safe to explode only for / (aka DIRECTORY_SEPARATOR)
	*/
	if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
		$tree = preg_split('#[\\\/]#', $directory);
	else
	{
		if (substr($directory, 0, 1)!=DIRECTORY_SEPARATOR)
		{
			if(!$return)
				//TODO Future development maybe change to a personalized error message
				fatal_lang_error('attachments_no_write', 'critical');
			else
				return false;
		}
		$tree = explode(DIRECTORY_SEPARATOR, trim($directory,DIRECTORY_SEPARATOR));
	}
	return $tree;
}

function mama_init_dir (&$tree, &$count, $return)
{
	$directory = '';
	// If on Windows servers the first part of the path is the drive (e.g. "C:")
	if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
	{
		 //Better be sure that the first part of the path is actually a drive letter...
		 //...even if, I should check this in the admin page...isn't it?
		 //...NHAAA Let's leave space for users' complains! :P
		if (preg_match('/^[a-z]:$/i',$tree[0]))
			$directory = array_shift($tree);
		else
		{
			if (!$return)
				//TODO Future development maybe change to a personalized error message
				fatal_lang_error('attachments_no_write', 'critical');
			else
				return false;
		}

		$count--;
	}
	return $directory;
}

function mod_automanage_attachments_create_directory ($basedirectory, $updir, $return=false)
{
	global $modSettings;

	$tree = mama_get_directory_tree_elements($updir);
	$count = count($tree);

	$directory = mama_init_dir($tree, $count, $return);
	if ($directory === false)
		return false;

	$directory .= DIRECTORY_SEPARATOR . array_shift($tree);

	while (!is_dir($directory) || $count != -1)
	{
		if (!is_dir($directory))
		{
			if (!@mkdir($directory,0755))
			{
				if (!$return)
					//TODO Future development maybe change to a personalized error message
					fatal_lang_error('attachments_no_write', 'critical');
				else
					return false;
			}
		}

		$directory .= DIRECTORY_SEPARATOR . array_shift($tree);
		$count--;
	}

	if (!is_writable($directory))
	{
		if (!$return)
			//TODO Future development maybe change to a personalized error message
			fatal_lang_error('attachments_no_write', 'critical');
		else
			return false;
	}

	// Everything seems fine...let's create the .htaccess
	$ht_created = mama_create_htaccess($basedirectory);
	// No .htaccess in the basement? Why not in the attic then?
	if ($ht_created === false)
		mama_create_htaccess($directory, false);

	$sep = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? '\/' : DIRECTORY_SEPARATOR;
	$directory = rtrim($directory, $sep);
	$_SESSION['temp_attachments_dir'][] = $directory;
	if (!empty($modSettings['currentAttachmentUploadDir']))
	{
		if (!is_array($modSettings['attachmentUploadDir']) && unserialize($modSettings['attachmentUploadDir']))
			$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);
	}
	else
	{
		$modSettings['attachmentUploadDir'] = array(
			1 => $modSettings['attachmentUploadDir']
		);
	}

	$modSettings['attachmentUploadDir'][count($modSettings['attachmentUploadDir'])+1] = $updir;
	updateSettings(array(
		'attachmentUploadDir' => serialize($modSettings['attachmentUploadDir']),
		'currentAttachmentUploadDir' => array_search($updir, $modSettings['attachmentUploadDir']),
	));
	return 'true';
}

function mama_create_htaccess ($directory, $check = true)
{
	global $boarddir;

	if ($check)
	{
		// The directory SHALL not be $boarddir, but neither one at a higher level
		$tree = mama_get_directory_tree_elements($boarddir);
		$count = count($tree);

		$board_parents = mama_init_dir($tree, $count, false);
		while ($count != -1)
		{
			// If at any time the two are the same then it means that $directory is a parent of $boarddir
			// Then no .htaccess!
			if ($board_parents==$directory)
				return false;

			$board_parents .= DIRECTORY_SEPARATOR . array_shift($tree);
			$count--;
		}
	}

	if (!file_exists($directory . '/.htaccess'))
	{
		$fh = @fopen($directory . '/.htaccess', 'w');
		if ($fh)
		{
			fwrite($fh, "<Files *>
	Order Deny,Allow
	Deny from all
	Allow from localhost
</Files>

RemoveHandler .php .php3 .phtml .cgi .fcgi .pl .fpl .shtml");
			fclose($fh);
			return true; //created
		}
		return false; //problems during creation
	}
	return true; //already exists
}

function mama_check_presence_of_attach ()
{
	if (isset($_FILE['name']))
		foreach($_FILE['error'] as $err)
			if($err==0)
				return true;

	return false;
}

function mod_automanage_attachments_check_space ()
{
	global $modSettings, $boarddir;

	$basedirectory = (isset($modSettings['mod_use_subdirectories_for_attchments']) ? ($modSettings['mod_basedirectory_for_attchments']) : $boarddir);
	//Just to be sure: I don't want directory separators at the end
	$sep = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? '\/' : DIRECTORY_SEPARATOR;
	$basedirectory = rtrim($basedirectory, $sep);

	if (!isset($modSettings['mod_last_attachments_directory']))
		$modSettings['mod_last_attachments_directory'] = 0;

	if (!empty($modSettings['mod_automanage_attachments']) && $modSettings['mod_automanage_attachments']==4)
	{
		$updir = $basedirectory . '/attachments_' . ($modSettings['mod_last_attachments_directory'] + 1);

		mod_automanage_attachments_create_directory($basedirectory, $updir);

		if (!is_array($modSettings['attachmentUploadDir']) && !empty($modSettings['currentAttachmentUploadDir']))
			$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);

		$modSettings['currentAttachmentUploadDir'] = array_search($updir, $modSettings['attachmentUploadDir']);

		updateSettings(array(
			'mod_last_attachments_directory' => $modSettings['mod_last_attachments_directory'] + 1,
			'currentAttachmentUploadDir' => $modSettings['currentAttachmentUploadDir'],
		));
		return true;
	}
	else
		return false;
}

function mama_scan_temp_directories ($attachID, $current_attach_dir, $posterID)
{
	$already_uploaded = preg_match('~^post_tmp_' . $posterID . '_\d+$~', $attachID) != 0;

	if (!$already_uploaded)
		return $current_attach_dir;

	foreach ($_SESSION['temp_attachments_dir'] as $dir)
		if(file_exists($dir . '/' . $attachID))
			return $dir;

	return $current_attach_dir;
}

?>