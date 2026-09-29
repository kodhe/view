<?php

use Kodhe\Framework\View\ViewFactory;

// =====================
// TEMPLATE ASSET STATE
// =====================

/**
 * Global template state untuk asset management
 */
function &get_template_state()
{
    static $state = [
        'platform' => null,
        'theme' => null,
        'assets' => [
            'header' => [
                'css' => [],
                'js' => [],
                'meta' => [],
                'title' => ''
            ],
            'footer' => [
                'js' => []
            ]
        ],
        'layout' => null
    ];
    
    return $state;
}

/**
 * Get template assets untuk dikirim ke view
 */
function get_template_assets()
{
    $state =& get_template_state();
    
    // Pastikan title selalu ada, bahkan jika kosong
    if (empty($state['assets']['header']['title'])) {
        $state['assets']['header']['title'] = '';
    }
    
    return [
        'template' => [
            'assets' => $state['assets'],
            'platform' => $state['platform'],
            'theme' => get_active_theme()
        ],
        'title' => $state['assets']['header']['title']
    ];
}

/**
 * Reset template state
 */
function reset_template_state()
{
    $state =& get_template_state();
    $state['assets'] = [
        'header' => [
            'css' => [],
            'js' => [],
            'meta' => [],
            'title' => ''
        ],
        'footer' => [
            'js' => []
        ]
    ];
    $state['layout'] = null;
}

// =====================
// ASSET MANAGEMENT
// =====================

if (!function_exists('set_css')) {
    function set_css($css_file, $source = 'local')
    {
        $state =& get_template_state();
        
        if (!$state['platform']) {
            throw new \Exception('Please set platform first using set_platform()');
        }

        if ($source === 'remote') {
            $url = $css_file;
        } else {
            $theme = get_active_theme();
            $url = 'assets/' . $state['platform'] . '/themes/' . $theme . '/css/' . $css_file;

            if (!file_exists($url)) {
                throw new \Exception("Cannot locate stylesheet file: {$url}.");
            }
        }

        $state['assets']['header']['css'][] = '<link rel="stylesheet" type="text/css" href="' . base_url($url) . '">';
    }
}

if (!function_exists('set_js')) {
    function set_js($js_file, $location = 'header', $source = 'local')
    {
        $state =& get_template_state();
        
        if (!$state['platform']) {
            throw new \Exception('Please set platform first using set_platform()');
        }
        
        if ($source === 'remote') {
            $url = $js_file;
        } else {
            $theme = get_active_theme();
            $url = 'assets/' . $state['platform'] . '/themes/' . $theme . '/js/' . $js_file;

            if (!file_exists($url)) {
                throw new \Exception("Cannot locate javascript file: {$url}.");
            }
        }

        $location = in_array($location, ['header', 'footer']) ? $location : 'header';
        $state['assets'][$location]['js'][] = '<script type="text/javascript" src="' . base_url($url) . '"></script>';
    }
}

if (!function_exists('set_meta')) {
    function set_meta($name, $content)
    {
        $state =& get_template_state();
        $state['assets']['header']['meta'][] = '<meta name="' . $name . '" content="' . $content . '">';
    }
}

if (!function_exists('set_title')) {
    function set_title($title)
    {
        $state =& get_template_state();
        $state['assets']['header']['title'] = '<title>' . $title . '</title>';
    }
}

if (!function_exists('set_platform')) {
    function set_platform($platform)
    {
        $state =& get_template_state();
        
        if (!is_dir('assets/' . $platform)) {
            throw new \Exception("Cannot find platform folder: {$platform}.");
        }
        
        $state['platform'] = $platform;
    }
}

