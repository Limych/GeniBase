<?php
require_once dirname(__FILE__) . '/trac.php';

class GB_UnitTestCase extends PHPUnit_Framework_TestCase
{

    protected static $forced_tickets = array();

    protected $expected_deprecated = array();

    protected $caught_deprecated = array();

    protected $expected_doing_it_wrong = array();

    protected $caught_doing_it_wrong = array();

    /**
     *
     * @var GB_UnitTest_Factory
     */
    protected $factory;

    function setUp()
    {
        set_time_limit(0);
        
        gbdb()->suppress_errors = false;
        gbdb()->show_errors = true;
        ini_set('display_errors', 1);
        // $this->factory = new GB_UnitTest_Factory();
        $this->clean_up_global_scope();
        
        $this->start_transaction();
        $this->expectDeprecated();
        GB_Hooks::add_filter('gb_die_handler', array(
            $this,
            'get_gb_die_handler'
        ));
    }

    function tearDown()
    {
        gbdb()->query('ROLLBACK');
        GB_Hooks::remove_filter('dbdelta_create_queries', array(
            $this,
            '_create_temporary_tables'
        ));
        GB_Hooks::remove_filter('query', array(
            $this,
            '_drop_temporary_tables'
        ));
        GB_Hooks::remove_filter('gb_die_handler', array(
            $this,
            'get_gb_die_handler'
        ));
    }

    function clean_up_global_scope()
    {
        $_GET = $_POST = array();
    }

    function start_transaction()
    {
        gbdb()->query('SET autocommit = 0');
        gbdb()->query('START TRANSACTION');
        GB_Hooks::add_filter('dbdelta_create_queries', array(
            $this,
            '_create_temporary_tables'
        ));
        GB_Hooks::add_filter('query', array(
            $this,
            '_drop_temporary_tables'
        ));
    }

