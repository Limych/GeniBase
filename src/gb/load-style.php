<?php
/**
 * GeniBase CSS loader and compressor
 *
 * @package GeniBase
 *
 * @copyright	Copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 * @copyright	Partially copyright © 2012-2014 Leaf Corcoran
 */
define('GB_SHORTINIT', true);
define('BASE_DIR', dirname(dirname(__FILE__)));

require BASE_DIR . '/gb-load.php';

if (! defined('GB_CSS_EXPIRES_OFFSET'))
    define('GB_CSS_EXPIRES_OFFSET', 31536000); // 1 year

/**
 *
 * @see http://leafo.github.io/scssphp/docs/
 *
 */
require GB_CORE_DIR . '/scssphp/scss.inc.php';

/**
 * SCSS server
 */
class GB_SCSS_Server extends Leafo\ScssPhp\Server
{

    protected $cache_salt = '';

    protected $rootDirs;

    protected $compiler;

    /**
     * Constructor
     *
     * @param string $cacheDir
     *            Cache directory
     * @param \Leafo\ScssPhp\Compiler|null $scss
     *            SCSS compiler instance
     */
    public function __construct($scss = null)
    {
        $this->rootDirs = array(
            '' => GB_CORE_DIR . '/scss',
            'gb-admin' => GB_ADMIN_DIR . '/scss'
        );
        
        /**
         * Filter the root dirs for CSS files.
         *
         * @since 3.0.0
         *       
         * @param array $root_dirs
         *            The root dirs for CSS files.
         */
        $this->rootDirs = GB_Hooks::apply_filters('css_root_dirs', $this->rootDirs);
        
        $this->compiler = $scss;
        if ($this->compiler)
            $this->compiler->setImportPaths(array(
                array(
                    $this,
                    'translatePath'
                )
            ));
        
        $sourceDir = GB_CORE_DIR . '/scss';
        
        parent::__construct($sourceDir, null, $scss);
    }

    /**
     * Translate path from "section:relative_path" to absolute path
     *
     * @param string $url
     *            path as "section:relative_path".
     * @return string path to SCSS file or NULL if file not exists.
     */
    function translatePath($url)
    {
        $url = ltrim($url, '/');
        preg_match('|(?:([^\:\/]+)\:)?(.+)|usi', $url, $matches);
        if (! isset($this->rootDirs[$matches[1]]))
            $matches[1] = '';
        
        $urls = [
            $url
        ];
        
        // for "normal" scss imports (ignore vanilla css and external requests)
        if (! preg_match('/\.css$|^https?:\/\//', $matches[2])) {
            // try both normal and the _partial filename
            $urls = [
                preg_replace('/[^\/]+$/', '_\0', $matches[2]),
                $url
            ];
        }
        
        // check urls for normal import paths
        foreach ($urls as $full) {
            $full = $this->join($this->rootDirs[$matches[1]], $full);
            
            if ((is_file($file = $full . '.scss') || is_file($file = $full)) && is_readable($file)) {
                return $file;
            }
        }
        
        return null;
    }

    /**
     * Get path to requested .
     *
     * scss file
     *
     * @return string
     */
    protected function findInput()
    {
        if (($input = $this->inputName()) && strpos($input, '..') === false) {
            if (substr($input, - 4) === '.min') {
                $input = substr($input, 0, - 4);
                $this->cache_salt .= '-min';
                if (! empty($this->compiler))
                    $this->compiler->setFormatter("Leafo\ScssPhp\Formatter\Compressed");
            }
            
            $fpath = $this->translatePath($input);
            if (null !== $fpath)
                return $fpath;
        }
        
        return false;
    }

    /**
     *
     * @param string $out            
     */
    protected function output($out)
    {
        // $compress = ( isset($_GET['c']) && $_GET['c'] );
        // $force_gzip = ( $compress && 'gzip' == $_GET['c'] );
        $force_gzip = false;
        if( /* $compress &&  */! ini_get('zlib.output_compression') && 'ob_gzhandler' != ini_get('output_handler') && isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
            header('Vary: Accept-Encoding'); // Handle proxies
            if (false !== stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate') && function_exists('gzdeflate') && ! $force_gzip) {
                header('Content-Encoding: deflate');
                $out = gzdeflate($out, 3);
            } elseif (false !== stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') && function_exists('gzencode')) {
                header('Content-Encoding: gzip');
                $out = gzencode($out, 3);
            }
        }
        echo $out;
    }

    /**
     * Compile requested scss and serve css.
     * Outputs HTTP response.
     *
     * @param string $salt
     *            Prefix a string to the filename for creating the cache name hash
     */
    public function serve($salt = '')
    {
        $this->cache_salt = $salt;
        $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
        
        if ($input = $this->findInput()) {
            $output = $this->cacheName($this->cache_salt . $input);
            $etag = $noneMatch = trim($this->getIfNoneMatchHeader(), '"');
            
            if ($this->needsCompile($input, $output, $etag)) {
                try {
                    list ($css, $etag) = $this->compile($input, $output);
                    
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
            
            $lastModified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
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
$compiler->setFormatter("Leafo\ScssPhp\Formatter\Expanded");

$server = new GB_SCSS_Server($compiler);
$server->serve();
exit();
