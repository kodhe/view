<?php

declare(strict_types=1);

namespace Kodhe\Framework\View;

class ViewLoader
{
    protected $viewFactory;
    protected $CI;
    
    public function __construct(ViewFactory $viewFactory = null)
    {
        $this->CI =& get_instance();
        $this->viewFactory = $viewFactory ?: new ViewFactory();
        
        // Inject ke CI untuk backward compatibility
        $this->CI->load = $this;
    }
    
    /**
     * CodeIgniter style view loading
     */
    public function view($view, $data = [], $return = false)
    {
        $output = $this->viewFactory->view($view, $data, true);
        
        if ($return) {
            return $output;
        }
        
        // Append to CI output buffer untuk multiple view calls
        if (property_exists($this->CI, 'output')) {
            $this->CI->output->append_output($output);
        } else {
            echo $output;
        }
        
        return null;
    }
    
    /**
     * Multiple view rendering dengan layout style
     */
    public function renderViews(array $views, $data = [], $return = false)
    {
        $output = '';
        
        foreach ($views as $view) {
            $output .= $this->viewFactory->view($view, $data, true);
        }
        
        if ($return) {
            return $output;
        }
        
        $this->viewFactory->response()->setBody($output)->send();
        
        return null;
    }
    
    /**
     * Legacy support untuk $this->load->view()
     */
    public function _ci_load($_ci_data)
    {
        extract($_ci_data);
        
        if (isset($_ci_view)) {
            $output = $this->viewFactory->view($_ci_view, $_ci_vars, true);
            
            if (isset($_ci_return) && $_ci_return === TRUE) {
                return $output;
            }
            
            echo $output;
        }
    }
}
