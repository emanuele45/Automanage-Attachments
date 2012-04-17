<?php
// If we have found SSI.php and we are outside of SMF, then we are running standalone.
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF')) // If we are outside SMF and can't find SSI.php, then throw an error
	die('<b>Error:</b> Cannot install - please verify you put this file in the same place as SMF\'s SSI.php.');

global $modSettings;

if (!empty($modSettings['mod_pre_automanagement_attachments_updir']))
{
	if (!empty($modSettings['currentAttachmentUploadDir']))
	{
		if (!is_array($modSettings['attachmentUploadDir']))
			$modSettings['attachmentUploadDir'] = @unserialize($modSettings['attachmentUploadDir']);

		$key = array_search($modSettings['mod_pre_automanagement_attachments_updir'], $modSettings['attachmentUploadDir']);
		if (is_null($key))
			$key=false; //for php < 4.2 see http://php.net/manual/en/function.array-search.php

		if ($key===false)
		{
			$modSettings['attachmentUploadDir'][] = $modSettings['mod_pre_automanagement_attachments_updir'];
			$key = array_search($modSettings['mod_pre_automanagement_attachments_updir'], $modSettings['attachmentUploadDir']);
		}
		updateSettings(array(
			'currentAttachmentUploadDir' => $key,
			'attachmentUploadDir' => @serialize($modSettings['attachmentUploadDir']),
		));
	}
	else
		updateSettings(array('attachmentUploadDir' => $modSettings['mod_pre_automanagement_attachments_updir']));
}

remove_integration_function('integrate_pre_include', '$sourcedir/Subs-AutoManageAttachments.php');
remove_integration_function('integrate_load_theme', 'mama_add_admin_javascript');


$mama_settings = array(
	'mod_lastknown_working_attachdir',
	'mod_automanage_attachments',
	'mod_use_subdirectories_for_attchments',
	'mod_basedirectory_for_attchments',
	'mod_last_attachments_directory'
);
$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}settings
	WHERE variable IN ({array_string:settings})',
	array(
		'settings' => $mama_settings,
	)
);

?>