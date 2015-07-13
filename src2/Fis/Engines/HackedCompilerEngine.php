<?php namespace Fis\Engines;

use Illuminate\View\Engines\CompilerEngine;
use Fis\Facades\Fis;
use Illuminate\View\Factory;

class HackedCompilerEngine extends CompilerEngine {
    public function get($path, array $data = array())
    {
        $env = (Object)$data['__env'];
        $result =  parent::get($path, $data);
        $env->decrementRender();
        $doneRendering = $env->doneRendering();
        $env->incrementRender();
        $doneRendering && ($result = Fis::filter($result));
        return $result;
    }


}