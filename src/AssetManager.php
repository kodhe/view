<?php

declare(strict_types=1);

namespace Kodhe\Framework\View;

class AssetManager
{
    protected $CI;
    protected $config;
    protected $themeManager;
    
    protected $assets = [
        'css' => [],
        'js' => [],
        'inline_css' => [],
        'inline_js' => [],
        'meta' => []
    ];
    
    protected $added_files = [
        'css' => [],
        'js' => []
    ];
    
    protected $currentGroup = 'default';
    protected $defaultGroup = 'theme';
    
    public function __construct($config = [], $themeManager = null)
    {
        $this->CI =& get_instance();
        $this->config = $config;
        $this->themeManager = $themeManager;
        
        // Load configuration
        $this->loadConfig();
        
        // Auto-load assets jika dikonfigurasi
        $this->autoLoadAssets();
        
        log_message('info', 'Assets Manager Initialized');
    }
    
    /**
     * Load configuration
     */
    protected function loadConfig()
    {
        $defaultConfig = [
            'assets_dir' => 'assets',
            'shared_assets_dir' => 'assets/shared',
            'cache_dir' => 'cache/assets',
            'combine' => ENVIRONMENT === 'production',
            'minify' => ENVIRONMENT === 'production',
            'asset_cache' => ENVIRONMENT === 'production',
            'version' => false,
            'version_param' => 'v',
            'cdn' => [],
            'auto_load' => []
        ];
        
        $this->config = array_merge($defaultConfig, $this->config);
    }
    
