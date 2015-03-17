<?php

class TracGitHubIssues {
	/**
	 * When open tickets for a Trac install is requested, the results are stored here.
	 * 
	 * @var	array
	 */
	protected static $trac_cache	= array();

	/**
	 * Checks if GitHub issue #$issue_id is closed.
	 * 
	 * @param	string	$repos_uri	Repositori URI on GitHub ("User/Project")
	 * @param	int		$issue_id	Issue ID	
	 * @return	bool|null	TRUE if the issue is closed, FALSE if not closed, NULL on error
	 */
	public static function isTicketClosed($repos_uri, $issue_id) {
		if( !isset(self::$trac_cache[$repos_uri])) {
			// In case you're running the tests offline, keep track of open tickets.
			$file = DIR_TESTDATA . '/.trac-cache.' . str_replace(array('/'), array('-'), $repos_uri);
			$issues = @file_get_contents('https://api.github.com/repos/' . $repos_uri . '/issues/?state=open');
			// Check if our HTTP request failed.
			if( false === $issues) {
				if( file_exists($file)) {
					register_shutdown_function(array('TracGitHubIssues', 'usingLocalCache'));
					$issues = explode(' ', file_get_contents($file));
				}else{
					register_shutdown_function(array('TracGitHubIssues', 'forcingKnownBugs'));
					self::$trac_cache[$repos_uri] = array();
					return true;	// Assume the ticket is closed, which means it gets run.
				}
			}else{
				$issues = json_decode($issues, TRUE);
				foreach ($issues as $key => $val) {
					$issues[$key] = $val['number'];
				}
				file_put_contents($file, implode(' ', $issues));
			}
			self::$trac_cache[$repos_uri] = $issues;
		}
		return !in_array($issue_id, self::$trac_cache[$repos_uri]);
	}

	public static function usingLocalCache() {
		echo PHP_EOL . "\x1b[0m\x1b[30;43m\x1b[2K";
		echo 'INFO: Trac was inaccessible, so a local ticket status cache was used.' . PHP_EOL;
		echo "\x1b[0m\x1b[2K";
	}

	public static function forcingKnownBugs() {
		echo PHP_EOL . "\x1b[0m\x1b[37;41m\x1b[2K";
		echo "ERROR: Trac was inaccessible, so known bugs weren't able to be skipped." . PHP_EOL;
		echo "\x1b[0m\x1b[2K";
	}
}
