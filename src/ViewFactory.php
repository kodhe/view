<?php

declare(strict_types=1);

namespace Kodhe\Framework\View;

use Exception;
use Kodhe\Framework\Http\Response;
use Kodhe\Framework\View\Engine\EngineFactory;

class ViewFactory
{
    protected $CI;
    protected $config;
    protected $engine;
    protected $currentEngine;
    protected $data = [];
    protected $layout = null;
    protected $sections = [];
    protected $currentSection = null;
    
    // Theme manager
    protected $themeManager;
    protected $assetsManager;
    
    // Response instance
    protected $response;
    
    public function __construct($config = [])
    {
        $this->CI =& get_instance();
        
        // Initialize response object
        $this->initResponse();
        
        // Load config
        $this->loadConfig($config);
        
        // Initialize theme manager jika enabled
        $this->initializeThemeIfEnabled();
        
        // Setup engines
        $this->setupEngines();
        
        // Load default engine
        $this->loadEngine($this->config['default']);
    }
    
    /**
     * Initialize response object
     */
    protected function initResponse()
    {
        // Periksa jika response sudah ada di CI instance
        if (isset($this->CI->response) && $this->CI->response instanceof Response) {
            $this->response = $this->CI->response;
        } else {
            // Buat response baru
            $this->response = new Response();
            
            // Simpan di CI instance untuk konsistensi
            if (!isset($this->CI->response)) {
                $this->CI->response = $this->response;
            }
        }
    }
    
    /**
     * Initialize theme manager if theme_enabled is true
     */
    protected function initializeThemeIfEnabled()
    {
        if ($this->config['theme_enabled']) {
            $this->themeManager = new ThemeManager($this->config, $this->CI);
            $this->initializeAssetsManager();
        }
    }
    
    /**
     * Initialize assets manager
     */
    protected function initializeAssetsManager()
    {
        if ($this->themeManager && class_exists('Kodhe\Framework\View\AssetManager')) {
            $this->assetsManager = new AssetManager($this->config['assets'], $this->themeManager);
            $this->CI->assets = $this->assetsManager;
        }
    }
    
    protected function loadConfig($userConfig = [])
    {
        // Default config
        $defaultConfig = [
            'default' => 'blade',
            'views_path' => VIEWPATH,
            'cache_path' => resolve_path(STORAGEPATH, 'cache') . 'blade',
            'engines' => [
                'blade' => [
                    'class' => 'Kodhe\Framework\View\Engine\BladeEngine',
                    'extension' => '.blade.php'
                ],
                'php' => [
                    'class' => 'Kodhe\Framework\View\Engine\PhpEngine',
                    'extension' => '.php'
                ],
                'lex' => [
                    'class' => 'Kodhe\Framework\View\Engine\LexEngine',
                    'extension' => '.lex.php'
                ]
            ],
            'paths' => [VIEWPATH],
            'layout' => null,
            'enable_profiler' => ENVIRONMENT === 'development',
            
            // Theme settings
            'theme_enabled' => false,
            
            // Config untuk assets
            'assets' => [
                'assets_dir' => 'assets',
                'shared_assets_dir' => 'assets/shared',
                'cache_dir' => 'cache/assets',
                'combine' => ENVIRONMENT === 'production',
                'minify' => ENVIRONMENT === 'production',
                'asset_cache' => ENVIRONMENT === 'production',
                'version' => false,
                'version_param' => 'v',
                'cdn' => [],
                'auto_load' => [],
                'locations' => [
                    resolve_path(APPPATH, 'themes'),
                    resolve_path(FCPATH, 'themes')
                ]
            ]
        ];
        
        // Load from config file
        $fileConfig = $this->loadConfigFile();
        
        // Merge config
        $this->config = array_merge($defaultConfig, $fileConfig, $userConfig);
    }
    
    protected function loadConfigFile()
    {
        $configDir = resolve_path(APPPATH, 'config');
        
        // Prioritize modern formats
        $formats = [
            'template.yaml' => 'yaml',
            'template.yml' => 'yaml',
            'template.json' => 'json',
            'template.php' => 'php',
        ];
        
        foreach ($formats as $file => $type) {
            $configFile = $configDir . $file;
            
            if (!file_exists($configFile)) {
                continue;
            }
            
            switch ($type) {
                case 'json':
                    $content = file_get_contents($configFile);
                    $config = json_decode($content, true);
                    return json_last_error() === JSON_ERROR_NONE ? $config : [];
                    
                case 'yaml':
                    // Requires symfony/yaml component
                    if (class_exists('\Symfony\Component\Yaml\Yaml')) {
                        return \Symfony\Component\Yaml\Yaml::parseFile($configFile);
                    }
                    break;
                    
                case 'php':
                    return $this->loadPhpConfig($configFile);
            }
        }
        
        return [];
    }
    
