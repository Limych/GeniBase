<?php
/**
 * Retrieves and creates the gb-config.php file.
 *
 * The permissions for the base directory must allow for writing files in order
 * for the gb-config.php to be created using this page.
 *
 * @internal This file must be parsable by PHP4.
 *
 * @package GeniBase
 * @subpackage Administration
 */

if (! defined('GB_DEBUG'))
    define('GB_DEBUG', true);

/**
 * We are installing.
 */
define('GB_INSTALLING', true);

/**
 * We are blissfully unaware of anything.
 */
define('GB_SETUP_CONFIG', true);

/**
 * Disable error reporting
 *
 * Set this to error_reporting( -1 ) for debugging
 */
error_reporting(defined('GB_DEBUG') && GB_DEBUG ? - 1 : 0);

define('BASE_DIR', dirname(dirname(__FILE__)));
define('GB_CORE_DIR', BASE_DIR . '/gb');

require GB_CORE_DIR . '/core.php';

/**
 * Load GeniBase Administration Upgrade API
 */
require_once GB_ADMIN_DIR . '/includes/upgrade.php';

nocache_headers();

// Support gb-config-sample.php one level up, for the develop repo.
if (file_exists(BASE_DIR . '/gb-config-sample.php'))
    $config_file = file(BASE_DIR . '/gb-config-sample.php');
elseif (file_exists(dirname(BASE_DIR) . '/gb-config-sample.php'))
    $config_file = file(dirname(BASE_DIR) . '/gb-config-sample.php');
else
    gb_die(__('Sorry, I need a gb-config-sample.php file to work from. Please re-upload this file from your GeniBase installation.'));

    // Check if gb-config.php has been created
if (file_exists(BASE_DIR . '/gb-config.php'))
    gb_die('<p>' . sprintf(__("The file <code>gb-config.php</code> already exists. If you need to reset any of the configuration items in this file, please delete it first. You may try <a href='%s'>installing now</a>."), 'install.php') . '</p>');

    // Check if gb-config.php exists above the root directory but is not part of another install
if (file_exists(BASE_DIR . '/../gb-config.php') && ! file_exists(BASE_DIR . '/../gb-settings.php'))
    gb_die('<p>' . sprintf(__("The file <code>gb-config.php</code> already exists one level above your GeniBase installation. If you need to reset any of the configuration items in this file, please delete it first. You may try <a href='%s'>installing now</a>."), 'install.php') . '</p>');

$step = isset($_GET['step']) ? (int) $_GET['step'] : 0;

/**
 * Display setup gb-config.php file header.
 *
 * @ignore
 *
 * @since 3.0.0
 */
function setup_config_display_header($body_classes = array())
{
    $body_classes = (array) $body_classes;
    $body_classes[] = 'gb-core-ui';
    if (function_exists('is_rtl') && is_rtl())
        $body_classes[] = 'rtl';

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml"
	<?php if ( is_rtl() ) echo ' dir="rtl"'; ?>>
<head>
<meta name="viewport" content="width=device-width" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php _e( 'GeniBase &rsaquo; Setup Configuration File' ); ?></title>
	<?php gb_admin_css( 'install', true );	?>
	<?php gb_print_scripts( 'jquery' );	?>
</head>
<body class="<?php echo implode( ' ', $body_classes ); ?>">
	<h1 id="logo">
		<a name="genibase" tabindex="-1"><?php _e( 'GeniBase' ); ?></a>
	</h1>
<?php
} // end function setup_config_display_header();

$language = '';
if (! empty($_REQUEST['language'])) {
    $language = preg_replace('/[^a-zA-Z_]/', '', $_REQUEST['language']);
} elseif (isset($GLOBALS['gb_local_package'])) {
    $language = $GLOBALS['gb_local_package'];
}

