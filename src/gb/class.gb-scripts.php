<?php
/**
 * GeniBase Scripts enqueue.
 *
 * These classes were refactored from the GeniBase GB_Scripts and GeniBase
 * script enqueue API.
 *
 * @package GeniBase
 * @since	2.0.0
 * 
 * @copyright	Copyright © WordPress Team
 * @copyright	Partially copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 */

// Direct execution forbidden for this script
if (! defined('GB_VERSION') || count(get_included_files()) == 1)
    die('<b>ERROR:</b> Direct execution forbidden!');

/**
 * GeniBase Scripts enqueue class.
 *
 * @package GeniBase
 * @uses GB_Dependencies
 * @since 2.0.0
 */
class GB_Scripts extends GB_Dependencies
{

    public $base_url;
    // Full URL with trailing slash
    public $content_url;

    public $default_version;

    public $in_footer = array();

    public $concat = '';

    public $concat_version = '';

    public $do_concat = false;

    public $print_html = '';

    public $print_code = '';

    public $ext_handles = '';

    public $ext_version = '';

    public $default_dirs;

    public function __construct()
    {
        $this->init();
        if (class_exists('GB_Hooks'))
            GB_Hooks::add_action('init', array(
                $this,
                'init'
            ), 0);
        else
            $this->init();
    }

    public function init()
    {
        /**
         * Fires when the GB_Scripts instance is initialized.
         *
         * @since 2.1.1
         *       
         * @param
         *            GB_Scripts &$this GB_Scripts instance, passed by reference.
         */
        GB_Hooks::do_action_ref_array('gb_default_scripts', array(
            &$this
        ));
    }

    /**
     * Prints scripts.
     *
     * Prints the scripts passed to it or the print queue. Also prints all necessary dependencies.
     *
     * @param mixed $handles
     *            Optional. Scripts to be printed. (void) prints queue, (string) prints
     *            that script, (array of strings) prints those scripts. Default false.
     * @param int $group
     *            Optional. If scripts were queued in groups prints this group number.
     *            Default false.
     * @return array Scripts that have been printed.
     */
    public function print_scripts($handles = false, $group = false)
    {
        return $this->do_items($handles, $group);
    }

    public function print_extra_script($handle, $echo = true)
    {
        if (! $output = $this->get_data($handle, 'data'))
            return;
        
        if (! $echo)
            return $output;
        
        echo "<script type='text/javascript'>\n"; // CDATA and type='text/javascript' is not needed for HTML 5
        echo "/* <![CDATA[ */\n";
        echo "$output\n";
        echo "/* ]]> */\n";
        echo "</script>\n";
        
        return true;
    }