if (!function_exists('set_theme')) {
    function set_theme($theme)
    {
        $state =& get_template_state();
        
        if ($state['platform']) {
            $themePath = 'assets/' . $state['platform'] . '/themes/' . $theme;
            if (!is_dir($themePath)) {
                throw new \Exception("Cannot find theme folder: {$theme}.");
            }
        }
        
        $state['theme'] = $theme;
        
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            try {
                if (!app()->template->isThemeEnabled()) {
                    app()->template->enableTheme(true);
                }
                app()->template->setTheme($theme);
                log_message('debug', 'Theme changed via template instance: ' . $theme);
            } catch (\Exception $e) {
                log_message('error', 'Failed to set theme in template: ' . $e->getMessage());
            }
        } else {
            try {
                app()->template = new \Kodhe\Framework\View\ViewFactory([
                    'theme_enabled' => true,
                    'theme_default' => $theme
                ]);
                log_message('debug', 'Created new template instance with theme: ' . $theme);
            } catch (\Exception $e) {
                log_message('error', 'Failed to create template instance: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('set_layout')) {
    function set_layout($layout)
    {
        $state =& get_template_state();
        $state['layout'] = $layout;
    }
}

if (!function_exists('clear_layout')) {
    function clear_layout()
    {
        $state =& get_template_state();
        $state['layout'] = null;
    }
}

// =====================
// RENDER HELPERS
// =====================

if (!function_exists('render_css')) {
    function render_css()
    {
        $state =& get_template_state();
        return implode("\n\t", $state['assets']['header']['css']);
    }
}

if (!function_exists('render_js')) {
    function render_js($location = 'header')
    {
        $state =& get_template_state();
        return implode("\n\t", $state['assets'][$location]['js'] ?? []);
    }
}

if (!function_exists('render_meta')) {
    function render_meta()
    {
        $state =& get_template_state();
        return implode("\n\t", $state['assets']['header']['meta']);
    }
}

if (!function_exists('render_title')) {
    function render_title()
    {
        $state =& get_template_state();
        return $state['assets']['header']['title'];
    }
}

// =====================
// INCLUDE HELPERS
// =====================

if (!function_exists('include_partial')) {
    function include_partial($partial, $data = [], $return = false)
    {
        $partialView = 'partials/' . $partial;
        
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            if ($return) {
                return app()->template->view($partialView, $data, true);
            }
            
            echo app()->template->view($partialView, $data, true);
            return;
        }
        
        if ($return) {
            return app()->load->view($partialView, $data, true);
        }
        
        app()->load->view($partialView, $data);
    }
}

if (!function_exists('include_widget')) {
    function include_widget($widget, $params = [], $return = false)
    {
        $widgetView = 'widgets/' . $widget;
        
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            if ($return) {
                return app()->template->view($widgetView, $params, true);
            }
            
            echo app()->template->view($widgetView, $params, true);
            return;
        }
        
        if ($return) {
            return app()->load->view($widgetView, $params, true);
        }
        
        app()->load->view($widgetView, $params);
    }
}

if (!function_exists('include_region')) {
    function include_region($region, $data = [], $return = false)
    {
        if (class_exists('Widget_manager')) {
            app()->load->library('widget_manager');
            if (method_exists(app()->widget_manager, 'render_region')) {
                $output = app()->widget_manager->render_region($region, $data);
                if ($return) {
                    return $output;
                }
                echo $output;
                return;
            }
        }
        
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            $output = app()->template->view('regions/' . $region, $data, true);
            if ($return) {
                return $output;
            }
            echo $output;
            return;
        }
        
        return include_partial('regions/' . $region, $data, $return);
    }
}

if (!function_exists('include_section')) {
    function include_section($section, $data = [], $return = false)
    {
        $sectionView = 'sections/' . $section;
        
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            if ($return) {
                return app()->template->view($sectionView, $data, true);
            }
            
            echo app()->template->view($sectionView, $data, true);
            return;
        }
        
        if ($return) {
            return app()->load->view($sectionView, $data, true);
        }
        
        app()->load->view($sectionView, $data);
    }
}

if (!function_exists('include_element')) {
    function include_element($element, $data = [], $return = false)
    {
        $elementView = 'elements/' . $element;
        
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            if ($return) {
                return app()->template->view($elementView, $data, true);
            }
            
            echo app()->template->view($elementView, $data, true);
            return;
        }
        
        if ($return) {
            return app()->load->view($elementView, $data, true);
        }
        
        app()->load->view($elementView, $data);
    }
}

if (!function_exists('include_block')) {
    function include_block($block, $data = [], $return = false)
    {
        $blockView = 'blocks/' . $block;
        
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            if ($return) {
                return app()->template->view($blockView, $data, true);
            }
            
            echo app()->template->view($blockView, $data, true);
            return;
        }
        
        if ($return) {
            return app()->load->view($blockView, $data, true);
        }
        
        app()->load->view($blockView, $data);
    }
}

if (!function_exists('include_module')) {
    function include_module($module, $data = [], $return = false)
    {
        $moduleView = 'modules/' . $module;
        
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            if ($return) {
                return app()->template->view($moduleView, $data, true);
            }
            
            echo app()->template->view($moduleView, $data, true);
            return;
        }
        
        if ($return) {
            return app()->load->view($moduleView, $data, true);
        }
        
        app()->load->view($moduleView, $data);
    }
}

// =====================
// MAIN VIEW FUNCTION
// =====================