    /**
     * Auto-load assets dari config
     */
    protected function autoLoadAssets()
    {
        if (isset($this->config['auto_load']) && is_array($this->config['auto_load'])) {
            foreach ($this->config['auto_load'] as $type => $files) {
                if ($type === 'css' && is_array($files)) {
                    foreach ($files as $file => $config) {
                        if (is_array($config)) {
                            $this->add_css($file, $config['group'] ?? 'theme', $config['attributes'] ?? [], $config['priority'] ?? 10);
                        } else {
                            $this->add_css($config, 'theme', [], 10);
                        }
                    }
                } elseif ($type === 'js' && is_array($files)) {
                    foreach ($files as $file => $config) {
                        if (is_array($config)) {
                            $this->add_js(
                                $file, 
                                $config['group'] ?? 'theme', 
                                $config['attributes'] ?? [], 
                                $config['position'] ?? 'footer', 
                                $config['priority'] ?? 10
                            );
                        } else {
                            $this->add_js($config, 'theme', [], 'footer', 10);
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Add CSS file dengan prioritas dan group
     */
    public function add_css($file, $group = null, $attributes = [], $priority = 10)
    {
        $group = $group ?: $this->defaultGroup;
        
        // Cek apakah file sudah ditambahkan
        if ($this->css_exists($file, $group)) {
            log_message('debug', 'CSS already added: ' . $file . ' in group ' . $group);
            return $this;
        }
        
        $this->assets['css'][] = [
            'file' => $file,
            'group' => $group,
            'attributes' => $attributes,
            'priority' => $priority,
            'external' => $this->is_external($file)
        ];
        
        // Track file yang sudah ditambahkan
        $this->added_files['css'][$file . '_' . $group] = true;
        
        // Urutkan berdasarkan prioritas
        usort($this->assets['css'], function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
        
        return $this;
    }
    
    /**
     * Add JS file dengan position dan group
     */
    public function add_js($file, $group = null, $attributes = [], $position = 'footer', $priority = 10)
    {
        $group = $group ?: $this->defaultGroup;
        
        // Cek apakah file sudah ditambahkan
        if ($this->js_exists($file, $group, $position)) {
            log_message('debug', 'JS already added: ' . $file . ' in group ' . $group . ' position ' . $position);
            return $this;
        }
        
        $this->assets['js'][] = [
            'file' => $file,
            'group' => $group,
            'attributes' => $attributes,
            'position' => $position,
            'priority' => $priority,
            'external' => $this->is_external($file)
        ];
        
        // Track file yang sudah ditambahkan
        $this->added_files['js'][$file . '_' . $group . '_' . $position] = true;
        
        // Urutkan berdasarkan prioritas
        usort($this->assets['js'], function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
        
        return $this;
    }
    
    /**
     * Alias untuk css() dengan backward compatibility
     */
    public function css($file, $group = null, $attributes = [], $priority = 10)
    {
        return $this->add_css($file, $group, $attributes, $priority);
    }
    
    /**
     * Alias untuk js() dengan backward compatibility
     */
    public function js($file, $group = null, $attributes = [], $position = 'footer', $priority = 10)
    {
        return $this->add_js($file, $group, $attributes, $position, $priority);
    }
    
    /**
     * Add inline CSS
     */
    public function add_inline_css($css, $priority = 10)
    {
        $this->assets['inline_css'][] = [
            'css' => $css,
            'priority' => $priority
        ];
        
        usort($this->assets['inline_css'], function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
        
        return $this;
    }
    
    /**
     * Add inline JS
     */
    public function add_inline_js($js, $position = 'footer', $priority = 10)
    {
        $this->assets['inline_js'][] = [
            'js' => $js,
            'position' => $position,
            'priority' => $priority
        ];
        
        usort($this->assets['inline_js'], function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
        
        return $this;
    }
    
    /**
     * Alias untuk style() dan script()
     */
    public function style($css, $priority = 10)
    {
        return $this->add_inline_css($css, $priority);
    }
    
    public function script($js, $position = 'footer', $priority = 10)
    {
        return $this->add_inline_js($js, $position, $priority);
    }
    
    /**
     * Add meta tag
     */
    public function meta($name, $content, $type = 'name')
    {
        $this->assets['meta'][] = [
            'name' => $name,
            'content' => $content,
            'type' => $type
        ];
        
        return $this;
    }
    
    /**
     * Set active group
     */
    public function group($group)
    {
        $this->currentGroup = $group;
        return $this;
    }
    
    /**
     * Check if file is external URL
     */
    protected function is_external($file)
    {
        return filter_var($file, FILTER_VALIDATE_URL) !== FALSE;
    }
    
    /**
     * Check if CSS file already added
     */
    protected function css_exists($file, $group)
    {
        $key = $file . '_' . $group;
        return isset($this->added_files['css'][$key]);
    }
    
    /**
     * Check if JS file already added
     */
    protected function js_exists($file, $group, $position)
    {
        $key = $file . '_' . $group . '_' . $position;
        return isset($this->added_files['js'][$key]);
    }
    
    /**
     * Resolve asset URL dengan theme support
     */
    public function url($path)
    {
        // Jika path adalah URL eksternal, langsung return
        if ($this->is_external($path)) {
            return $path;
        }
        
        // Check CDN first
        if ($cdnUrl = $this->getCdnUrl($path)) {
            return $cdnUrl;
        }
        
        // Jika ada theme manager, gunakan theme asset
        if ($this->themeManager && $this->themeManager->isEnabled()) {
            return $this->themeManager->themeAsset($path);
        }
        
        // Fallback ke default assets
        return base_url($this->config['assets_dir'] . '/' . ltrim($path, '/'));
    }
    
    /**
     * Get CDN URL jika tersedia
     */
    protected function getCdnUrl($path)
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        
        if ($ext === 'css' && isset($this->config['cdn']['css'][$path])) {
            return $this->config['cdn']['css'][$path];
        }
        
        if ($ext === 'js' && isset($this->config['cdn']['js'][$path])) {
            return $this->config['cdn']['js'][$path];
        }
        
        // Check by filename
        $filename = basename($path);
        if (isset($this->config['cdn'][$filename])) {
            return $this->config['cdn'][$filename];
        }
        
        return false;
    }
    
    /**
     * Helper untuk mendapatkan URL asset theme
     */
    public function theme_asset($path)
    {
        return $this->url($path);
    }
    
    /**
     * Helper untuk CSS theme
     */
    public function theme_css($file)
    {
        return $this->url('css/' . ltrim($file, '/'));
    }
    
    /**
     * Helper untuk JS theme
     */
    public function theme_js($file)
    {
        return $this->url('js/' . ltrim($file, '/'));
    }
    
    /**
     * Helper untuk Image theme
     */
    public function theme_img($file)
    {
        return $this->url('img/' . ltrim($file, '/'));
    }
    
    /**
     * Render CSS dengan cache busting
     */
    public function render_css($group = null)
    {
        $group = $group ?: $this->defaultGroup;
        $html = '';
        
        foreach ($this->assets['css'] as $css) {
            if ($css['group'] === $group || $group === 'all') {
                $file_url = $css['external'] ? $css['file'] : $this->url($css['file']);
                
                // Cache busting
                if (!$css['external'] && $this->config['asset_cache']) {
                    $file_url = $this->cache_bust($file_url);
                }
                
                $attributes = $this->build_attributes($css['attributes']);
                $html .= '<link rel="stylesheet" href="' . $file_url . '" ' . $attributes . '>' . PHP_EOL;
            }
        }
        
        // Inline CSS
        foreach ($this->assets['inline_css'] as $inline) {
            $html .= '<style>' . $inline['css'] . '</style>' . PHP_EOL;
        }
        
        return $html;
    }
    
    /**
     * Render JS dengan position support
     */
    public function render_js($position = 'footer', $group = null)
    {
        $group = $group ?: $this->defaultGroup;
        $html = '';
        
        foreach ($this->assets['js'] as $js) {
            if ($js['position'] === $position && ($js['group'] === $group || $group === 'all')) {
                $file_url = $js['external'] ? $js['file'] : $this->url($js['file']);
                
                // Cache busting
                if (!$js['external'] && $this->config['asset_cache']) {
                    $file_url = $this->cache_bust($file_url);
                }
                
                $attributes = $this->build_attributes($js['attributes']);
                $html .= '<script src="' . $file_url . '" ' . $attributes . '></script>' . PHP_EOL;
            }
        }
        
        // Inline JS
        foreach ($this->assets['inline_js'] as $inline) {
            if ($inline['position'] === $position) {
                $html .= '<script>' . $inline['js'] . '</script>' . PHP_EOL;
            }
        }
        
        return $html;
    }
    
    /**
     * Render meta tags
     */
    public function render_meta()
    {
        $html = '';
        
        foreach ($this->assets['meta'] as $meta) {
            $html .= '<meta ' . $meta['type'] . '="' . $meta['name'] . '" content="' . htmlspecialchars($meta['content']) . '">' . PHP_EOL;
        }
        
        return $html;
    }
    
    /**
     * Alias untuk renderCss (camelCase)
     */
    public function renderCss($group = null)
    {
        return $this->render_css($group);
    }
    
    /**
     * Alias untuk renderJs (camelCase)
     */
    public function renderJs($position = 'footer', $group = null)
    {
        return $this->render_js($position, $group);
    }
    
    /**
     * Build HTML attributes string
     */
    protected function build_attributes($attributes)
    {
        if (empty($attributes)) {
            return '';
        }
        
        $html = '';
        foreach ($attributes as $key => $value) {
            if (is_bool($value)) {
                if ($value) $html .= $key . ' ';
            } elseif (is_numeric($key)) {
                $html .= $value . ' ';
            } else {
                $html .= $key . '="' . htmlspecialchars($value) . '" ';
            }
        }
        return trim($html);
    }
    
    /**
     * Cache busting dengan timestamp
     */
    protected function cache_bust($url)
    {
        // Remove query string jika ada
        $url_parts = parse_url($url);
        $file_path = str_replace(base_url(), FCPATH, $url_parts['path']);
        
        if (file_exists($file_path)) {
            $modified = filemtime($file_path);
            $separator = (strpos($url, '?') === false) ? '?' : '&';
            return $url . $separator . 'v=' . $modified;
        }
        
        return $url;
    }
    
    /**
     * Check if asset exists
     */
    public function exists($path)
    {
        if ($this->themeManager) {
            $url = $this->themeManager->themeAsset($path);
            $file_path = str_replace(base_url(), FCPATH, parse_url($url, PHP_URL_PATH));
            return file_exists($file_path);
        }
        
        return false;
    }
    
    /**
     * Clear assets
     */
    public function clear_assets($type = null)
    {
        if ($type === 'css' || $type === 'CSS') {
            $this->assets['css'] = [];
            $this->added_files['css'] = [];
        } elseif ($type === 'js' || $type === 'JS') {
            $this->assets['js'] = [];
            $this->added_files['js'] = [];
        } elseif ($type === 'inline') {
            $this->assets['inline_css'] = [];
            $this->assets['inline_js'] = [];
        } elseif ($type === 'meta') {
            $this->assets['meta'] = [];
        } else {
            $this->assets['css'] = [];
            $this->assets['js'] = [];
            $this->assets['inline_css'] = [];
            $this->assets['inline_js'] = [];
            $this->assets['meta'] = [];
            $this->added_files['css'] = [];
            $this->added_files['js'] = [];
        }
        
        return $this;
    }
    
    /**
     * Reset all assets (alias untuk clear_assets)
     */
    public function reset($type = null)
    {
        return $this->clear_assets($type);
    }
    
    /**
     * Get all assets
     */
    public function get_assets()
    {
        return $this->assets;
    }
    
    /**
     * Get configuration
     */
    public function get_config()
    {
        return $this->config;
    }
    
    /**
     * Magic method untuk backward compatibility
     */
    public function __call($method, $args)
    {
        // Convert camelCase to snake_case
        if (strpos($method, 'render') === 0) {
            $snake_method = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $method));
            if (method_exists($this, $snake_method)) {
                return call_user_func_array([$this, $snake_method], $args);
            }
        }
        
        throw new \Exception("Method {$method} not found in Assets");
    }
}