    protected function loadPhpConfig($file)
    {
        // Isolate in closure to prevent variable leakage
        $loader = function($file) {
            return require $file;
        };
        
        $result = $loader($file);
        
        if (is_array($result)) {
            return $result;
        }
        
        // Legacy support
        $legacyConfig = [];
        (function() use ($file, &$legacyConfig) {
            include $file;
            $legacyConfig = $GLOBALS['config'] ?? [];
        })();
        
        return $legacyConfig;
    }
    
    /**
     * Setup engine registration
     */
    protected function setupEngines()
    {
        EngineFactory::register('blade', 'Kodhe\Framework\View\Engine\BladeEngine', [
            'views_path' => $this->config['views_path'],
            'cache_path' => $this->config['cache_path']
        ]);
        
        EngineFactory::register('php', 'Kodhe\Framework\View\Engine\PhpEngine', [
            'views_path' => $this->config['views_path']
        ]);
        
        EngineFactory::register('lex', 'Kodhe\Framework\View\Engine\LexEngine', [
            'views_path' => $this->config['views_path']
        ]);
    }
    
    /**
     * Load template engine
     */
    public function loadEngine($engine, $config = [])
    {
        if (!isset($this->config['engines'][$engine])) {
            throw new Exception("Template engine '{$engine}' not configured");
        }
        
        // Merge engine config
        $engineConfig = array_merge($this->config['engines'][$engine], $config);
        
        // Update paths based on theme if enabled
        if ($this->config['theme_enabled'] && $this->themeManager) {
            $themeConfig = $this->themeManager->getConfig();
            
            if (isset($themeConfig['views_path'])) {
                $engineConfig['views_path'] = $themeConfig['views_path'];
            }
            
            // For Blade, update cache path
            if ($engine === 'blade' && isset($themeConfig['cache_path'])) {
                $engineConfig['cache_path'] = $themeConfig['cache_path'];
            }
        }
        
        // Create engine instance via factory
        $this->engine = EngineFactory::make($engine, $engineConfig);
        $this->currentEngine = $engine;
        
        return $this;
    }
    
    /**
     * Set layout
     */
    public function layout($layout)
    {
        $this->layout = $layout;
        return $this;
    }
    
