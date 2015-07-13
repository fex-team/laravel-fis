<?php
/**
 * Created by PhpStorm.
 * User: liaoxuezhi
 * Date: 5/25/15
 * Time: 8:33 AM
 */

namespace Fis;


use Fis\Facades\Fis;
use Illuminate\View\FileViewFinder;

class FisIdResolver extends FileViewFinder {

    /**
     * 提供更多的可能文件，供查找。
     */
    protected function getPossibleViewFiles($name)
    {
        $arr = array();
        $name = ltrim($name, ".");
        $name = preg_replace('/\.blade\.php$/', "", $name);
        $parts = explode(".", $name);

        foreach($this->extensions as $extension) {
            $len = sizeof($parts);
            $i = 0;
            $dir = "";
            for(; $i < $len; $i++) {
                $basename = join(".", array_slice($parts, $i));
                $arr[] = $dir.$basename.".".$extension;
                $dir .= $parts[$i]."/";
            }
        }

        return $arr;
    }

}