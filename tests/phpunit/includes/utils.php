<?php

// misc help functions and utilities

/**
 * Use to create objects by yourself
 */
class MockClass {};

/**
 * Create string with random characters.
 * 
 * @param number $len
 * @return string
 */
function rand_str($len = 32) {
	return substr(md5(uniqid(rand())), 0, $len);
}

/**
 * Catch output of printing function.
 * 
 * @param unknown $callable
 * @param unknown $args
 * @return string
 */
function get_echo($callable, $args = array()) {
	ob_start();
	call_user_func_array($callable, $args);
	return ob_get_clean();
}

// helper class for testing code that involves actions and filters
// typical use:
// $ma = new MockAction();
// add_action('foo', array(&$ma, 'action'));
class MockAction {
	var $events;
	var $debug;

	function MockAction($debug=0) {
		$this->reset();
		$this->debug = $debug;
	}

	function reset() {
		$this->events = array();
	}

	function current_filter() {
		if (is_callable('current_filter'))
			return current_filter();
		global $wp_actions;
		return end($wp_actions);
	}

	function action($arg) {
		if ($this->debug) dmp(__FUNCTION__, $this->current_filter());
		$args = func_get_args();
		$this->events[] = array('action' => __FUNCTION__, 'tag'=>$this->current_filter(), 'args'=>$args);
		return $arg;
	}

	function action2($arg) {
		if ($this->debug) dmp(__FUNCTION__, $this->current_filter());

		$args = func_get_args();
		$this->events[] = array('action' => __FUNCTION__, 'tag'=>$this->current_filter(), 'args'=>$args);
		return $arg;
	}

	function filter($arg) {
		if ($this->debug) dmp(__FUNCTION__, $this->current_filter());

		$args = func_get_args();
		$this->events[] = array('filter' => __FUNCTION__, 'tag'=>$this->current_filter(), 'args'=>$args);
		return $arg;
	}

	function filter2($arg) {
		if ($this->debug) dmp(__FUNCTION__, $this->current_filter());

		$args = func_get_args();
		$this->events[] = array('filter' => __FUNCTION__, 'tag'=>$this->current_filter(), 'args'=>$args);
		return $arg;
	}

	function filter_append($arg) {
		if ($this->debug) dmp(__FUNCTION__, $this->current_filter());

		$args = func_get_args();
		$this->events[] = array('filter' => __FUNCTION__, 'tag'=>$this->current_filter(), 'args'=>$args);
		return $arg . '_append';
	}

	function filterall($tag, $arg=NULL) {
		// this one doesn't return the result, so it's safe to use with the new 'all' filter
		if ($this->debug) dmp(__FUNCTION__, $this->current_filter());

		$args = func_get_args();
		$this->events[] = array('filter' => __FUNCTION__, 'tag'=>$tag, 'args'=>array_slice($args, 1));
	}

	// return a list of all the actions, tags and args
	function get_events() {
		return $this->events;
	}

	// return a count of the number of times the action was called since the last reset
	function get_call_count($tag='') {
		if ($tag) {
			$count = 0;
			foreach ($this->events as $e)
				if ($e['action'] == $tag)
					++$count;
				return $count;
		}
		return count($this->events);
	}

	// return an array of the tags that triggered calls to this action
	function get_tags() {
		$out = array();
		foreach ($this->events as $e) {
			$out[] = $e['tag'];
		}
		return $out;
	}

	// return an array of args passed in calls to this action
	function get_args() {
		$out = array();
		foreach ($this->events as $e)
			$out[] = $e['args'];
		return $out;
	}
}
