<?php
/**
 * GeniBase Installer
 *
 * @package GeniBase
 * @subpackage Administration
 */

if (! defined('GB_DEBUG'))  define('GB_DEBUG', true);   // TODO: Remove me

// Sanity check.
if (FALSE) {
/**
 * #@+
 *
 * @ignore
 *
 */
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Error: PHP is not running</title>
</head>
<body class="gb-core-ui">
	<h1 id="logo">GeniBase</h1>
	<h2>Error: PHP is not running</h2>
	<p>GeniBase requires that your web server is running PHP. Your server
		does not have PHP installed, or PHP is turned off.</p>
</body>
</html>
<?php
/**
 * #@-
 */
}

/**
 * We are installing GeniBase.
 *
 * @since 3.0.0
 * @var bool
 */
define('GB_INSTALLING', true);

if (GB_DEBUG)
    header('Content-Type: text/html; charset=utf-8');

/**
 * Load GeniBase bootstrap
 */
require_once './gb-load.php';

/**
 * Load GeniBase Administration Upgrade API
 */
require_once GB_ADMIN_DIR . '/includes/upgrade.php';

// /** Load GeniBase Translation Install API */
// require_once BASE_DIR . '/gb-admin/includes/translation-index.php';

nocache_headers();

$language = '';
if (! empty($_REQUEST['language'])) {
    $language = preg_replace('/[^a-zA-Z_]/', '', $_REQUEST['language']);
} elseif (defined('GB_LOCAL_PACKAGE')) {
    $language = GB_LOCAL_PACKAGE;
}
if (! empty($language) && load_default_textdomain($language)) {
    $GLOBALS['gb_locale'] = new GB_Locale();
} else {
    $language = 'en_US';
}

$step = isset($_GET['step']) ? (int) $_GET['step'] : 0;

/**
 * Display install header.
 *
 * @since 3.0.0
 */
function display_header($body_classes = [])
{
    $body_classes = (array) $body_classes;
    $body_classes[] = 'gb-core-ui';
    if (function_exists('is_rtl') && is_rtl())
        $body_classes[] = 'rtl';

    @header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
    <title><?php _e('GeniBase Installation'); ?></title>

    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

<?php gb_admin_css('install', true);	?>
</head>
<body class="<?php echo implode(' ', $body_classes); ?>">
	<div class="container">
		<div class="header">
			<h1 id="logo"><?php _e( 'GeniBase' ); ?></h1>
		</div>
		<div class="page">
<?php
}   // end display_header()

/**
 * Do we have a users table?
 *
 * @since 3.0.0
 *
 * @return boolean if the users table already have.
 */
function has_users_table()
{
    static $has_users_table;

    if (isset($has_users_table))
        return $has_users_table;

    $users_table = gbdb()->get_cell('SHOW TABLES LIKE ?users', [
        'users' => gbdb()->table_unescape(gbdb()->table_escape('users'))
    ]);
    $has_users_table = ($users_table != null);
    return $has_users_table;
}   // end has_users_table()

/**
 * Display installer setup form.
 *
 * @since 3.0.0
 */
function display_setup_form($error = null)
{
    global $language;

    // Ensure that site appear in search engines by default.
    $site_public = 1;
    if (isset($_POST['site_title'])) {
        $site_public = isset($_POST['site_public']);
    }

    $site_title = isset($_POST['site_title']) ? trim(gb_unslash($_POST['site_title'])) : 'GeniBase';
    $admin_email = isset($_POST['admin_email']) ? trim(gb_unslash($_POST['admin_email'])) : '';

    if (! is_null($error)) :
?>
    <div class="alert alert-danger" role="alert">
        <?php echo $error; ?>
    </div>
<?php endif; ?>
    <form id="setup" method="post" action="install.php?step=2" novalidate="novalidate">
		<div class="form-group row">
			<label class="col-form-label col-md-3 col-12 text-md-right text-left"
                for="site_title"><?php _e( 'Site Title' ); ?>:</label>
			<div class="col-md-4 col-12">
				<input class="form-control" name="site_title" id="site_title" type="text" size="25"
					value="<?php echo esc_attr( $site_title ); ?>" />
			</div>
		</div>
		<div class="form-group row">
			<label class="col-form-label col-md-3 col-12 text-md-right text-left"
                for="admin_email"><?php _e( 'Your E-mail' ); ?>:</label>
            <div class="col-md-4 col-12">
<?php   if ( has_users_table() ) : ?>
                <p class="form-control-static"><?php _e('User(s) already exists.'); ?></p>
    			<input name="admin_email" type="hidden" value="" />
			</div>
<?php   else : ?>
				<input class="form-control" name="admin_email" id="admin_email" type="email" size="25"
					value="<?php echo esc_attr( $admin_email ); ?>" aria-describedby="admin_email-help" />
			</div>
			<small id="admin_email-help" class="col-md-5 col form-text text-muted"><?php _e( 'Double-check your email address before continuing.' ); ?></small>
<?php endif; ?>
    	</div>
<?php if ( ! has_users_table() ) : ?>
    	<div class="form-group row">
    		<label class="col-form-label col-md-3 col-12 text-md-right text-left"
                for="pass1"><?php _e('Password, twice'); ?>:</label>
    		<div class="col-md-4 col-12">
    			<p><input class="form-control" name="admin_password" id="pass1" type="password" size="25"
                    value="" aria-describedby="admin_password-help" /></p>
    			<p><input class="form-control" name="admin_password2" id="pass2" type="password" size="25"
                    value="" aria-describedby="admin_password-help" /></p>
    			<p id="pass-strength-result"><?php _e('Strength indicator'); ?></p>
    		</div>
    		<div id="admin_password-help" class="col-md-5 col form-text text-muted">
    			<p><small><?php _e('A&nbsp;password will be automatically generated for you if you leave this blank.'); ?></small></p>
    			<p><small><?php echo gb_get_password_hint(); ?></small></p>
    		</div>
    	</div>
<?php endif; ?>
        <div class="form-group row">
			<div class="col-md-3 col-12 text-md-right text-left"><?php _e( 'Privacy' ); ?>:</div>
			<div class="col-md-4 col-12 form-check">
                <label class="form-check-label"><input class="form-check-input" type="checkbox"
                    name="site_public" id="site_public" value="1" <?php checked( $site_public ); ?> />
                    <?php _e( 'Allow search engines to&nbsp;index this site.' ); ?></label>
			</div>
		</div>
        <input type="hidden" name="language" value="<?php esc_attr_e( $language ); ?>" />
		<div class="row step">
			<p class="text-right col-md-7 col-12">
    			<input type="submit" name="Submit" value="<?php esc_attr_e( 'Install GeniBase' ); ?>"
                    class="btn btn-primary" />
    		</p>
    	</div>
	</form>
<?php
} // end display_setup_form()



