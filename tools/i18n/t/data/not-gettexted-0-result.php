<?php

if (! isset($gb_did_header)):
if ( !file_exists( dirname(__FILE__) . '/wp-config.php') ) {
	if (strpos($_SERVER['PHP_SELF'], 'wp-admin') !== false) $path = '';
	else $path = 'wp-admin/';

	require_once( dirname(__FILE__) . '/wp-includes/classes.php');
	require_once( dirname(__FILE__) . '/wp-includes/functions.php');
	require_once( dirname(__FILE__) . '/wp-includes/plugin.php');
	gb_die( sprintf(/*GB_I18N_CONFIG*/'Translation: There doesn\'t seem to be a <code>wp-config.php</code> file. I need this before we can get started. Need more help? <a href=\'http://codex.wordpress.org/Editing_wp-config.php\'>We got it</a>. You can create a <code>wp-config.php</code> file through a web interface, but this doesn\'t work for all server setups. The safest way is to manually create the file.</p><p><a href=\'%s\' class=\'button\'>Create a Configuration File</a>' /*/GB_I18N_CONFIG*/, $path.'setup-config.php'), /*GB_I18N_ERROR*/ 'Translation: WordPress &rsaquo; Error' /*/GB_I18N_ERROR*/);
}

$gb_did_header = true;

require_once( dirname(__FILE__) . '/wp-config.php');

wp();

require_once(GB_INC_DIR . '/template-loader.php');

endif;

?>