    public function do_item($handle, $group = false)
    {
        if (! parent::do_item($handle))
            return false;
        
        if (0 === $group && $this->groups[$handle] > 0) {
            $this->in_footer[] = $handle;
            return false;
        }
        
        if (false === $group && in_array($handle, $this->in_footer, true))
            $this->in_footer = array_diff($this->in_footer, (array) $handle);
        
        $obj = $this->registered[$handle];
        
        if (null === $obj->ver)
            $ver = '';
        else
            $ver = $obj->ver ? $obj->ver : $this->default_version;
        
        if (isset($this->args[$handle]))
            $ver = $ver ? $ver . '&amp;' . $this->args[$handle] : $this->args[$handle];
        
        $src = $obj->src;
        $cond_before = $cond_after = '';
        $conditional = isset($obj->extra['conditional']) ? $obj->extra['conditional'] : '';
        
        if ($conditional) {
            $cond_before = "<!--[if {$conditional}]>\n";
            $cond_after = "<![endif]-->\n";
        }
        
        if ($this->do_concat) {
            /**
             * Filter the script loader source.
             *
             * @since 2.1.1
             *       
             * @param string $src
             *            Script loader source path.
             * @param string $handle
             *            Script handle.
             */
            $srce = GB_Hooks::apply_filters('script_loader_src', $src, $handle);
            if ($this->in_default_dir($srce)) {
                $this->print_code .= $this->print_extra_script($handle, false);
                $this->concat .= "$handle,";
                $this->concat_version .= "$handle$ver";
                return true;
            } else {
                $this->ext_handles .= "$handle,";
                $this->ext_version .= "$handle$ver";
            }
        }
        
        $has_conditional_data = $conditional && $this->get_data($handle, 'data');
        
        if ($has_conditional_data)
            echo $cond_before;
        
        $this->print_extra_script($handle);
        
        if ($has_conditional_data)
            echo $cond_after;
        
        if (! preg_match('|^(https?:)?//|', $src) && ! ($this->content_url && 0 === strpos($src, $this->content_url))) {
            $src = $this->base_url . $src;
        }
        
        if (! empty($ver))
            $src = add_query_arg('ver', $ver, $src);
        
        /**
         * This filter is documented in gb/class.gb-scripts.php
         */
        $src = esc_url(GB_Hooks::apply_filters('script_loader_src', $src, $handle));
        
        if (! $src)
            return true;
        
        $tag = "{$cond_before}<script type='text/javascript' src='$src'></script>\n{$cond_after}";
        
        /**
         * Filter the HTML script tag of an enqueued script.
         *
         * @since 2.1.1
         *       
         * @param string $tag
         *            The `<script>` tag for the enqueued script.
         * @param string $handle
         *            The script's registered handle.
         * @param string $src
         *            The script's source URL.
         */
        $tag = GB_Hooks::apply_filters('script_loader_tag', $tag, $handle, $src);
        
        if ($this->do_concat) {
            $this->print_html .= $tag;
        } else {
            echo $tag;
        }
        
        return true;
    }

    /**
     * Localizes a script
     *
     * Localizes only if the script has already been added
     */
    public function localize($handle, $object_name, $l10n)
    {
        if ($handle === 'jquery')
            $handle = 'jquery-core';
        
        if (is_array($l10n) && isset($l10n['l10n_print_after'])) { // back compat, preserve the code in 'l10n_print_after' if present
            $after = $l10n['l10n_print_after'];
            unset($l10n['l10n_print_after']);
        }
        
        foreach ((array) $l10n as $key => $value) {
            if (! is_scalar($value))
                continue;
            
            $l10n[$key] = html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
        }
        
        $script = "var $object_name = " . gb_json_encode($l10n) . ';';
        
        if (! empty($after))
            $script .= "\n$after;";
        
        $data = $this->get_data($handle, 'data');
        
        if (! empty($data))
            $script = "$data\n$script";
        
        return $this->add_data($handle, 'data', $script);
    }

    public function set_group($handle, $recursion, $group = false)
    {
        if ($this->registered[$handle]->args === 1)
            $grp = 1;
        else
            $grp = (int) $this->get_data($handle, 'group');
        
        if (false !== $group && $grp > $group)
            $grp = $group;
        
        return parent::set_group($handle, $recursion, $grp);
    }

    public function all_deps($handles, $recursion = false, $group = false)
    {
        $r = parent::all_deps($handles, $recursion);
        if (! $recursion) {
            /**
             * Filter the list of script dependencies left to print.
             *
             * @since 2.1.1
             *       
             * @param array $to_do
             *            An array of script dependencies.
             */
            $this->to_do = GB_Hooks::apply_filters('print_scripts_array', $this->to_do);
        }
        return $r;
    }

    public function do_head_items()
    {
        $this->do_items(false, 0);
        return $this->done;
    }

    public function do_footer_items()
    {
        $this->do_items(false, 1);
        return $this->done;
    }

    public function in_default_dir($src)
    {
        if (! $this->default_dirs) {
            return true;
        }
        
        if (0 === strpos($src, '/' . GB_CORE_DIR . '/js/l10n')) {
            return false;
        }
        
        foreach ((array) $this->default_dirs as $test) {
            if (0 === strpos($src, $test)) {
                return true;
            }
        }
        return false;
    }

    public function reset()
    {
        $this->do_concat = false;
        $this->print_code = '';
        $this->concat = '';
        $this->concat_version = '';
        $this->print_html = '';
        $this->ext_version = '';
        $this->ext_handles = '';
    }
}