    function _create_temporary_tables($queries)
    {
        return str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $queries);
    }

    function _drop_temporary_tables($query)
    {
        if ('DROP TABLE' === substr($query, 0, 10))
            return 'DROP TEMPORARY TABLE ' . substr($query, 10);
        return $query;
    }

    function get_gb_die_handler($handler)
    {
        return array(
            $this,
            'gb_die_handler'
        );
    }

    function gb_die_handler($message)
    {
        throw new GBDieException($message);
    }

    function assertGBError($actual, $message = '')
    {
        $this->assertInstanceOf('GB_Error', $actual, $message);
    }

    function assertEqualFields($object, $fields)
    {
        foreach ($fields as $field_name => $field_value) {
            if ($object->$field_name != $field_value) {
                $this->fail();
            }
        }
    }

    function assertEqualSets($expected, $actual)
    {
        $this->assertEquals(array(), array_diff($expected, $actual));
        $this->assertEquals(array(), array_diff($actual, $expected));
    }

    protected function checkRequirements()
    {
        parent::checkRequirements();
        if (GB_TESTS_FORCE_KNOWN_BUGS)
            return;
        $tickets = PHPUnit_Util_Test::getTickets(get_class($this), $this->getName(false));
        foreach ($tickets as $ticket) {
            if (is_numeric($ticket)) {
                $this->knownGBBug($ticket);
            }
        }
    }

    /**
     * Skips the current test if there is an open GeniBase ticket with id $ticket_id
     */
    function knownGBBug($ticket_id)
    {
        if (GB_TESTS_FORCE_KNOWN_BUGS || in_array($ticket_id, self::$forced_tickets))
            return;
        if (! TracGitHubIssues::isTicketClosed(GB_GITHUB_REPOS, $ticket_id))
            $this->markTestSkipped(sprintf('GeniBase Issue #%d is not fixed', $ticket_id));
    }

    public static function forceTicket($ticket)
    {
        self::$forced_tickets[] = $ticket;
    }

    /**
     * Returns the name of a temporary file.
     */
    function temp_filename()
    {
        $tmp_dir = '';
        $dirs = array(
            'TMP',
            'TMPDIR',
            'TEMP'
        );
        foreach ($dirs as $dir)
            if (isset($_ENV[$dir]) && ! empty($_ENV[$dir])) {
                $tmp_dir = $dir;
                break;
            }
        if (empty($tmp_dir)) {
            $tmp_dir = '/tmp';
        }
        $tmp_dir = realpath($dir);
        return tempnam($tmp_dir, 'gbunit');
    }

    function expectDeprecated()
    {
        $annotations = $this->getAnnotations();
        foreach (array(
            'class',
            'method'
        ) as $depth) {
            if (! empty($annotations[$depth]['expectedDeprecated']))
                $this->expected_deprecated = array_merge($this->expected_deprecated, $annotations[$depth]['expectedDeprecated']);
            if (! empty($annotations[$depth]['expectedIncorrectUsage']))
                $this->expected_doing_it_wrong = array_merge($this->expected_doing_it_wrong, $annotations[$depth]['expectedIncorrectUsage']);
        }
        GB_Hooks::add_action('deprecated_function_run', array(
            $this,
            'deprecated_function_run'
        ));
        GB_Hooks::add_action('deprecated_argument_run', array(
            $this,
            'deprecated_function_run'
        ));
        GB_Hooks::add_action('doing_it_wrong_run', array(
            $this,
            'doing_it_wrong_run'
        ));
        GB_Hooks::add_action('deprecated_function_trigger_error', '__return_false');
        GB_Hooks::add_action('deprecated_argument_trigger_error', '__return_false');
        GB_Hooks::add_action('doing_it_wrong_trigger_error', '__return_false');
    }

    function expectedDeprecated()
    {
        $errors = array();
        
        $not_caught_deprecated = array_diff($this->expected_deprecated, $this->caught_deprecated);
        foreach ($not_caught_deprecated as $not_caught) {
            $errors[] = "Failed to assert that $not_caught triggered a deprecated notice";
        }
        
        $unexpected_deprecated = array_diff($this->caught_deprecated, $this->expected_deprecated);
        foreach ($unexpected_deprecated as $unexpected) {
            $errors[] = "Unexpected deprecated notice for $unexpected";
        }
        
        $not_caught_doing_it_wrong = array_diff($this->expected_doing_it_wrong, $this->caught_doing_it_wrong);
        foreach ($not_caught_doing_it_wrong as $not_caught) {
            $errors[] = "Failed to assert that $not_caught triggered an incorrect usage notice";
        }
        
        $unexpected_doing_it_wrong = array_diff($this->caught_doing_it_wrong, $this->expected_doing_it_wrong);
        foreach ($unexpected_doing_it_wrong as $unexpected) {
            $errors[] = "Unexpected incorrect usage notice for $unexpected";
        }
        
        if (! empty($errors)) {
            $this->fail(implode("\n", $errors));
        }
    }

    /**
     * Detect post-test failure conditions.
     *
     * We use this method to detect expectedDeprecated and expectedIncorrectUsage annotations.
     *
     * @since 2.2.2
     */
    protected function assertPostConditions()
    {
        $this->expectedDeprecated();
    }

    /**
     * Declare an expected `_deprecated_function()` or `_deprecated_argument()` call from within a test.
     *
     * @since 2.2.2
     *       
     * @param string $deprecated
     *            Name of the function, method, class, or argument that is deprecated. Must match
     *            first parameter of the `_deprecated_function()` or `_deprecated_argument()` call.
     */
    public function setExpectedDeprecated($deprecated)
    {
        array_push($this->expected_deprecated, $deprecated);
    }

    /**
     * Declare an expected `_doing_it_wrong()` call from within a test.
     *
     * @since 2.2.2
     *       
     * @param string $deprecated
     *            Name of the function, method, or class that appears in the first argument of the
     *            source `_doing_it_wrong()` call.
     */
    public function setExpectedIncorrectUsage($doing_it_wrong)
    {
        array_push($this->expected_doing_it_wrong, $doing_it_wrong);
    }

    function deprecated_function_run($function)
    {
        if (! in_array($function, $this->caught_deprecated))
            $this->caught_deprecated[] = $function;
    }

    function doing_it_wrong_run($function)
    {
        if (! in_array($function, $this->caught_doing_it_wrong))
            $this->caught_doing_it_wrong[] = $function;
    }

    function go_to($url)
    {
        // note: the GeniBase classes like to silently fetch parameters
        // from all over the place (globals, GET, etc), which makes it tricky
        // to run them more than once without very carefully clearing everything
        $_GET = $_POST = array();
        // foreach (array('query_string', 'id', 'postdata', 'authordata', 'day', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages', 'pagenow') as $v) {
        // if ( isset( $GLOBALS[$v] ) ) unset( $GLOBALS[$v] );
        // }
        $parts = parse_url($url);
        if (isset($parts['scheme'])) {
            $req = isset($parts['path']) ? $parts['path'] : '';
            if (isset($parts['query'])) {
                $req .= '?' . $parts['query'];
                // parse the url query vars into $_GET
                parse_str($parts['query'], $_GET);
            }
        } else {
            $req = $url;
        }
        if (! isset($parts['query'])) {
            $parts['query'] = '';
        }
        
        $_SERVER['REQUEST_URI'] = $req;
        unset($_SERVER['PATH_INFO']);
        
        // $this->flush_cache();
    }
}