switch ($step) {
    case 0: // Step 0

    // Deliberately fall through if we can't reach the translations API.

    case 1: // Step 1, direct link or from language chooser.
        display_header();
?>
            <h1><?php _ex( 'Welcome', 'Howdy' ); ?></h1>
        	<p><?php _e( 'Welcome to five-minute GeniBase installation process! Just fill in the information below.' ); ?></p>

        	<h2><?php _e( 'Information needed' ); ?></h2>
        	<p><?php _e( 'Please provide the following information. Don&#8217;t worry, you can always change these settings later.' ); ?></p>

<?php
        display_setup_form();
        break;

    case 2: // Step 2
        if (! empty(gbdb()->error))
            gb_die(gbdb()->error->get_error_message());

        display_header();

        // Fill in the data we gathered
        $site_title = isset($_POST['site_title']) ? trim(gb_unslash($_POST['site_title'])) : 'GeniBase';
        $admin_email = isset($_POST['admin_email']) ? trim(gb_unslash($_POST['admin_email'])) : '';
        $admin_password = isset($_POST['admin_password']) ? gb_unslash($_POST['admin_password']) : '';
        $admin_password_check = isset($_POST['admin_password2']) ? gb_unslash($_POST['admin_password2']) : '';
        $public = isset($_POST['site_public']) ? (int) $_POST['site_public'] : 0;

        // Check e-mail address.
        $error = false;
        if (! has_users_table()) {
            if (empty($admin_email)) {
                // TODO: poka-yoke
                display_setup_form(__('You must provide an email address.'));
                $error = true;
            } elseif (! is_email($admin_email)) {
                // TODO: poka-yoke
                display_setup_form(__('Sorry, that isn&#8217;t a valid email address. Email addresses look like <code>username@example.com</code>.'));
                $error = true;
            } elseif ($admin_password != $admin_password_check) {
                // TODO: poka-yoke
                display_setup_form(__('Your passwords do not match. Please try again.'));
                $error = true;
            }
        }

        if ($error === false) :
            gbdb()->show_errors();
            $result = gb_install($site_title, $admin_email, $public, gb_slash($admin_password), $language);  // TODO Uncomment
?>
        <h1><?php _e( 'Success!' ); ?></h1>

    	<p><?php _e( 'GeniBase has been installed. Were you expecting more steps? Sorry to disappoint.' ); ?></p>

    	<div class="row">
    		<div class="col-4 text-right"><?php _e( 'User login' ); ?>:</div>
    		<div class="col"><?php echo esc_html( sanitize_email( $admin_email ) ); ?></div>
    	</div>
    	<div class="row">
    		<div class="col-4 text-right"><?php _e( 'Password' ); ?>:</div>
    		<div class="col">
<?php
            if (! empty($result['password']) && empty($admin_password_check)) {
                echo "<p><code>" . esc_html( $result['password'] ) . "</code></p>\n";
            }
            echo "<p>" . $result['password_message'] . "</p>\n";
?>
            </div>
    	</div>

    	<p class="text-right step">
    		<a href="../gb-login.php" class="btn btn-primary"><?php _e( 'Log In' ); ?></a>
    	</p>

<?php
	endif;
        break;
}

gb_print_scripts('user-profile');
?>
<script type="text/javascript">var t = document.getElementById('site_title'); if (t){ t.select(); t.focus(); }</script>
</body>
</html>
