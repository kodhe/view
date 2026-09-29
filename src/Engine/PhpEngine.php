<?php

declare(strict_types=1);

namespace Kodhe\Framework\View\Engine;

class PhpEngine implements EngineInterface
{
    protected $CI;
    protected $viewsPath;
    
    public function __construct($config = [])
    {
        $this->CI =& get_instance();

        $this->viewsPath = is_array($config) ? ($config['views_path'] ?? VIEWPATH) : VIEWPATH;
        $this->viewsPath = rtrim($this->viewsPath, '/') . '/';

        if (method_exists($this->CI->load, 'add_view_path')) {
            $this->CI->load->add_view_path($this->viewsPath);
        }
    }
    
    // Di PhpEngine.php, method render():
    public function render($view, $data = [])
    {
        if (method_exists($this->CI->load, 'prepend_view_path')) {
            $this->CI->load->prepend_view_path($this->viewsPath);
        }
        
        $templateData = get_template_assets();
        $allData = array_merge($templateData, $data);
        
        // ✅ Simpan dan hapus layout dari data
        $layout = null;
        if (!empty($allData['_layout'])) {
            $layout = $allData['_layout'];
            unset($allData['_layout']);  // 🔑 HAPUS dari data yang diteruskan
        }
        
        // Jika ada layout, render dengan layout
        if ($layout) {
            // Render content TANPA _layout
            $allData['content'] = $this->renderContent($view, $allData);

            // Render layout TANPA _layout (sudah di-unset)
            return $this->renderLegacyView($layout, $allData);
        }

        // Render biasa tanpa layout
        return $this->renderLegacyView($view, $allData);
    }

    /**
     * Common header/meta variables that legacy theme partials (e.g.
     * partials/_header.php) tend to reference directly. When a controller
     * does not pass them, provide safe defaults so the view renders without
     * "Undefined variable" warnings instead of triggering the error handler.
     */
    private const DEFAULT_VIEW_VARS = [
        'description'      => '',
        'meta_description' => '',
        'keywords'         => '',
        'meta_keywords'    => '',
        'title'            => '',
        'page_title'       => '',
    ];

    /**
     * Dispatch a single view render through the legacy CI3 loader.
     *
     * Legacy theme partials (e.g. partials/_header.php) often reference
     * common template variables like $description directly. When a
     * controller forgets to pass them, PHP 8 raises an "Undefined variable"
     * warning that surfaces as a CI error page. To keep each render isolated
     * we snapshot the loader's cached vars beforehand; safe defaults are only
     * injected for keys missing from BOTH the passed data and the cache, and
     * those injected defaults are rolled out of the cache afterwards so no
     * synthetic empty value leaks into subsequent renders. Values that views
     * intentionally set via $this->load->vars() during render (a common CI3
     * pattern for propagating data between nested views) are preserved.
     *
     * @param  string $view
     * @param  array  $data
     * @return string
     */
    protected function renderLegacyView($view, $data = [])
    {
        $loader = $this->CI->load;

        // Snapshot of the loader's cached vars BEFORE we inject anything.
        $canSnapshot = method_exists($loader, 'get_vars')
            && method_exists($loader, 'clear_vars')
            && method_exists($loader, 'vars');

        $previousVars = $canSnapshot ? $loader->get_vars() : null;

        // Fill in safe defaults for commonly used template variables so
        // legacy partials (e.g. partials/_header.php referencing
        // $description) don't emit "Undefined variable" warnings when a
        // controller forgets to pass them. A default is only injected when
        // the key is absent from BOTH the passed data and the loader's
        // cached vars, and any default we inject is rolled out of the cache
        // afterwards — so real values always win and no synthetic empty
        // string leaks into subsequent renders.
        $injectedDefaults = [];
        $cachedVars = is_array($previousVars) ? $previousVars : [];

        foreach (self::DEFAULT_VIEW_VARS as $key => $default) {
            if (!array_key_exists($key, $data) && !array_key_exists($key, $cachedVars)) {
                $data[$key] = $default;
                $injectedDefaults[] = $key;
            }
        }

        try {
            if (method_exists($loader, 'legacy_view')) {
                return $loader->legacy_view($view, $data, true);
            }

            return $loader->view($view, $data, true);
        } finally {
            if ($canSnapshot) {
                // Undo only the defaults we injected above; leave everything
                // else untouched. Deliberately NOT restoring the full snapshot:
                // views sometimes call $this->load->vars() during render to
                // propagate data to sibling views (a common CI3 pattern), and
                // a blanket rollback would break that flow.
                $currentVars = $loader->get_vars();
                $needsCleanup = false;

                foreach ($injectedDefaults as $key) {
                    if (array_key_exists($key, $currentVars)) {
                        $needsCleanup = true;
                        break;
                    }
                }

                if ($needsCleanup) {
                    $loader->clear_vars();

                    foreach ($currentVars as $key => $value) {
                        if (in_array($key, $injectedDefaults, true)) {
                            continue;
                        }

                        $loader->vars($key, $value);
                    }
                }
            }
        }
    }

    protected function renderContent($view, $data = [])
    {
        return $this->renderLegacyView($view, $data);
    }
    
    public function exists($view)
    {
        $view = $view . $this->getExtension();
        
        $fullPath = $this->viewsPath . ltrim($view, '/');
        if (file_exists($fullPath)) {
            return true;
        }
        
        if (method_exists($this->CI->load, 'get_view_paths')) {
            foreach ($this->CI->load->get_view_paths() as $path => $cascade) {
                if ($path === $this->viewsPath) {
                    continue;
                }
                $fullPath = rtrim($path, '/') . '/' . ltrim($view, '/');
                if (file_exists($fullPath)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    public function getExtension()
    {
        return '.php';
    }
    
    public function setPath($path)
    {
        $this->viewsPath = rtrim($path, '/') . '/';
        
        if (method_exists($this->CI->load, 'add_view_path')) {
            $this->CI->load->add_view_path($this->viewsPath);
        }
        return $this;
    }
    
    public function getPath()
    {
        return $this->viewsPath;
    }
}