switch ($step) {
    case 0:
        if (! empty($language)) {
            load_default_textdomain($language);
            $GLOBALS['gb_locale'] = new GB_Locale();
        }

        setup_config_display_header();
        $step_1 = 'setup-config.php?step=1';
        if (isset($_REQUEST['noapi'])) {
            $step_1 .= '&amp;noapi';
        }
        if (! empty($language)) {
            $step_1 .= '&amp;language=' . $language;
        }
?>

    <p><?php _e( 'Welcome to GeniBase. Before getting started, we need some information on the database. You will need to know the following items before proceeding.' ) ?></p>
	<ol>
		<li><?php _e( 'Database host' ); ?></li>
		<li><?php _e( 'Database username' ); ?></li>
		<li><?php _e( 'Database password' ); ?></li>
		<li><?php _e( 'Database name' ); ?></li>
		<li><?php _e( 'Table prefix (if you want to run more than one GeniBase in a single database)' ); ?></li>
	</ol>
	<p>
	<?php _e( 'We&#8217;re going to use this information to create a <code>gb-config.php</code> file.' ); ?>
	<strong><?php _e( "If for any reason this automatic file creation doesn&#8217;t work, don&#8217;t worry. All this does is fill in the database information to a configuration file. You may also simply open <code>gb-config-sample.php</code> in a text editor, fill in your information, and save it as <code>gb-config.php</code>." ); ?></strong>
	<?php /* _e( "Need more help? <a href='http://codex.wordpress.org/Editing_gb-config.php'>We got it</a>." ); */ ?>
	</p>
	<p><?php _e( "In all likelihood, these items were supplied to you by your Web Host. If you do not have this information, then you will need to contact them before you can continue. If you&#8217;re all ready&hellip;" ); ?></p>

	<p class="step">
		<a href="<?php echo $step_1; ?>" class="button-primary"><?php _e( 'Let&#8217;s go!' ); ?></a>
	</p>
<?php
        break;

    case 1:
        load_default_textdomain($language);
        $GLOBALS['gb_locale'] = new GB_Locale();

        setup_config_display_header();
        ?>
<form method="post" action="setup-config.php?step=2">
		<p><?php _e( "Below you should enter your database connection details. If you&#8217;re not sure about these, contact your host." ); ?></p>
		<div class="row">
			<div class="medium-3 columns medium-text-right">
				<label class="inline" for="dbhost"><?php _e( 'Database host' ); ?></label>
			</div>
			<div class="medium-4 columns">
				<input name="dbhost" id="dbhost" type="text" size="25"
					value="localhost" />
			</div>
			<div class="medium-5 columns description"><?php _e( 'You should be able to get this info from your web host, if <code>localhost</code> does not work.' ); ?></div>
		</div>
		<div class="row">
			<label class="medium-3 columns medium-text-right" for="uname"><?php _e( 'User Name' ); ?></label>
			<div class="medium-4 columns">
				<input name="uname" id="uname" type="text" size="25"
					value="<?php echo htmlspecialchars( _x( 'username', 'example username' ), ENT_QUOTES ); ?>" />
			</div>
			<div class="medium-5 columns description"><?php _e( 'Your MySQL username' ); ?></div>
		</div>
		<div class="row">
			<label class="medium-3 columns medium-text-right" for="pwd"><?php _e( 'Password' ); ?></label>
			<div class="medium-4 columns">
				<input name="pwd" id="pwd" type="text" size="25"
					value="<?php echo htmlspecialchars( _x( 'password', 'example password' ), ENT_QUOTES ); ?>" />
			</div>
			<div class="medium-5 columns description"><?php _e( '&hellip;and your MySQL password.' ); ?></div>
		</div>
		<div class="row">
			<label class="medium-3 columns medium-text-right" for="dbname"><?php _e( 'Database name' ); ?></label>
			<div class="medium-4 columns">
				<input name="dbname" id="dbname" type="text" size="25"
					value="genibase" />
			</div>
			<div class="medium-5 columns description"><?php _e( 'The name of the database you want to run GeniBase in.' ); ?></div>
		</div>
		<div class="row">
			<label class="medium-3 columns medium-text-right" for="prefix"><?php _e( 'Table Prefix' ); ?></label>
			<div class="medium-4 columns">
				<input name="prefix" id="prefix" type="text" size="25" value="gb_" />
			</div>
			<div class="medium-5 columns description" id="prefix_desc"><?php printf( __( 'If you want to run multiple GeniBase installations in a single database or for security reasons, change this. For example, to &#8220;<code>%s</code>&#8221;.' ), gb_generate_password(gb_rand(2, 4), false, false) . '_' /* 15 018 508 combinations */ ); ?></div>
		</div>
		<script>
$(function() {
	$('#prefix_desc code').css('cursor', 'pointer').click(function(){
 		$('#prefix').val( $(this).text() );
	});
});
</script>
	<?php if ( isset( $_GET['noapi'] ) ) { ?><input name="noapi"
			type="hidden" value="1" /><?php } ?>
	<input type="hidden" name="language"
			value="<?php echo esc_attr( $language ); ?>" />
		<p class="step">
			<input name="submit" type="submit"
				value="<?php echo htmlspecialchars( __( 'Submit' ), ENT_QUOTES ); ?>"
				class="button-primary" />
		</p>
	</form>
<?php
        break;

    case 2:
        load_default_textdomain($language);
        $GLOBALS['gb_locale'] = new GB_Locale();

        $dbname = trim(gb_unslash($_POST['dbname']));
        $uname = trim(gb_unslash($_POST['uname']));
        $pwd = trim(gb_unslash($_POST['pwd']));
        $dbhost = trim(gb_unslash($_POST['dbhost']));
        $prefix = trim(gb_unslash($_POST['prefix']));

        $step_1 = 'setup-config.php?step=1';
        $install = 'install.php';
        if (isset($_REQUEST['noapi'])) {
            $step_1 .= '&amp;noapi';
        }

        if (! empty($language)) {
            $step_1 .= '&amp;language=' . $language;
            $install .= '?language=' . $language;
        } else {
            $install .= '?language=en_US';
        }

        $tryagain_link = '</p><p class="step"><a href="' . $step_1 . '" onclick="javascript:history.go(-1);return false;" class="button">' . __('Try again') . '</a>';

        if (empty($prefix))
            gb_die(__('<strong>ERROR</strong>: &#8220;Table Prefix&#8221; must not be empty.' . $tryagain_link));

            // Validate $prefix: it can only contain letters, numbers and underscores.
        if (preg_match('|[^a-z0-9_]|i', $prefix))
            gb_die(__('<strong>ERROR</strong>: &#8220;Table Prefix&#8221; can only contain numbers, letters, and underscores.' . $tryagain_link));

            // Test the db connection.
        /**
         * #@+
         *
         * @ignore
         *
         */
        define('DB_HOST', $dbhost);
        define('DB_USER', $uname);
        define('DB_PASSWORD', $pwd);
        define('DB_BASE', $dbname);
        define('DB_PREFIX', $prefix);
        /**
         * #@-
         */

        /*
         * The gbdb constructor bails when GB_SETUP_CONFIG is set, so we must
         * fire this manually. We'll fail here if the values are no good.
         */
        gbdb()->suppress_errors();
        gbdb()->connect();

        if (! empty(gbdb()->error))
            gb_die(gbdb()->error->get_error_message() . $tryagain_link);

        // Fetch or generate keys and salts.
        $no_api = isset($_POST['noapi']);
        // if ( ! $no_api ) {
        // $secret_keys = gb_remote_get( 'https://api.wordpress.org/secret-key/1.1/salt/' );
        // }

        // if ( $no_api || is_gb_error( $secret_keys ) ) {
        $secret_keys = array();
        for ($i = 0; $i < 8; $i ++) {
            $secret_keys[] = gb_generate_password(64, true, true);
        }
        // } else {
        // $secret_keys = explode( "\n", gb_remote_retrieve_body( $secret_keys ) );
        // foreach ( $secret_keys as $k => $v ) {
        // $secret_keys[$k] = substr( $v, 28, 64 );
        // }
        // }

        $key = 0;
        // Not a PHP5-style by-reference foreach, as this file must be parseable by PHP4.
        foreach ($config_file as $line_num => $line) {
            if (! preg_match('/^define\(\'([A-Z_]+)\',([ ]+)/', $line, $match) )
                continue;

            $constant = $match[1];
            $padding  = $match[2];

            switch ($constant) {
                case 'DB_HOST':
                case 'DB_USER':
                case 'DB_PASSWORD':
                case 'DB_BASE':
                case 'DB_PREFIX':
                    $config_file[$line_num] = "define('" . $constant . "'," . $padding . "'" . addcslashes(constant($constant), "\\'") . "');\r\n";
                    break;
                case 'AUTH_KEY':
                case 'SECURE_AUTH_KEY':
                case 'LOGGED_IN_KEY':
                case 'NONCE_KEY':
                case 'AUTH_SALT':
                case 'SECURE_AUTH_SALT':
                case 'LOGGED_IN_SALT':
                case 'NONCE_SALT':
                    $config_file[$line_num] = "define('" . $constant . "'," . $padding . "'" . $secret_keys[$key ++] . "');\r\n";
                    break;
            }
        }
        unset($line);

        if (! is_writable(BASE_DIR)) :
            setup_config_display_header();
?>
<p><?php _e( 'Sorry, but I can&#8217;t write the <code>gb-config.php</code> file.' ); ?></p>
	<p><?php _e( 'You can create the <code>gb-config.php</code> manually and paste the following text into it.' ); ?></p>
	<textarea id="gb-config" cols="98" rows="15" class="code"
		readonly="readonly"><?php
        foreach ($config_file as $line) {
            echo htmlentities($line, ENT_COMPAT, 'UTF-8');
        }
        ?></textarea>
	<p><?php _e( 'After you&#8217;ve done that, click &#8220;Run the install.&#8221;' ); ?></p>
	<p class="step">
		<a href="<?php echo $install; ?>" class="button"><?php _e( 'Run the install' ); ?></a>
	</p>
	<script>
    (function(){
    if ( ! /iPad|iPod|iPhone/.test( navigator.userAgent ) ) {
        var el = document.getElementById('gb-config');
        el.focus();
        el.select();
    }
    })();
</script>

<?php
        else :
            /*
             * If this file doesn't exist, then we are using the gb-config-sample.php
             * file one level up, which is for the develop repo.
             */
            if (file_exists(BASE_DIR . '/gb-config-sample.php'))
                $path_to_gb_config = BASE_DIR . '/gb-config.php';
            else
                $path_to_gb_config = dirname(BASE_DIR) . '/gb-config.php';

            $handle = fopen($path_to_gb_config, 'w');
            foreach ($config_file as $line) {
                fwrite($handle, $line);
            }
            fclose($handle);
            chmod($path_to_gb_config, 0666);
            setup_config_display_header();
            ?>
<p><?php _e( 'All right, sparky! You&#8217;ve made it through this part of the installation. GeniBase can now communicate with your database. If you are ready, time now to&hellip;' ); ?></p>

	<p class="step">
		<a href="<?php echo $install; ?>" class="button"><?php _e( 'Run the install' ); ?></a>
	</p>
<?php
        endif;
        break;
}
?>
</body>
</html>
