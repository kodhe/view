<?php

declare(strict_types=1);

namespace Kodhe\Framework\View;

use Exception;

class VariantManager
{
    protected $CI;
    protected $config;
    protected $themeBasePath;
    
    // Variant properties
    protected $currentVariant = null;
    protected $availableVariants = [];
    protected $variantPaths = [];
    
    public function __construct($themeBasePath, $config, $ci)
    {
        $this->CI = $ci;
        $this->config = $config;
        $this->themeBasePath = $themeBasePath;
        
        $this->scanAvailableVariants();
        $this->detectVariant();
        $this->setupVariantPaths();
    }
    
    /**
     * Scan for available variants in theme directory
     */
    protected function scanAvailableVariants()
    {
        $this->availableVariants = [];
        
        if (!is_dir($this->themeBasePath)) {
            return;
        }
        
        $items = scandir($this->themeBasePath);
        
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            
            $itemPath = $this->themeBasePath . $item;
            
            // Hanya folder yang bukan reserved names
            if (is_dir($itemPath) && 
                !in_array($item, ['views', 'assets', 'shared', 'language', 'config'])) {
                
                // Cek apakah ini valid variant (punya views atau assets folder)
                if (is_dir($itemPath . '/views') || is_dir($itemPath . '/assets')) {
                    $this->availableVariants[] = $item;
                }
            }
        }
        
