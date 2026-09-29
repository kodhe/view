<?php

declare(strict_types=1);

namespace Kodhe\Framework\View\Engine;

interface EngineInterface
{
    public function render($view, $data = []);
    public function exists($view);
    public function getExtension();
}
