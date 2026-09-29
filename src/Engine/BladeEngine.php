<?php

declare(strict_types=1);

namespace Kodhe\Framework\View\Engine;

use eftec\bladeone\BladeOne;

class BladeEngine implements EngineInterface
{
    protected $blade;
    protected $viewsPath;
    protected $cachePath;
    
    /**
     * Constructor dengan parameter default
     */
    public function __construct($config = [])
    {
        // Support untuk array config atau individual parameters
        if (is_array($config)) {
            // VIEWPATH sebagai default, ditambah dengan config jika ada
            $additionalPaths = $config['views_path'] ?? [];
            
            // Gabungkan VIEWPATH dengan paths tambahan
            $this->viewsPath = $this->combineViewPaths($additionalPaths, VIEWPATH);
            
            $this->cachePath = $config['cache_path'] ?? STORAGEPATH . 'cache/blade';
        } else {
            // Backward compatibility
            $additionalPaths = func_get_arg(0) ?? [];
            
            // Gabungkan VIEWPATH dengan paths tambahan
            $this->viewsPath = $this->combineViewPaths($additionalPaths, VIEWPATH);
            
            $this->cachePath = func_get_arg(1) ?? STORAGEPATH . 'cache/blade';
        }
        
        $this->init();
    }
    
    /**
     * Gabungkan VIEWPATH default dengan paths tambahan
     */
    protected function combineViewPaths($defaultPath, $additionalPaths)
    {
        $paths = [];
        
        // Selalu tambahkan VIEWPATH sebagai pertama
        $paths[] = rtrim($defaultPath, '/\\');
        
        // Tambahkan paths tambahan jika ada
        if (!empty($additionalPaths)) {
            if (is_array($additionalPaths)) {
                foreach ($additionalPaths as $path) {
                    $cleanPath = rtrim($path, '/\\');
                    if (!in_array($cleanPath, $paths)) {
                        $paths[] = $cleanPath;
                    }
                }
            } else {
                // Jika single path (string)
                $cleanPath = rtrim($additionalPaths, '/\\');
                if (!in_array($cleanPath, $paths)) {
                    $paths[] = $cleanPath;
                }
            }
        }
        
        return $paths;
    }
    
    protected function init()
    {
        // Create cache directory
        if (!is_dir($this->cachePath)) {
            @mkdir($this->cachePath, 0755, true);
        }
        
        // Pastikan viewsPath adalah array
        $viewsPaths = is_array($this->viewsPath) ? $this->viewsPath : [$this->viewsPath];
        
        $this->blade = new BladeOne($viewsPaths, $this->cachePath, BladeOne::MODE_AUTO);
        $this->setup();
    }
    
    protected function setup()
    {
        try {
            if (method_exists($this->blade, 'setBaseUrl')) {
                $this->blade->setBaseUrl(base_url());
            }

            // Setup Blade directives termasuk kodhe()
            $this->setup_blade_directives();

        } catch (Exception $e) {
            // Ignore
        }
    }
    
    public function render($view, $data = [])
    {
        $view = str_replace('/', '.', $view);
        return $this->blade->run($view, $data);
    }
    
    public function exists($view)
    {
        $view = str_replace('.', '/', $view) . $this->getExtension();
        
        // Cek di semua paths
        if (is_array($this->viewsPath)) {
            foreach ($this->viewsPath as $path) {
                if (file_exists($path . '/' . $view)) {
                    return true;
                }
            }
            return false;
        }
        
        return file_exists($this->viewsPath . '/' . $view);
    }
    
    public function getExtension()
    {
        return '.blade.php';
    }
    
    /**
     * Get all view paths
     */
    public function getViewPaths()
    {
        return $this->viewsPath;
    }
    
    /**
     * Add additional view path
     */
    public function addViewPath($path)
    {
        $cleanPath = rtrim($path, '/\\');
        
        if (!in_array($cleanPath, $this->viewsPath)) {
            $this->viewsPath[] = $cleanPath;
            
            // Reinitialize Blade dengan paths yang baru
            $this->reinitBlade();
        }
        
        return $this;
    }
    
    /**
     * Reinitialize Blade dengan paths yang update
     */
    protected function reinitBlade()
    {
        if ($this->blade) {
            // Set path di BladeOne
            $this->blade->setPath($this->viewsPath);
        }
    }
    
    protected function setup_blade_directives()
    {
        if (!$this->blade || !method_exists($this->blade, 'directive')) {
            return;
        }
        
        // Register @app() directive untuk set/get
        $this->blade->directive('app', function($expression) {
            // Parse expression: 'set', 'nama', 'Rohmad Kadarwanto'
            $expression = trim($expression, "()");
            
            if (empty($expression)) {
                return "<?php echo app(); ?>";
            }
            
            // Parse arguments
            $args = $this->parse_arguments($expression);
            
            if (count($args) > 0) {
                $method = trim($args[0], "'\"");
                
                if ($method === 'set' && count($args) >= 3) {
                    $key = trim($args[1], "'\"");
                    $value = $args[2]; // Biarkan dengan quotes
                    return "<?php app()->set('{$key}', {$value}); ?>";
                }
                elseif ($method === 'get' && count($args) >= 2) {
                    $key = trim($args[1], "'\"");
                    $default = count($args) >= 3 ? $args[2] : "''";
                    return "<?php echo app()->get('{$key}', {$default}); ?>";
                }
            }
            
            return "<?php echo app({$expression}); ?>";
        });
        
        // Register helper functions lainnya
        $this->register_helper_directives($this->blade);
    }
    
    protected function register_helper_directives($blade)
    {
        $directives = [
            'base_url',
            'site_url', 
            'current_url',
            'anchor',
            'img',
            'form_open',
            'form_close',
            'form_input',
            'form_dropdown',
            'form_submit',
            'form_error',
            'validation_errors',
            'set_value',
            'set_select',
            'lang',
            'csrf'
        ];
        
        foreach ($directives as $directive) {
            $blade->directive($directive, function($expression) use ($directive) {
                if (empty($expression) || $expression === "''" || $expression === '""') {
                    return "<?php echo {$directive}(); ?>";
                }
                return "<?php echo {$directive}({$expression}); ?>";
            });
        }

        // Register common CI $this-> methods
        $methods = ['load', 'db', 'email', 'upload', 'cart'];
        foreach ($methods as $method) {
            $blade->directive($method, function() use ($method) {
                return "<?php echo \$this->{$method}; ?>";
            });
        }
    }
    
    protected function parse_arguments($expression)
    {
        $args = [];
        $current = '';
        $in_quote = false;
        $quote_char = '';
        $paren_count = 0;
        
        for ($i = 0; $i < strlen($expression); $i++) {
            $char = $expression[$i];
            
            if ($char === "'" || $char === '"') {
                if (!$in_quote) {
                    $in_quote = true;
                    $quote_char = $char;
                } elseif ($char === $quote_char) {
                    $in_quote = false;
                }
                $current .= $char;
            }
            elseif ($char === ',' && !$in_quote && $paren_count === 0) {
                $args[] = trim($current);
                $current = '';
            }
            elseif ($char === '(') {
                $paren_count++;
                $current .= $char;
            }
            elseif ($char === ')') {
                $paren_count--;
                $current .= $char;
            }
            else {
                $current .= $char;
            }
        }
        
        if (!empty($current)) {
            $args[] = trim($current);
        }
        
        return $args;
    }
}