if (!function_exists('view')) {
    function view($view, $data = [], $return = false, $layout = null, $options = [])
    {
        static $template = null;
        static $rendering_layout = false;
        
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            $template = app()->template;
        } elseif ($template === null) {
            $template = new \Kodhe\Framework\View\ViewFactory($options);
            app()->template = $template;
        }
        
        $state =& get_template_state();
        
        if ($rendering_layout) {
            unset($data['_layout']);
        }
        
        if ($layout !== null) {
            $data['_layout'] = $layout;
        } elseif ($state['layout'] !== null && !$rendering_layout) {
            $data['_layout'] = $state['layout'];
            $state['layout'] = null;
        }
        
        $view = format_view_path($view);
        
        if (!empty($data['_layout'])) {
            $rendering_layout = true;
        }
        
        $result = $template->view($view, $data, $return);
        
        $rendering_layout = false;
        
        return $result;
    }
}

if (!function_exists('apply_layout_config')) {
    function apply_layout_config($template, $layout)
    {
        if (is_string($layout)) {
            $template->layout($layout);
        }
        elseif (is_array($layout)) {
            if (isset($layout['name'])) {
                $template->layout($layout['name']);
            }
            
            if (isset($layout['data']) && is_array($layout['data'])) {
                foreach ($layout['data'] as $key => $value) {
                    $template->set($key, $value);
                }
            }
            
            if (isset($layout['sections']) && is_array($layout['sections'])) {
                foreach ($layout['sections'] as $name => $content) {
                    if (method_exists($template, 'section')) {
                        $template->section($name);
                        echo $content;
                        $template->endsection();
                    }
                }
            }
            
            if (isset($layout['extends'])) {
                if (method_exists($template, 'extends')) {
                    $template->extends($layout['extends']);
                }
            }
        }
    }
}

if (!function_exists('format_view_path')) {
    function format_view_path($view)
    {
        $view = preg_replace('/\.(blade\.php|php|lex\.php)$/i', '', $view);
        $view = str_replace('.', '/', $view);
        
        return $view;
    }
}

// =====================
// THEME FUNCTIONS
// =====================

if (!function_exists('theme_asset')) {
    function theme_asset($path, $theme = null)
    {
        if (isset(app()->assets) && app()->assets instanceof \Kodhe\Framework\View\AssetManager) {
            return app()->assets->theme_asset($path);
        }
        
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            $template = app()->template;
        } else {
            static $template = null;
            if ($template === null) {
                $template = new ViewFactory(['theme_enabled' => true]);
            }
        }
        
        if ($theme !== null && $theme !== $template->getTheme()) {
            $template->setTheme($theme);
        }
        
        return $template->themeAsset($path);
    }
}

if (!function_exists('theme_info')) {
    function theme_info($key = null, $theme = null)
    {
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            $template = app()->template;
        } else {
            static $template = null;
            if ($template === null) {
                $template = new ViewFactory(['theme_enabled' => true]);
            }
        }
        
        if ($theme !== null && $theme !== $template->getTheme()) {
            $template->setTheme($theme);
        }
        
        return $template->getThemeInfo($key);
    }
}

if (!function_exists('get_active_theme')) {
    function get_active_theme()
    {
        // 1. Cek dari session preview (prioritas tertinggi)
        if (isset(app()->session) && $preview = app()->session->userdata('theme_preview')) {
            return $preview;
        }
        
        // 2. Cek dari template instance (theme yang sedang aktif)
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            if (app()->template->isThemeEnabled()) {
                try {
                    $themeManager = app()->template->theme();
                    if ($themeManager) {
                        $activeTheme = $themeManager->getTheme();
                        if ($activeTheme) {
                            return $activeTheme;
                        }
                    }
                } catch (\Exception $e) {
                    $activeTheme = app()->template->getTheme();
                    if ($activeTheme) {
                        return $activeTheme;
                    }
                }
            }
        }
        
        // 3. Fallback ke template state
        $state =& get_template_state();
        if (!empty($state['theme'])) {
            return $state['theme'];
        }
        
        // 4. Last resort: default
        return 'default';
    }
}

if (!function_exists('get_available_themes')) {
    function get_available_themes()
    {
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            return app()->template->getAvailableThemes();
        }
        
        static $template = null;
        if ($template === null) {
            $template = new ViewFactory(['theme_enabled' => true]);
        }
        
        return $template->getAvailableThemes();
    }
}

