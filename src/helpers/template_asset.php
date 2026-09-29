<?php

/**
 * Template Asset Helpers
 * 
 * Fungsi-fungsi ini adalah wrapper untuk asset management yang spesifik.
 * Fungsi utama seperti theme_asset(), render_css(), render_js(), render_meta()
 * sudah didefinisikan di template.php dan TIDAK diulang di sini.
 */

if (!function_exists('theme_css')) {
    function theme_css($file, $theme = null)
    {
        return theme_asset('css/' . ltrim($file, '/'), $theme);
    }
}

if (!function_exists('theme_js')) {
    function theme_js($file, $theme = null)
    {
        return theme_asset('js/' . ltrim($file, '/'), $theme);
    }
}

if (!function_exists('theme_img')) {
    function theme_img($file, $theme = null)
    {
        return theme_asset('img/' . ltrim($file, '/'), $theme);
    }
}

if (!function_exists('add_css')) {
    function add_css($file, $group = 'theme', $attributes = [], $priority = 10)
    {
        if (isset(app()->assets) && app()->assets instanceof \Kodhe\Framework\Assets\Assets) {
            return app()->assets->add_css($file, $group, $attributes, $priority);
        }
        
        // Fallback: langsung output link tag
        $url = theme_asset('css/' . ltrim($file, '/'));
        $attrs = '';
        foreach ($attributes as $key => $value) {
            if (is_numeric($key)) {
                $attrs .= $value . ' ';
            } else {
                $attrs .= $key . '="' . htmlspecialchars($value) . '" ';
            }
        }
        
        echo '<link rel="stylesheet" href="' . $url . '" ' . trim($attrs) . '>' . PHP_EOL;
        return null;
    }
}

if (!function_exists('add_js')) {
    function add_js($file, $group = 'theme', $attributes = [], $position = 'footer', $priority = 10)
    {
        if (isset(app()->assets) && app()->assets instanceof \Kodhe\Framework\Assets\Assets) {
            return app()->assets->add_js($file, $group, $attributes, $position, $priority);
        }
        
        // Fallback: langsung output script tag
        $url = theme_asset('js/' . ltrim($file, '/'));
        $attrs = '';
        foreach ($attributes as $key => $value) {
            if (is_numeric($key)) {
                $attrs .= $value . ' ';
            } else {
                $attrs .= $key . '="' . htmlspecialchars($value) . '" ';
            }
        }
        
        if ($position === 'header') {
            echo '<script src="' . $url . '" ' . trim($attrs) . '></script>' . PHP_EOL;
        } else {
            if (!isset(app()->_footer_js)) {
                app()->_footer_js = [];
            }
            app()->_footer_js[] = '<script src="' . $url . '" ' . trim($attrs) . '></script>' . PHP_EOL;
        }
        return null;
    }
}

if (!function_exists('add_inline_css')) {
    function add_inline_css($css, $priority = 10)
    {
        if (isset(app()->assets) && app()->assets instanceof \Kodhe\Framework\Assets\Assets) {
            return app()->assets->add_inline_css($css, $priority);
        }
        
        // Fallback: langsung output style tag
        echo '<style>' . $css . '</style>' . PHP_EOL;
        return null;
    }
}

if (!function_exists('add_inline_js')) {
    function add_inline_js($js, $position = 'footer', $priority = 10)
    {
        if (isset(app()->assets) && app()->assets instanceof \Kodhe\Framework\Assets\Assets) {
            return app()->assets->add_inline_js($js, $position, $priority);
        }
        
        // Fallback: langsung output script tag
        if ($position === 'header') {
            echo '<script>' . $js . '</script>' . PHP_EOL;
        } else {
            if (!isset(app()->_footer_inline_js)) {
                app()->_footer_inline_js = [];
            }
            app()->_footer_inline_js[] = '<script>' . $js . '</script>' . PHP_EOL;
        }
        return null;
    }
}

if (!function_exists('asset_exists')) {
    function asset_exists($path, $theme = null)
    {
        if (isset(app()->assets) && app()->assets instanceof \Kodhe\Framework\Assets\Assets) {
            return app()->assets->exists($path, $theme);
        }
        
        // Fallback check
        if (!$theme && isset(app()->template) && method_exists(app()->template, 'theme')) {
            $theme = app()->template->theme()->getTheme();
        }
        
        if ($theme) {
            $full_path = FCPATH . 'themes/' . $theme . '/assets/' . ltrim($path, '/');
            return file_exists($full_path);
        }
        
        return false;
    }
}

if (!function_exists('clear_assets')) {
    function clear_assets($type = null)
    {
        if (isset(app()->assets) && app()->assets instanceof \Kodhe\Framework\Assets\Assets) {
            return app()->assets->clear_assets($type);
        }
        
        return null;
    }
}