    /**
     * Set data
     */
    public function set($key, $value = null)
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }
        return $this;
    }
    
    /**
     * Render template
     */
    public function render($view, $data = [], $return = false, $engine = null)
    {
        // Pastikan response sudah diinisialisasi
        if ($this->response === null) {
            $this->initResponse();
        }
        
        // Benchmark start
        app('benchmark')->mark('template_render_start');
        
        // Use specified engine or current
        if ($engine && $engine !== $this->currentEngine) {
            $this->loadEngine($engine);
        }
        
        // Remove extension
        $view = $this->removeExtension($view);
        
        // Merge data
        $allData = array_merge($this->data, $data);
        
        // Add theme data if enabled
        if ($this->config['theme_enabled'] && $this->themeManager) {
            $allData = array_merge($allData, $this->themeManager->getTemplateData());
        }
        
        // Add CI instance if not already present
        if (!isset($allData['CI'])) {
            $allData['CI'] = $this->CI;
        }

        
        // Render
        try {

            $output = $this->engine->render($view, $allData);
        } catch (Exception $e) {
            log_message('error', 'Template render failed: ' . $e->getMessage());
            
            // Fallback to PHP engine
            if ($this->currentEngine !== 'php') {
                log_message('debug', 'Falling back to PHP engine');
                return $this->render($view, $data, $return, 'php');
            }
            
            throw $e;
        }
        
        // Benchmark end
        app('benchmark')->mark('template_render_end');
        
        // Process profiler
        $output = $this->processProfiler($output);
        
        if ($return) {
            return $output;
        }
        
        // Pastikan response ada sebelum memanggil send()
        if ($this->response === null) {
            throw new Exception("Response object is not initialized");
        }
        
        $this->response->setBody($output);
        // Hanya send() jika tidak dalam mode middleware/process
        if ($this->currentEngine !== 'php') {
            $this->response->send();
        } else {
            $this->response->send(false);
        }
        
        return null;
    }
    
    /**
     * Render view dengan auto engine detection
     */
    public function view($view, $data = [], $return = false, $engine = null)
    {
        $engine = $engine ?? $this->detectEngine($view);
        return $this->render($view, $data, $return, $engine);
    }
    
    /**
     * Detect engine based on view file
     */
    protected function detectEngine($view)
    {
        // Check theme views first if enabled
        if ($this->config['theme_enabled'] && $this->themeManager) {
            $themeEngine = $this->themeManager->detectViewEngine($view);
            if ($themeEngine) {
                return $themeEngine;
            }
        }
        
        // Check in default paths
        foreach ($this->config['engines'] as $engine => $engineConfig) {
            $extension = $engineConfig['extension'];
            $filePath = $this->config['views_path'] . '/' . $view;
            
            if (file_exists($filePath) || 
                file_exists($filePath . $extension) ||
                $this->findViewInPaths($view . $extension)) {
                return $engine;
            }
        }
        
        return $this->config['default'];
    }
    
    /**
     * Find view in configured paths
     */
    protected function findViewInPaths($viewFile)
    {
        foreach ($this->config['paths'] as $path) {
            $filePath = rtrim($path, '/') . '/' . $viewFile;
            if (file_exists($filePath)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Remove extension from view name
     */
    protected function removeExtension($view)
    {
        foreach ($this->config['engines'] as $engineConfig) {
            $extension = $engineConfig['extension'];
            $view = preg_replace('/' . preg_quote($extension, '/') . '$/i', '', $view);
        }
        return $this->normalizeViewName($view);
    }
    
    /**
     * Normalize view name based on engine
     */
    protected function normalizeViewName($view, $engine = null)
    {
        if ($engine === null) {
            $engine = $this->currentEngine;
        }
        
        // Untuk engine PHP, ubah titik menjadi slash
        if ($engine === 'php') {
            return str_replace('.', '/', $view);
        }
        
        return $view;
    }
    
    /**
     * Process profiler placeholders
     */
    protected function processProfiler($output)
    {
        if (!$this->config['enable_profiler']) {
            //return $output;
        }
        
        // Replace {elapsed_time}
        if (strpos($output, '{elapsed_time}') !== false) {
            $elapsed = app('benchmark')->elapsed_time('total_execution_time_start', 'total_execution_time_end');
            $output = str_replace('{elapsed_time}', $elapsed, $output);
        }
        
        // Replace {memory_usage}
        if (strpos($output, '{memory_usage}') !== false) {
            $memory = function_exists('memory_get_usage') 
                ? round(memory_get_usage()/1024/1024, 2) . 'MB' 
                : '0';
            $output = str_replace('{memory_usage}', $memory, $output);
        }
        
        return $output;
    }
    
    /**
     * Check if view exists
     */
    public function exists($view)
    {
        // Check theme first if enabled
        if ($this->config['theme_enabled'] && $this->themeManager) {
            if ($this->themeManager->viewExists($view)) {
                return true;
            }
        }
        
        // Check in default paths
        foreach ($this->config['engines'] as $engineConfig) {
            $extension = $engineConfig['extension'];
            $filePath = $this->config['views_path'] . $view . $extension;
            
            if (file_exists($filePath)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * ======================
     * THEME CONTROL METHODS
     * ======================
     */
    
    /**
     * Enable or disable theme system
     */
    public function enableTheme($enabled = false)
    {
        $oldValue = $this->config['theme_enabled'];
        $this->config['theme_enabled'] = $enabled;
        
        if ($enabled && !$oldValue) {
            // Enable theme system
            if (!$this->themeManager) {
                $this->themeManager = new ThemeManager($this->config, $this->CI);
                $this->initializeAssetsManager();
            }
            
            // Reload engine dengan path theme
            if ($this->currentEngine) {
                $this->loadEngine($this->currentEngine);
            }
            
        } elseif (!$enabled && $oldValue) {
            // Disable theme system
            $this->themeManager = null;
            $this->assetsManager = null;
            
            // Reload engine dengan path default
            if ($this->currentEngine) {
                $this->loadEngine($this->currentEngine);
            }
        }
        
        return $this;
    }
    
    /**
     * Check if theme system is enabled
     */
    public function isThemeEnabled()
    {
        return $this->config['theme_enabled'] && $this->themeManager !== null;
    }
    
    /**
     * ======================
     * THEME DELEGATION METHODS
     * ======================
     */
    
    /**
     * Get theme manager
     */
    public function theme()
    {
        if (!$this->themeManager) {
            throw new Exception("Theme system is not enabled");
        }
        return $this->themeManager;
    }
    
    /**
     * Set active theme
     */
    public function setTheme($themeName)
    {
        if (!$this->themeManager) {
            throw new Exception("Theme system is not enabled");
        }
        
        $this->themeManager->setTheme($themeName);
        
        // Reload engine with new theme paths
        $this->loadEngine($this->currentEngine);
        
        // Update assets manager with new theme
        if (class_exists('Kodhe\Framework\View\AssetManager')) {
            $this->assetsManager = new AssetManager($this->config['assets'], $this->themeManager);
            $this->CI->assets = $this->assetsManager;
        }
        
        return $this;
    }
    
    /**
     * Get active theme name
     */
    public function getTheme()
    {
        if (!$this->themeManager) {
            return null;
        }
        return $this->themeManager->getTheme();
    }
    
    /**
     * Get theme info
     */
    public function getThemeInfo($key = null)
    {
        if (!$this->themeManager) {
            return null;
        }
        return $this->themeManager->getThemeInfo($key);
    }
    
    /**
     * Get all available themes
     */
    public function getAvailableThemes()
    {
        if (!$this->themeManager) {
            return [];
        }
        return $this->themeManager->getAvailableThemes();
    }
    
    /**
     * Get theme asset URL
     */
    public function themeAsset($path)
    {
        if (!$this->themeManager) {
            return base_url('assets/' . ltrim($path, '/'));
        }
        return $this->themeManager->themeAsset($path);
    }
    
    /**
     * Get theme paths
     */
    public function getThemePaths()
    {
        if (!$this->themeManager) {
            return [];
        }
        return $this->themeManager->getThemePaths();
    }
    
    /**
     * Clear theme cache
     */
    public function clearThemeCache()
    {
        if ($this->themeManager) {
            $this->themeManager->clearThemeCache();
        }
        return $this;
    }
    
    /**
     * Get variant manager (alias untuk kemudahan)
     */
    public function variant()
    {
        if (!$this->themeManager) {
            throw new Exception("Theme system is not enabled");
        }
        
        return $this->themeManager->variant();
    }
    
    /**
     * Set theme variant
     */
    public function setVariant($variant)
    {
        if (!$this->themeManager) {
            throw new Exception("Theme system is not enabled");
        }
        return $this->themeManager->variant()->setVariant($variant);
    }
    
    /**
     * Get current variant
     */
    public function getVariant()
    {
        if (!$this->themeManager) {
            return null;
        }
        return $this->themeManager->variant()->getCurrentVariant();
    }
    
    /**
     * Get available variants
     */
    public function getAvailableVariants()
    {
        if (!$this->themeManager) {
            return [];
        }
        return $this->themeManager->variant()->getAvailableVariants();
    }
    
    /**
     * Check if mobile variant is active
     */
    public function isMobileVariant()
    {
        if (!$this->themeManager) {
            return false;
        }
        return $this->themeManager->variant()->getCurrentVariant() === 'mobile';
    }
    
    /**
     * Check if tablet variant is active
     */
    public function isTabletVariant()
    {
        if (!$this->themeManager) {
            return false;
        }
        return $this->themeManager->variant()->getCurrentVariant() === 'tablet';
    }
    
    /**
     * Force mobile variant
     */
    public function forceMobile($force = true)
    {
        if (!$this->themeManager) {
            throw new Exception("Theme system is not enabled");
        }
        return $this->themeManager->variant()->forceMobile($force);
    }
    
    /**
     * Clear variant (use root)
     */
    public function clearVariant()
    {
        if (!$this->themeManager) {
            throw new Exception("Theme system is not enabled");
        }
        return $this->themeManager->variant()->clearVariant();
    }
    
    /**
     * Save variant to session
     */
    public function saveVariantToSession()
    {
        if ($this->themeManager) {
            $this->themeManager->variant()->saveToSession();
        }
        return $this;
    }
    
    /**
     * Check if view exists in theme
     */
    public function themeViewExists($view)
    {
        if (!$this->themeManager) {
            return false;
        }
        return $this->themeManager->viewExists($view);
    }
    
    /**
     * Detect engine for a view in theme
     */
    public function detectThemeEngine($view)
    {
        if (!$this->themeManager) {
            return null;
        }
        return $this->themeManager->detectViewEngine($view);
    }
    
    /**
     * ======================
     * ENGINE METHODS
     * ======================
     */
    
    /**
     * Set current engine directly without reloading
     */
    public function setEngine($engine)
    {
        $this->loadEngine($engine);
        return $this;
    }
    
    /**
     * Get current engine name
     */
    public function getCurrentEngine()
    {
        return $this->currentEngine;
    }
    
    /**
     * Get response instance
     */
    public function response()
    {
        if ($this->response === null) {
            $this->initResponse();
        }
        return $this->response;
    }
    
    /**
     * Set response instance
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;
        
        // Update CI instance untuk konsistensi
        if (isset($this->CI)) {
            $this->CI->response = $response;
        }
        
        return $this;
    }
    
    /**
     * Get assets manager
     */
    public function assets()
    {
        return $this->assetsManager;
    }
    
    /**
     * Get configuration
     */
    public function getConfig($key = null)
    {
        if ($key !== null) {
            return $this->config[$key] ?? null;
        }
        return $this->config;
    }
    
    /**
     * Set configuration
     */
    public function setConfig($key, $value)
    {
        $this->config[$key] = $value;
        return $this;
    }
    
    /**
     * ======================
     * VIEW HELPERS
     * ======================
     */
    
    /**
     * Render theme partial
     */
    public function partial($view, $data = [])
    {
        $partialPath = 'partials/' . $view;
        return $this->view($partialPath, $data, true);
    }
    
    /**
     * Render widget
     */
    public function widget($widget, $params = [])
    {
        $widgetPath = 'widgets/' . $widget;
        return $this->view($widgetPath, $params, true);
    }
    
    /**
     * Render section
     */
    public function section($name)
    {
        $this->currentSection = $name;
        ob_start();
        return $this;
    }
    
    /**
     * End section
     */
    public function endsection()
    {
        if ($this->currentSection) {
            $this->sections[$this->currentSection] = ob_get_clean();
            $this->currentSection = null;
        }
        return $this;
    }
    
    /**
     * Get section content
     */
    public function getSection($name)
    {
        return $this->sections[$name] ?? '';
    }
    
    /**
     * Extend layout (for blade-like syntax)
     */
    public function extends($layout)
    {
        $this->layout = $layout;
        return $this;
    }
    
    /**
     * Get current layout
     */
    public function getLayout()
    {
        return $this->layout;
    }
    
    /**
     * Clear all data
     */
    public function clearData()
    {
        $this->data = [];
        $this->sections = [];
        $this->layout = null;
        return $this;
    }
    
    /**
     * Magic method untuk kemudahan
     */
    public function __call($method, $args)
    {
        // Try theme manager methods
        if ($this->themeManager && method_exists($this->themeManager, $method)) {
            return call_user_func_array([$this->themeManager, $method], $args);
        }
        
        // Try engine methods
        if ($this->engine && method_exists($this->engine, $method)) {
            return call_user_func_array([$this->engine, $method], $args);
        }
        
        // Try assets manager methods
        if ($this->assetsManager && method_exists($this->assetsManager, $method)) {
            return call_user_func_array([$this->assetsManager, $method], $args);
        }
        
        throw new Exception("Method {$method} not found in Template");
    }
    
    /**
     * Magic getter untuk kemudahan akses
     */
    public function __get($name)
    {
        switch ($name) {
            case 'theme':
                return $this->themeManager;
                
            case 'assets':
                return $this->assetsManager;
                
            case 'response':
                return $this->response();
                
            case 'engine':
                return $this->currentEngine;
                
            default:
                throw new Exception("Property {$name} not found in Template");
        }
    }
    
    /**
     * Magic setter
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'theme':
                $this->setTheme($value);
                break;
                
            case 'layout':
                $this->layout($value);
                break;
                
            default:
                throw new Exception("Cannot set property {$name} in Template");
        }
    }
}