if (!function_exists('set_theme_preview')) {
    function set_theme_preview($theme)
    {
        app()->load->library('session');
        app()->session->set_userdata('theme_preview', $theme);
        
        $state =& get_template_state();
        $state['theme'] = $theme;
        
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            try {
                app()->template->setTheme($theme);
            } catch (\Exception $e) {
                log_message('error', 'Failed to set theme preview: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('clear_theme_preview')) {
    function clear_theme_preview()
    {
        app()->load->library('session');
        app()->session->unset_userdata('theme_preview');
        
        app()->config->load('template', true);
        $config = app()->config->item('template');
        $defaultTheme = $config['theme_default'] ?? 'default';
        
        $state =& get_template_state();
        $state['theme'] = $defaultTheme;
        
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            try {
                app()->template->setTheme($defaultTheme);
            } catch (\Exception $e) {
                log_message('error', 'Failed to reset theme: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('is_mobile_theme')) {
    function is_mobile_theme()
    {
        $active_theme = get_active_theme();
        app()->config->load('template', true);
        $config = app()->config->item('template');
        
        return isset($config['theme_mobile']) && $active_theme === $config['theme_mobile'];
    }
}

if (!function_exists('is_admin_theme')) {
    function is_admin_theme()
    {
        $active_theme = get_active_theme();
        app()->config->load('template', true);
        $config = app()->config->item('template');
        
        return isset($config['theme_admin']) && $active_theme === $config['theme_admin'];
    }
}

if (!function_exists('theme_view')) {
    function theme_view($view, $data = [], $return = false, $theme = null, $layout = null, $options = [])
    {
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            $template = app()->template;
        } else {
            static $template = null;
            if ($template === null) {
                $options['theme_enabled'] = true;
                $template = new ViewFactory($options);
            }
        }
        
        if ($theme !== null) {
            $template->setTheme($theme);
        }
        
        if ($layout !== null) {
            apply_layout_config($template, $layout);
        }
        
        $view = format_view_path($view);
        $data['_theme_view'] = true;
        
        return $template->view($view, $data, $return);
    }
}

if (!function_exists('template_exists')) {
    function template_exists($view)
    {
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            return app()->template->exists($view);
        }
        
        static $template = null;
        if ($template === null) {
            $template = new ViewFactory();
        }
        
        return $template->exists($view);
    }
}

if (!function_exists('theme_variant')) {
    function theme_variant()
    {
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            try {
                return app()->template->getVariant();
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }
}

if (!function_exists('is_mobile_variant')) {
    function is_mobile_variant()
    {
        return theme_variant() === 'mobile';
    }
}

if (!function_exists('theme_switch_variant')) {
    function theme_switch_variant($variant)
    {
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            try {
                app()->template->setVariant($variant);
                app()->session->set_userdata('theme_variant', $variant);
                return true;
            } catch (\Exception $e) {
                log_message('error', 'Failed to switch variant: ' . $e->getMessage());
            }
        }
        return false;
    }
}

if (!function_exists('enable_theme')) {
    function enable_theme($enabled = true, $themeName = null)
    {
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            $template = app()->template;
        } else {
            $config = ['theme_enabled' => $enabled];
            
            if ($themeName !== null) {
                $config['theme_default'] = $themeName;
            }
            
            $template = new \Kodhe\Framework\View\ViewFactory($config);
            app()->template = $template;
        }
        
        $template->enableTheme($enabled);
        
        if ($enabled && $themeName !== null) {
            $template->setTheme($themeName);
            
            $state =& get_template_state();
            $state['theme'] = $themeName;
        }
        
        return $template;
    }
}

if (!function_exists('disable_theme')) {
    function disable_theme()
    {
        return enable_theme(false);
    }
}

if (!function_exists('debug_theme_paths')) {
    function debug_theme_paths()
    {
        echo '<pre>';
        echo "Active Theme (get_active_theme): " . get_active_theme() . "\n";
        
        if (isset(app()->template) && app()->template instanceof \Kodhe\Framework\View\ViewFactory) {
            echo "Template getTheme(): " . (app()->template->getTheme() ?? 'null') . "\n";
            
            try {
                $themeManager = app()->template->theme();
                if ($themeManager) {
                    echo "ThemeManager getTheme(): " . ($themeManager->getTheme() ?? 'null') . "\n\n";
                    
                    echo "Theme Paths:\n";
                    print_r($themeManager->getThemePaths());
                    
                    echo "\nView Lookup Paths:\n";
                    if ($themeManager->variant()) {
                        print_r($themeManager->variant()->getViewLookupPaths());
                    }
                }
            } catch (\Exception $e) {
                echo "Error: " . $e->getMessage() . "\n";
            }
        }
        echo '</pre>';
    }
}