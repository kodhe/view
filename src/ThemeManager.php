<?php

declare(strict_types=1);

namespace Kodhe\Framework\View;

use Exception;

class ThemeManager
{
    protected $CI;
    public $config;
    
    // Theme properties
    protected $theme;
    protected $themeConfig = [];
    protected $themePaths = [];
    protected $themeEnabled = false;
    
    // Managers
    protected $variantManager;
    
    public function __construct($config, $ci)
    {
        $this->CI = $ci;
        $this->config = $config;
        $this->themeEnabled = $config['theme_enabled'] ?? true;
        
        if ($this->themeEnabled) {
            $this->setupTheme();
        }
    }
    
    /**
     * Setup theme system
     */
    protected function setupTheme()
    {
        // Load theme-specific config
        $this->loadThemeConfig();
        
        // Detect active theme
        $this->theme = $this->detectTheme();
        
        // Find theme base path
        $this->themePaths['base'] = $this->findThemePath($this->theme);
  
        if (!$this->themePaths['base']) {
            // Fallback to default theme
            if ($this->config['theme_fallback'] ?? false) {
                log_message('warning', 'Theme not found: ' . $this->theme . ', falling back to default');
                $this->theme = $this->config['theme_default'] ?? 'default';
                $this->themePaths['base'] = $this->findThemePath($this->theme);
            }
            
            if (!$this->themePaths['base']) {
                throw new Exception("Theme not found: " . $this->theme);
            }
        }
        
        // Initialize variant manager
        $this->variantManager = new VariantManager(
            $this->themePaths['base'],
            $this->config,
            $this->CI
        );
        
        // Load theme info
        $this->loadThemeInfo();
        
        // Calculate web path
        $this->themePaths['web'] = str_replace(FCPATH, base_url(), $this->themePaths['base']);
        
        log_message('info', 'Theme loaded: ' . $this->theme);
    }
    
    /**
     * Load theme configuration
     */
    protected function loadThemeConfig()
    {
        // Theme-specific config defaults
        $themeDefaults = [
            'theme_default' => 'default',
            'theme_admin' => 'admin',
            'theme_mobile_detection' => true,
            'theme_mobile_agents' => [
                'Android', 'iPhone', 'iPad', 'iPod', 'BlackBerry',
                'Windows Phone', 'Mobile', 'Opera Mini', 'IEMobile'
            ],
            'theme_locations' => [
                resolve_path(APPPATH, 'themes'),
                VIEWPATH,
                FCPATH, 'themes'
            ],
            'theme_fallback' => true,
            'theme_cache_path' => resolve_path(STORAGEPATH, 'cache') . 'themes'
        ];
        
        $themeDefaults['theme_locations'][] = resolve_path(SYSPATH, 'themes');

        // Merge with existing config
        $this->config = array_merge($themeDefaults, $this->config);
    }
    
    /**
     * Detect active theme
     */
    protected function detectTheme()
    {
        // Check session preview
        if (isset($this->CI->session) && $preview = $this->CI->session->userdata('theme_preview')) {
            log_message('debug', 'Using theme from session preview: ' . $preview);
            return $preview;
        }
        
        // Check admin area
        if (isset($this->CI->router)) {
            $directory = $this->CI->router->directory ?? '';
            $class = $this->CI->router->class ?? '';
        }
        
        log_message('debug', 'Using default theme: ' . $this->config['theme_default']);
        return $this->config['theme_default'];
    }
    
    /**
     * Find theme path in configured locations
     */
    protected function findThemePath($themeName)
    {        
        foreach ($this->config['theme_locations'] as $location) {
            $path = rtrim($location, '/') . '/' . $themeName . '/';
            if (is_dir($path)) {
                return $path;
            }

            //return rtrim($location, '/') . '/';
        }
        return false;
    }
    
    /**
     * Load theme info from theme.php file
     */
    protected function loadThemeInfo()
    {
        // Load theme.php file
        $themeFile = $this->themePaths['base'] . 'theme.php';
        $themeInfo = [];
        
        if (file_exists($themeFile)) {
            include($themeFile);
            if (isset($theme) && is_array($theme)) {
                $themeInfo = $theme;
            }
        }
        
        // Default theme info
        $this->themeConfig = array_merge([
            'name' => ucfirst($this->theme),
            'version' => '1.0.0',
            'author' => 'Unknown',
            'description' => '',
            'type' => 'frontend',
            'parser' => 'blade',
            'layout' => 'default',
            'views_path' => 'views/',
            'assets_path' => 'assets/',
            'regions' => ['header', 'content', 'sidebar', 'footer'],
            'options' => []
        ], $themeInfo);
    }
    
    /**
     * Get configuration for template engine
     */
    public function getConfig()
    {
        $viewsPath = $this->variantManager->getCurrentViewsPath();
        $cachePath = null;
        
        // Update cache path for theme-specific caching
        if (isset($this->config['theme_cache_path'])) {
            $themeCachePath = $this->config['theme_cache_path'] . '/' . $this->theme;
            if ($this->variantManager->getCurrentVariant()) {
                $themeCachePath .= '_' . $this->variantManager->getCurrentVariant();
            }
            $themeCachePath .= '/blade';
            
            if (!is_dir($themeCachePath)) {
                mkdir($themeCachePath, 0755, true);
            }
            $cachePath = $themeCachePath;
        }
        
        return [
            'views_path' => $viewsPath,
            'cache_path' => $cachePath,
            'paths' => $this->variantManager->getViewLookupPaths()
        ];
    }
    