        log_message('debug', 'Available variants: ' . implode(', ', $this->availableVariants));
    }
    
    /**
     * Detect which variant to use
     */
    protected function detectVariant()
    {
        $this->currentVariant = null;
        
        // Detection order
        $detectionOrder = $this->config['variant_detection'] ?? ['session', 'url', 'auto'];
        
        foreach ($detectionOrder as $method) {
            switch ($method) {
                case 'session':
                    if ($variant = $this->detectFromSession()) {
                        $this->currentVariant = $variant;
                        return;
                    }
                    break;
                    
                case 'url':
                    if ($variant = $this->detectFromUrl()) {
                        $this->currentVariant = $variant;
                        return;
                    }
                    break;
                    
                case 'auto':
                    if ($variant = $this->autoDetect()) {
                        $this->currentVariant = $variant;
                        return;
                    }
                    break;
            }
        }
    }
    
    /**
     * Detect variant from session
     */
    protected function detectFromSession()
    {
        if (!isset($this->CI->session)) {
            return null;
        }
        
        $sessionVariant = $this->CI->session->userdata('theme_variant');
        if ($sessionVariant && $this->isValidVariant($sessionVariant)) {
            log_message('debug', 'Using variant from session: ' . $sessionVariant);
            return $sessionVariant;
        }
        
        return null;
    }
    
    /**
     * Detect variant from URL parameter
     */
    protected function detectFromUrl()
    {
        $urlVariant = $this->CI->input->get('theme_variant');
        if ($urlVariant && $this->isValidVariant($urlVariant)) {
            log_message('debug', 'Using variant from URL: ' . $urlVariant);
            return $urlVariant;
        }
        
        return null;
    }
    
    /**
     * Auto-detect variant based on device
     */
    protected function autoDetect()
    {
        // Check mobile detection
        if ($this->config['theme_mobile_detection'] ?? false) {
            // Check mobile variant
            if ($this->isValidVariant('mobile') && $this->isMobile()) {
                log_message('debug', 'Auto-detected mobile variant');
                return 'mobile';
            }
            
            // Check tablet variant
            if ($this->isValidVariant('tablet') && $this->isTablet()) {
                log_message('debug', 'Auto-detected tablet variant');
                return 'tablet';
            }
        }
        
        return null;
    }
    
    /**
     * Mobile detection
     */
    protected function isMobile()
    {
        $user_agent = $this->CI->input->user_agent();
        if (empty($user_agent)) {
            return false;
        }
        
        $mobile_agents = $this->config['theme_mobile_agents'] ?? [
            'Android', 'iPhone', 'iPad', 'iPod', 'BlackBerry',
            'Windows Phone', 'Mobile', 'Opera Mini', 'IEMobile'
        ];
        
        foreach ($mobile_agents as $agent) {
            if (stripos($user_agent, $agent) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Tablet detection
     */
    protected function isTablet()
    {
        $user_agent = $this->CI->input->user_agent();
        if (empty($user_agent)) {
            return false;
        }
        
        $tablet_agents = [
            'iPad', 'Android(?!.*Mobile)', 'Silk', 'Kindle', 'PlayBook', 'Tablet'
        ];
        
        foreach ($tablet_agents as $agent) {
            if (preg_match('/' . $agent . '/i', $user_agent)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if variant is valid
     */
    protected function isValidVariant($variant)
    {
        return in_array($variant, $this->availableVariants);
    }
    
    /**
     * Setup variant paths
     */
    protected function setupVariantPaths()
    {
        $this->variantPaths = [
            'base' => $this->themeBasePath,
            'views' => null,
            'assets' => null,
            'web' => str_replace(FCPATH, base_url(), $this->themeBasePath),
            'web_variant' => null // Initialize with null
        ];
        
        // Jika ada variant aktif
        if ($this->currentVariant) {
            $variantPath = $this->themeBasePath . $this->currentVariant . '/';
            
            // Views path
            if (is_dir($variantPath . 'views')) {
                $this->variantPaths['views'] = $variantPath . 'views/';
            } elseif (is_dir($variantPath . 'view')) {
                $this->variantPaths['views'] = $variantPath . 'view/';
            }
            
            // Assets path
            if (is_dir($variantPath . 'assets')) {
                $this->variantPaths['assets'] = $variantPath . 'assets/';
            }
            
            $this->variantPaths['web_variant'] = str_replace(FCPATH, base_url(), $variantPath);
        }
        
        // Fallback ke root paths jika variant tidak punya
        if (!$this->variantPaths['views']) {
            // Cek root views
            if (is_dir($this->themeBasePath . 'views')) {
                $this->variantPaths['views'] = $this->themeBasePath . 'views/';
            } elseif (is_dir($this->themeBasePath . 'view')) {
                $this->variantPaths['views'] = $this->themeBasePath . 'view/';
            }
        }
        
        if (!$this->variantPaths['assets'] && is_dir($this->themeBasePath . 'assets')) {
            $this->variantPaths['assets'] = $this->themeBasePath . 'assets/';
        }
        
        // Jika web_variant masih null, set ke web
        if (!$this->variantPaths['web_variant']) {
            $this->variantPaths['web_variant'] = $this->variantPaths['web'];
        }
        
        // Ensure directories exist
        $this->ensureDirectories();
    }
    
    /**
     * Ensure directories exist
     */
    protected function ensureDirectories()
    {
        $dirs = [
            $this->variantPaths['views'],
            $this->variantPaths['assets']
        ];
        
        foreach ($dirs as $dir) {
            if ($dir && !is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
    }
    
    /**
     * Get current views path
     */
    public function getCurrentViewsPath()
    {
        return $this->variantPaths['views'] ?: $this->themeBasePath . 'views/';
    }
    
    /**
     * Get view lookup paths dengan hierarchy
     */
    public function getViewLookupPaths()
    {
        $lookupPaths = [];
        
        // 1. Variant views (jika ada)
        if ($this->variantPaths['views']) {
            $lookupPaths[] = $this->variantPaths['views'];
        }
        
        // 2. Root views (selalu)
        $rootViewsPath = $this->themeBasePath . 'views/';
        if (is_dir($rootViewsPath) && !in_array($rootViewsPath, $lookupPaths)) {
            $lookupPaths[] = $rootViewsPath;
        }
        
        // 3. App views (fallback)
        $lookupPaths[] = resolve_path(APPPATH, 'views');
        
        return $lookupPaths;
    }
    
   /**
     * Get variant data for template
     */
    public function getVariantData()
    {
        $webVariant = $this->variantPaths['web_variant'] ?? $this->variantPaths['web'];
        $assetsPath = $this->variantPaths['web_variant'] ? 
            $this->variantPaths['web_variant'] . 'assets/' : 
            $this->variantPaths['web'] . 'assets/';
        
        return [
            'variant' => $this->currentVariant,
            'is_mobile' => ($this->currentVariant === 'mobile'),
            'is_tablet' => ($this->currentVariant === 'tablet'),
            'has_variant' => ($this->currentVariant !== null),
            '_variant' => [
                'name' => $this->currentVariant,
                'paths' => $this->variantPaths,
                'web_path' => $webVariant,
                'assets_path' => $assetsPath
            ]
        ];
    }
    
   /**
     * Resolve asset URL dengan fallback hierarchy
     */
    public function resolveAssetUrl($path)
    {
        // 1. Cek di variant assets (jika ada variant aktif dan punya assets)
        if ($this->currentVariant && $this->variantPaths['assets']) {
            $variantAssetPath = $this->variantPaths['assets'] . $path;
            $variantWebPath = $this->variantPaths['web_variant'] . 'assets/' . $path;
            
            if (file_exists($variantAssetPath)) {
                return $variantWebPath;
            }
        }
        
        // 2. Cek di root assets
        $rootAssetsPath = $this->themeBasePath . 'assets/';
        $rootWebPath = $this->variantPaths['web'] . 'assets/';
        
        if (file_exists($rootAssetsPath . $path)) {
            return $rootWebPath . $path;
        }
        
        // 3. Fallback ke default
        return base_url('assets/' . $path);
    }

    
    /**
     * Detect engine for a view dengan fallback hierarchy
     */
    public function detectViewEngine($view, $engines)
    {
        // Cari di semua lookup paths
        foreach ($this->getViewLookupPaths() as $lookupPath) {
            foreach ($engines as $engine => $engineConfig) {
                if (!isset($engineConfig['extension'])) {
                    continue;
                }
                
                $extension = $engineConfig['extension'];
                $filePath = rtrim($lookupPath, '/') . '/' . $view . $extension;
                
                if (file_exists($filePath)) {
                    log_message('debug', 'View found at: ' . $filePath . ' (engine: ' . $engine . ')');
                    return $engine;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Check if view exists dengan fallback hierarchy
     */
    public function viewExists($view, $engines)
    {
        foreach ($this->getViewLookupPaths() as $lookupPath) {
            foreach ($engines as $engineConfig) {
                if (!isset($engineConfig['extension'])) {
                    continue;
                }
                
                $extension = $engineConfig['extension'];
                $filePath = rtrim($lookupPath, '/') . '/' . $view . $extension;
                
                if (file_exists($filePath)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * ======================
     * PUBLIC METHODS
     * ======================
     */
    
    /**
     * Set active variant
     */
    public function setVariant($variant)
    {
        if ($variant === null) {
            $this->currentVariant = null;
        } elseif ($this->isValidVariant($variant)) {
            $this->currentVariant = $variant;
        } else {
            throw new Exception("Variant not found: " . $variant);
        }
        
        // Setup paths baru
        $this->setupVariantPaths();
        
        log_message('info', 'Theme variant set to: ' . ($variant ?: 'root'));
        return $this;
    }
    
    /**
     * Get current variant
     */
    public function getCurrentVariant()
    {
        return $this->currentVariant;
    }
    
    /**
     * Get available variants
     */
    public function getAvailableVariants()
    {
        return $this->availableVariants;
    }
    
    /**
     * Force mobile variant
     */
    public function forceMobile($force = true)
    {
        if ($force && $this->isValidVariant('mobile')) {
            return $this->setVariant('mobile');
        } elseif (!$force) {
            return $this->setVariant(null);
        }
        
        return $this;
    }
    
    /**
     * Check if has mobile variant
     */
    public function hasMobileVariant()
    {
        return $this->isValidVariant('mobile');
    }
    
    /**
     * Check if has tablet variant
     */
    public function hasTabletVariant()
    {
        return $this->isValidVariant('tablet');
    }
    
    /**
     * Clear variant (use root)
     */
    public function clearVariant()
    {
        return $this->setVariant(null);
    }
    
    /**
     * Save variant to session
     */
    public function saveToSession()
    {
        if (isset($this->CI->session)) {
            $this->CI->session->set_userdata('theme_variant', $this->currentVariant);
        }
        return $this;
    }
    
    /**
     * Get variant paths
     */
    public function getVariantPaths()
    {
        return $this->variantPaths;
    }
}
