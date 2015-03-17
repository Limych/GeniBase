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