    /**
     * Get template data with theme information
     */
    public function getTemplateData()
    {
        $variant = $this->variantManager->getCurrentVariant();
        $variantData = $this->variantManager->getVariantData();
        
        return array_merge([
            '_theme' => [
                'name' => $this->theme,
                'config' => $this->themeConfig,
                'paths' => $this->themePaths,
                'web_path' => $this->themePaths['web'],
                'assets_path' => $this->themePaths['web'] . 'assets/'
            ],
            'theme_asset' => function($path) {
                return $this->themeAsset($path);
            },
            'theme_url' => $this->themePaths['web']
        ], $variantData);
    }
    
    /**
     * Get theme asset URL
     */
    public function themeAsset($path = '')
    {
        if (!$this->themeEnabled) {
            return base_url('assets/' . ltrim($path, '/'));
        }
        
        $path = ltrim($path, '/');
        return $this->variantManager->resolveAssetUrl($path);
    }
    
    /**
     * Detect engine for a view in theme
     */
    public function detectViewEngine($view)
    {
        if (!$this->themeEnabled) {
            return null;
        }
        
        return $this->variantManager->detectViewEngine($view, $this->config['engines'] ?? []);
    }
    
    /**
     * Check if view exists in theme
     */
    public function viewExists($view)
    {
        if (!$this->themeEnabled) {
            return false;
        }
        
        return $this->variantManager->viewExists($view, $this->config['engines'] ?? []);
    }
    
    /**
     * ======================
     * PUBLIC METHODS
     * ======================
     */
    
    /**
     * Get variant manager
     */
    public function variant()
    {
        return $this->variantManager;
    }
    
    /**
     * Set active theme
     */
    public function setTheme($themeName)
    {
        $oldTheme = $this->theme;
        $this->theme = $themeName;
        
        // Find new theme path
        $this->themePaths['base'] = $this->findThemePath($this->theme);
        
        if (!$this->themePaths['base']) {
            throw new Exception("Theme not found: " . $themeName);
        }
        
        // Reinitialize variant manager with new theme
        $this->variantManager = new VariantManager(
            $this->themePaths['base'],
            $this->config,
            $this->CI
        );
        
        // Reload theme info
        $this->loadThemeInfo();
        
        // Update web path
        $this->themePaths['web'] = str_replace(FCPATH, base_url(), $this->themePaths['base']);
        
        log_message('info', 'Theme switched from ' . $oldTheme . ' to ' . $themeName);
        return $this;
    }
    
    /**
     * Get theme info
     */
    public function getThemeInfo($key = null)
    {
        if (!$this->themeEnabled) {
            return null;
        }
        
        if ($key) {
            return $this->themeConfig[$key] ?? null;
        }
        return $this->themeConfig;
    }
    
    /**
     * Get active theme
     */
    public function getTheme()
    {
        return $this->theme;
    }
    
    /**
     * Get all available themes
     */
    public function getAvailableThemes()
    {
        $themes = [];
        
        foreach ($this->config['theme_locations'] as $location) {
            if (!is_dir($location)) continue;
            
            $folders = glob($location . '/*', GLOB_ONLYDIR);
            
            foreach ($folders as $folder) {
                $themeName = basename($folder);
                $themeFile = $folder . '/theme.php';
                
                if (file_exists($themeFile)) {
                    include($themeFile);
                    if (isset($theme) && is_array($theme)) {
                        $themes[$themeName] = array_merge([
                            'dir_name' => $themeName,
                            'path' => $folder,
                            'active' => ($themeName === $this->theme),
                        ], $theme);
                    }
                }
            }
        }
        
        return $themes;
    }
    
    /**
     * Clear theme cache
     */
    public function clearThemeCache()
    {
        if (!$this->themeEnabled) {
            return $this;
        }
        
        $cachePath = $this->config['theme_cache_path'] ?? null;
        
        if ($cachePath && is_dir($cachePath)) {
            $files = glob($cachePath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            log_message('info', 'Theme cache cleared: ' . $cachePath);
        }
        
        return $this;
    }
    
    /**
     * Get theme paths
     */
    public function getThemePaths()
    {
        return $this->themePaths;
    }
    
    /**
     * Check if theme system is enabled
     */
    public function isEnabled()
    {
        return $this->themeEnabled;
    }


// Di ThemeManager.php, tambahkan method ini:

/**
 * Get view path for includes
 */
public function getIncludePath($type, $name)
{
    if (!$this->themeEnabled) {
        return null;
    }
    
    $viewPath = $type . '/' . $name;
    
    // Check if view exists in theme
    if ($this->variantManager->viewExists($viewPath, $this->config['engines'] ?? [])) {
        return $viewPath;
    }
    
    return null;
}

/**
 * Render include from theme
 */
public function renderInclude($type, $name, $data = [])
{
    $viewPath = $this->getIncludePath($type, $name);
    
    if (!$viewPath) {
        throw new Exception("Include not found: {$type}/{$name}");
    }
    
    // Get template instance
    $CI =& get_instance();
    if (isset($CI->template)) {
        return $CI->template->view($viewPath, $data, true);
    }
    
    return null;
}


}
