<?php

/**
 * @group	general-template.php
 */
class Tests_general_template extends GB_UnitTestCase {
	function test_siteinfo(){
		$this->assertEquals(GB_VERSION, get_siteinfo('version'));
	}

	function test_paginator() {
		$url = '/index.php?surname=surname_val_' . rand(100, 999);
		$_SERVER['REQUEST_URI'] = $url . '&pg=9' . rand(100, 999);
		
		$a = '<div class="paginator"><span class="current">1</span> <a href="' . $url . '&pg=2#report">2</a> <a href="' . $url . '&pg=3#report">3</a> <a href="' . $url . '&pg=4#report">4</a> <a href="' . $url . '&pg=5#report">5</a> <span>…</span> <a href="' . $url . '&pg=11#report">11</a> <span>…</span> <a href="' . $url . '&pg=25#report">25</a> <a href="' . $url . '&pg=2#report" class="next">→</a></div>';
		$this->assertEquals($a, paginator(1, 25));
		
		$b = '<div class="paginator"><a href="' . $url . '&pg=10#report" class="prev">←</a> <a href="' . $url . '#report">1</a> <span>…</span> <a href="' . $url . '&pg=6#report">6</a> <a href="' . $url . '&pg=7#report">7</a> <a href="' . $url . '&pg=8#report">8</a> <a href="' . $url . '&pg=9#report">9</a> <a href="' . $url . '&pg=10#report">10</a> <span class="current">11</span> <a href="' . $url . '&pg=12#report">12</a> <a href="' . $url . '&pg=13#report">13</a> <a href="' . $url . '&pg=14#report">14</a> <a href="' . $url . '&pg=15#report">15</a> <span>…</span> <a href="' . $url . '&pg=21#report">21</a> <span>…</span> <a href="' . $url . '&pg=25#report">25</a> <a href="' . $url . '&pg=12#report" class="next">→</a></div>';
		$this->assertEquals($b, paginator(11, 25));
		
		$c = '<div class="paginator"><a href="' . $url . '&pg=12#report" class="prev">←</a> <a href="' . $url . '#report">1</a> <span>…</span> <a href="' . $url . '&pg=3#report">3</a> <span>…</span> <a href="' . $url . '&pg=8#report">8</a> <a href="' . $url . '&pg=9#report">9</a> <a href="' . $url . '&pg=10#report">10</a> <a href="' . $url . '&pg=11#report">11</a> <a href="' . $url . '&pg=12#report">12</a> <span class="current">13</span> <a href="' . $url . '&pg=14#report">14</a> <a href="' . $url . '&pg=15#report">15</a> <a href="' . $url . '&pg=16#report">16</a> <a href="' . $url . '&pg=17#report">17</a> <span>…</span> <a href="' . $url . '&pg=23#report">23</a> <span>…</span> <a href="' . $url . '&pg=25#report">25</a> <a href="' . $url . '&pg=14#report" class="next">→</a></div>';
		$this->assertEquals($c, paginator(13, 25));
		
		$d = '<div class="paginator"><a href="' . $url . '&pg=13#report" class="prev">←</a> <a href="' . $url . '#report">1</a> <span>…</span> <a href="' . $url . '&pg=4#report">4</a> <span>…</span> <a href="' . $url . '&pg=9#report">9</a> <a href="' . $url . '&pg=10#report">10</a> <a href="' . $url . '&pg=11#report">11</a> <a href="' . $url . '&pg=12#report">12</a> <a href="' . $url . '&pg=13#report">13</a> <span class="current">14</span> <a href="' . $url . '&pg=15#report">15</a> <a href="' . $url . '&pg=16#report">16</a> <a href="' . $url . '&pg=17#report">17</a> <a href="' . $url . '&pg=18#report">18</a> <span>…</span> <a href="' . $url . '&pg=24#report">24</a> <a href="' . $url . '&pg=25#report">25</a> <a href="' . $url . '&pg=15#report" class="next">→</a></div>';
		$this->assertEquals($d, paginator(14, 25));
		
		$e = '<div class="paginator"><a href="' . $url . '&pg=24#report" class="prev">←</a> <a href="' . $url . '#report">1</a> <span>…</span> <a href="' . $url . '&pg=15#report">15</a> <span>…</span> <a href="' . $url . '&pg=20#report">20</a> <a href="' . $url . '&pg=21#report">21</a> <a href="' . $url . '&pg=22#report">22</a> <a href="' . $url . '&pg=23#report">23</a> <a href="' . $url . '&pg=24#report">24</a> <span class="current">25</span></div>';
		$this->assertEquals($e, paginator(25, 25));
	}
}
