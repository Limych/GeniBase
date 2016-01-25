<?php
/**
 * GeniBase CSS loader and compressor
 *
 * @package GeniBase
 *
 * @copyright	Copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 * @copyright	Partially copyright © 2012-2014 Leaf Corcoran
 */

/** Absolute path to the root directory of this site. */
if( !defined('BASE_DIR') )
	define('BASE_DIR', dirname(dirname(__FILE__)));

/** Absolute path to the root directory of GeniBase core. */
if( !defined('GB_CORE_DIR') )
	define('GB_CORE_DIR', BASE_DIR . '/gb');

if( !defined('GB_CSS_EXPIRES_OFFSET') )
	define('GB_CSS_EXPIRES_OFFSET', 31536000); // 1 year

if( !defined('GB_SCSS_DIR') )
	define('GB_SCSS_DIR', GB_CORE_DIR . '/scss');



/**
 * @see	http://leafo.net/scssphp/docs/
*/
require 'scssphp/scss.inc.php';

/**
 * SCSS server
 */
class GB_SCSS_Server extends Leafo\ScssPhp\Server {
	protected $cache_salt = '';
	
	protected $rootDir;
	protected $compiler;

    /**
     * Constructor
     *
     * @param string                       $dir      Root directory to .scss files
     * @param string                       $cacheDir Cache directory
     * @param \Leafo\ScssPhp\Compiler|null $scss     SCSS compiler instance
     */
	public function __construct($dir, $cacheDir = null, $scss = null){
		$this->rootDir = $dir;
		$this->compiler = $scss;
		parent::__construct($dir, $cacheDir, $scss);
	}
	
    /**
     * Get path to requested .scss file
     *
     * @return string
     */
    protected function findInput(){
        if( ($input = $this->inputName()) && strpos($input, '..') === false ){
            $name = $this->join($this->rootDir, $input);
            if( substr($name, -4) === '.min' ){
            	$name = substr($name, 0, -4);
            	$this->cache_salt .= '-min';
            	if( !empty($this->compiler) )
        			$this->compiler->setFormatter("Leafo\ScssPhp\Formatter\Compressed");
            }

            $name .= '.scss';
            if( is_file($name) && is_readable($name) )
                return $name;
        }

        return false;
    }

	/**
	 * 
	 * @param string $out
	 */
	protected function output($out){
// 		$compress = ( isset($_GET['c']) && $_GET['c'] );
// 		$force_gzip = ( $compress && 'gzip' == $_GET['c'] );
		$force_gzip = false;
		if( /* $compress &&  */!ini_get('zlib.output_compression') && 'ob_gzhandler' != ini_get('output_handler') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) ){
			header('Vary: Accept-Encoding'); // Handle proxies
			if( false !== stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate') && function_exists('gzdeflate') && !$force_gzip ){
				header('Content-Encoding: deflate');
				$out = gzdeflate($out, 3);
			}elseif( false !== stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') && function_exists('gzencode') ){
				header('Content-Encoding: gzip');
				$out = gzencode($out, 3);
			}
		}
		echo $out;
	}

	/**
	 * Compile requested scss and serve css.  Outputs HTTP response.
	 *
	 * @param string $salt Prefix a string to the filename for creating the cache name hash
	 */
	public function serve($salt = ''){
        $this->cache_salt = $salt;
		$protocol = isset($_SERVER['SERVER_PROTOCOL'])
				? $_SERVER['SERVER_PROTOCOL']
				: 'HTTP/1.0';
	
		if ($input = $this->findInput()) {
			$output = $this->cacheName($this->cache_salt . $input);
			$etag = $noneMatch = trim($this->getIfNoneMatchHeader(), '"');
	
			if ($this->needsCompile($input, $output, $etag)) {
				try {
					list($css, $etag) = $this->compile($input, $output);
	
					$lastModified = gmdate('D, d M Y H:i:s', filemtime($output)) . ' GMT';
	
					header('Last-Modified: ' . $lastModified);
					header('Content-type: text/css; charset=UTF-8');
					header('ETag: "' . $etag . '"');
					header('Expires: ' . gmdate('D, d M Y H:i:s', time() + GB_CSS_EXPIRES_OFFSET) . ' GMT');
					header('Cache-Control: public, max-age=' . GB_CSS_EXPIRES_OFFSET);
	
					$this->output($css);
					return;

				} catch (\Exception $e) {
					header($protocol . ' 500 Internal Server Error');
					header('Content-type: text/plain');
	
					echo 'Parse error: ' . $e->getMessage() . "\n";
				}
			}
	
			header('X-SCSS-Cache: true');
			header('Content-type: text/css; charset=UTF-8');
			header('ETag: "' . $etag . '"');
			header('Expires: ' . gmdate('D, d M Y H:i:s', time() + GB_CSS_EXPIRES_OFFSET) . ' GMT');
			header('Cache-Control: public, max-age=' . GB_CSS_EXPIRES_OFFSET);

			if ($etag === $noneMatch) {
				header($protocol . ' 304 Not Modified');
				return;
			}
	
			$modifiedSince = $this->getIfModifiedSinceHeader();
			$mtime = filemtime($output);
	
			if (@strtotime($modifiedSince) === $mtime) {
				header($protocol . ' 304 Not Modified');
				return;
			}
	
			$lastModified  = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
			header('Last-Modified: ' . $lastModified);
	
			$this->output(file_get_contents($output));
			return;
		}
	
		header($protocol . ' 404 Not Found');
		header('Content-type: text/plain');
	
		$v = Leafo\ScssPhp\Version::VERSION;
		echo "/* INPUT NOT FOUND scss $v */\n";
	}
}



$compiler = new Leafo\ScssPhp\Compiler();
$compiler->setImportPaths(GB_SCSS_DIR);

$server = new GB_SCSS_Server(GB_SCSS_DIR, null, $compiler);
$server->serve();
exit;
