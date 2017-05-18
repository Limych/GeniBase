<?php
/**
 * GeniBase Installer
 *
 * @package GeniBase
 * @subpackage Administration
 */

if (! defined('GB_DEBUG'))
    define('GB_DEBUG', true);

    // Sanity check.
if (FALSE) {
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
}

/**
 * We are installing GeniBase.
 *
 * @since 3.0.0
 * @var bool
 */
define('GB_INSTALLING', true);

define('BASE_DIR', dirname(dirname(__FILE__)));

/**
 * Load GeniBase Bootstrap
 */
require_once (BASE_DIR . '/gb-load.php');

/**
 * Load GeniBase Administration Upgrade API
 */
require_once (GB_ADMIN_DIR . '/includes/upgrade.php');

// /** Load GeniBase Translation Install API */
// require_once( BASE_DIR . 'gb-admin/includes/translation-index.php' );

/**
 * Load gbdb
 */
// require_once (GB_CORE_DIR . '/class.gb-dbase.php');

nocache_headers();

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
        $body_classes[] = ' rtl';

    @header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml"
	<?php language_attributes(); ?>>
<head>
<meta name="viewport" content="width=device-width" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php _e('GeniBase Installation'); ?></title>
	<?php gb_admin_css( 'install', true );	?>
</head>
<body class="<?php echo implode(' ', $body_classes); ?>">
	<h1 id="logo">
		<a name="genibase" tabindex="-1"><?php _e( 'GeniBase' ); ?></a>
	</h1>
<?php
}
// end display_header()

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
}
// end has_users_table()

/**
 * Display installer setup form.
 *
 * @since 3.0.0
 */
function display_setup_form($error = null)
{
    // Ensure that site appear in search engines by default.
    $site_public = 1;
    if (isset($_POST['site_title'])) {
        $site_public = isset($_POST['site_public']);
    }

    $site_title = isset($_POST['site_title']) ? trim(gb_unslash($_POST['site_title'])) : 'GeniBase';
    $admin_email = isset($_POST['admin_email']) ? trim(gb_unslash($_POST['admin_email'])) : '';

    if (! is_null($error)) :
        ?>
<p class="message"><?php echo $error; ?></p>
	<?php endif; ?>
<form id="setup" method="post" action="install.php?step=2"
		novalidate="novalidate">
		<div class="row">
			<div class="medium-3 columns medium-text-right">
				<label for="site_title"><?php _e( 'Site Title' ); ?></label>
			</div>
			<div class="medium-4 columns end">
				<input name="site_title" type="text" id="site_title" size="25"
					value="<?php echo esc_attr( $site_title ); ?>" />
			</div>
		</div>
		<div class="row">
			<div class="medium-3 columns medium-text-right">
				<label for="admin_email"><?php _e( 'Your E-mail' ); ?></label>
			</div>
	<?php if ( has_users_table() ) : ?>
		<div class="medium-4 columns end"><?php _e('User(s) already exists.'); ?></div>
			<input name="admin_email" type="hidden" value="" />
	<?php else : ?>
		<div class="medium-4 columns">
				<input name="admin_email" type="email" id="admin_email" size="25"
					value="<?php echo esc_attr( $admin_email ); ?>" />
			</div>
			<div class="medium-5 columns description"><?php _e( 'Double-check your email address before continuing.' ); ?></div>
	<?php endif; ?>
	</div>
	<?php if ( ! has_users_table() ) : ?>
	<div class="row">
			<div class="medium-3 columns medium-text-right">
				<label for="pass1"><?php _e('Password, twice'); ?></label>
			</div>
			<div class="medium-4 columns">
				<p>
					<input name="admin_password" type="password" id="pass1" size="25"
						value="" />
				</p>
				<p>
					<input name="admin_password2" type="password" id="pass2" size="25"
						value="" />
				</p>
				<p id="pass-strength-result"><?php _e('Strength indicator'); ?></p>
			</div>
			<div class="medium-5 columns description">
				<p><?php _e('A&nbsp;password will be automatically generated for you if you leave this blank.'); ?></p>
				<p><?php echo gb_get_password_hint(); ?></p>
			</div>
		</div>
	<?php endif; ?>
	<div class="row">
			<div class="medium-3 columns medium-text-right">
				<label for="site_public"><?php _e( 'Privacy' ); ?></label>
			</div>
			<div class="medium-4 columns end">
				<label><input type="checkbox" name="site_public" id="site_public"
					value="1" <?php checked( $site_public ); ?> /> <?php _e( 'Allow search engines to&nbsp;index this site.' ); ?></label>
			</div>
		</div>
		<p class="step">
			<input type="submit" name="Submit"
				value="<?php esc_attr_e( 'Install GeniBase' ); ?>"
				class="primary-button button-large" />
		</p>
		<input type="hidden" name="language"
			value="<?php echo isset( $_REQUEST['language'] ) ? esc_attr( $_REQUEST['language'] ) : ''; ?>" />
	</form>
<?php
} // end display_setup_form()

$language = '';
if ( ! empty( $_REQUEST['language'] ) ) {
    $language = preg_replace( '/[^a-zA-Z_]/', '', $_REQUEST['language'] );
} elseif ( isset( $GLOBALS['gb_local_package'] ) ) {
    $language = $GLOBALS['gb_local_package'];
}

switch ($step) {
    case 0: // Step 0

    // Deliberately fall through if we can't reach the translations API.

    case 1: // Step 1, direct link or from language chooser.
        if (! empty($language) && load_default_textdomain($language)) {
            $language = $language;
            $GLOBALS['gb_locale'] = new GB_Locale();
        }

        display_header();
?>
    <h1><?php _ex( 'Welcome', 'Howdy' ); ?></h1>
	<p><?php _e( 'Welcome to five-minute GeniBase installation process! Just fill in the information below.' ); ?></p>

	<h1><?php _e( 'Information needed' ); ?></h1>
	<p><?php _e( 'Please provide the following information. Don&#8217;t worry, you can always change these settings later.' ); ?></p>

<?php
        display_setup_form();
        break;

    case 2: // Step 2
        if (! empty($language) && load_default_textdomain($language)) {
            $language = $language;
            $GLOBALS['gb_locale'] = new GB_Locale();
        } else {
            $language = 'en_US';
        }

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
            $result = gb_install($site_title, $admin_email, $public, gb_slash($admin_password), $language);
            ?>

<h1><?php _e( 'Success!' ); ?></h1>

	<p><?php _e( 'GeniBase has been installed. Were you expecting more steps? Sorry to disappoint.' ); ?></p>

	<div class="row">
		<div class="small-3 columns text-right"><?php _e( 'User login' ); ?></div>
		<div class="small-9 columns"><?php echo esc_html( sanitize_email( $admin_email ) ); ?></div>
	</div>
	<div class="row">
		<div class="small-3 columns text-right"><?php _e( 'Password' ); ?></div>
		<div class="small-9 columns"><?php
            if (! empty($result['password']) && empty($admin_password_check)) :
                ?>
			<code><?php echo esc_html( $result['password'] ) ?></code>
		<?php endif; ?>
			<?php

            echo $result['password_message']?></div>
	</div>

	<p class="step">
		<a href="../gb-login.php" class="primary-button button-large"><?php _e( 'Log In' ); ?></a>
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
