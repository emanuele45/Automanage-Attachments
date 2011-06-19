<?php
// If we have found SSI.php and we are outside of SMF, then we are running standalone.
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF')) // If we are outside SMF and can't find SSI.php, then throw an error
	die('<b>Error:</b> Cannot install - please verify you put this file in the same place as SMF\'s SSI.php.');

if(ini_get('safe_mode')){
	echo '
<div style="background-color:white;width:90%;margin:10px auto;">
	<h2 style="padding-top:1em;text-align:center;font-size:2em;color:red;">Safe Mode enabled</h2>
	<br />
	<div style="margin:3px 10px;">
		<p>It seems your server is configured with <a href="http://php.net/manual/en/features.safe-mode.php">Safe Mode</a> on.</p>
		<p>This mod doesn\'t work properly with safe mode on (i.e. it will not be able to create directories and so it will be impossible for your members upload attachments).</p>
		<br />
		<p><b>Please be sure that the mod works on your configuration!</b></p>
	</div>
</div>
';
}

if (!empty($modSettings['currentAttachmentUploadDir']))
{
	if (!is_array($modSettings['attachmentUploadDir']))
		$modSettings['attachmentUploadDir'] = @unserialize($modSettings['attachmentUploadDir']);
	$path = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];
}
else
{
	$path = $modSettings['attachmentUploadDir'];
}

updateSettings(array('mod_pre_automanagement_attachments_updir' => $path));

add_integration_function('integrate_pre_include', '$sourcedir/Subs-AutoManageAttachments.php');
add_integration_function('integrate_load_theme', 'mama_add_admin_javascript');

?>