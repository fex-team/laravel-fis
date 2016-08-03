<?php

namespace Fis;


use Illuminate\Filesystem\Filesystem;

class Resource {

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The map.json path.
     *
     * @var array
     */
    protected $path;

    const CSS_LINKS_HOOK = '<!--[FIS_CSS_LINKS_HOOK]-->';
    const JS_SCRIPT_HOOK = '<!--[FIS_JS_SCRIPT_HOOK]-->';
    const FRAMEWORK_HOOK = '<!--[FIS_FRAMEWORK_HOOKb]-->';
    const FRAMEWORK_CONFIG_HOOK = '<!--[FIS_FRAMEWORK_CONFIG_HOOK]-->';
    const FRAMEWORK_CONFIG_HOOK_WITH_SCRIPT = '<!--[FIS_FRAMEWORK_CONFIG_HOOK_WITH_SCRIPT]-->';

    protected $resoucrcType = 'amd';

    protected $maps;
    protected $loaded;
    protected $res;
    protected $css;
    protected $js;
    protected $asyncs;
    protected $_calculated;
    protected $framework;

    protected $stacks;

    public function __construct(Filesystem $files, $path) {
        $this->files = $files;
        $this->path = $path;
        $this->maps = array();

        $this->loaded = array();
        $this->res = array();

        $this->css = array();
        $this->js = array();
        $this->asyncs = array();
        $this->_calculated = false;

        $this->stacks = array();
    }

    public function setFramework($id, $type = 'auto') {
        $this->framework = $id;
        if ($type === 'auto' && preg_match('/mod\.js$/i', $id)) {
            $this->resoucrcType = 'mod';
        }
    }

    public function add($id, $defer = false, $prefix = '', $affix = '', $withPkg = false) {
        $node = $this->getNode($id);
        $defer = !!$defer;

        if ($node == null) {
            return $id;
        }

        // css 不支持异步加载。
//        if (isset($node['type']) && $node['type'] === 'css' && $defer) {
//            $defer = false;
//        }

//        print_r($id);
//        echo "\n";

        if (isset($this->loaded[$id]) && ($this->loaded[$id] === $defer || $defer && $this->loaded[$id] === false ) ) {
            // 如果之前是同步的这次异步添加则忽略掉。都同步添加过了，不需要异步再添加一次。
            return $this->uri($id, true);
        } else if (isset($this->loaded[$id]) && $this->loaded[$id] === true && !$defer) {
            $this->remove($id, true);
        }

        $this->loaded[$id] = $defer;

        if ($withPkg && !empty($node['pkg'])) {
            $pkgNode =  $this->getNode($node['pkg'], 'pkg');
            $uri = $pkgNode['uri'];

            if (isset($pkgNode['has'])) {
                foreach($pkgNode['has'] as $dep) {
                    if ($id !== $dep) {
                        $this->loaded[$dep] = $defer;
                    }
                }
            }

            isset($pkgNode['deps']) && ($node['deps'] = $pkgNode['deps']);
        } else {
            $uri = $node['uri'];
        }

        if (isset($node['extras']) && isset($node['extras']['async'])) {
            foreach($node['extras']['async'] as $res) {
                $this->add($res, true, $prefix, $affix, $withPkg);
            }
        }

        if (isset($node['deps'])) {
            foreach($node['deps'] as $dep) {
                $this->add($dep, $defer, $prefix, $affix, $withPkg);
            }
        }

        $type = $node['type'];

        if ($type !== 'css' && $type !== 'js') {
            return $this->uri($id, true);
        }

        $this->res[] = array(
            'uri' => $uri,
            'id' => $id,
            'pkg' => isset($node['pkg']) ? $node['pkg'] : '',
            'type' => $type,
            'async' => $defer,
            'prefix' => $prefix,
            'affix' => $affix
        );

        return $this->uri($id, true);
    }

    public function remove($id, $defer = false) {
        $node = $this->getNode($id);
        $defer = !!$defer;

        if ($node == null) {
            return;
        }

        if (isset($this->loaded[$id]) && $this->loaded[$id] === $defer) {
            unset($this->loaded[$id]);
        }

        if (isset($node['extras']) && isset($node['extras']['async'])) {
            foreach($node['extras']['async'] as $res) {
                $this->remove($res, true);
            }
        }

        if (isset($node['deps'])) {
            foreach($node['deps'] as $dep) {
                $this->remove($dep, $defer);
            }
        }

        $type = $node['type'];

        if ($type !== 'css' && $type !== 'js') {
            return;
        }

        $index = $this->search($this->res, function($value) use($id, $defer) {
            return isset($value['id']) && $value['id'] === $id && $value['async'] === $defer;
        });

        if ($index !== false) {
            array_splice($this->res, $index, 1);
        }
    }

    public function addJs($id, $prefix = '', $affix = '') {
        if ($this->exists($id)) {
            $this->add($id, false, $prefix, $affix);
        } else {
            $this->res[] = array(
                'uri' => $id,
                'type' => 'js',
                'prefix' => $prefix,
                'affix' => $affix
            );
        }
    }

    public function addJsEmbed($body, $prefix = '', $affix = '') {
        $this->res[] = array(
            'embed' => true,
            'type' => 'js',
            'content' => $body,
            'prefix' => $prefix,
            'affix' => $affix
        );
    }

    public function addCss($id, $prefix = '', $affix = '') {
        if ($this->exists($id)) {
            $this->add($id, false, $prefix, $affix);
        } else {
            $this->res[] = array(
                'uri' => $id,
                'type' => 'css',
                'prefix' => $prefix,
                'affix' => $affix
            );
        }
    }

    public function addCssEmbed($body, $prefix = '', $affix = '') {
        $this->res[] = array(
            'embed' => true,
            'type' => 'css',
            'content' => $body,
            'prefix' => $prefix,
            'affix' => $affix
        );
    }

    public function uri($id, $pkg = false) {
        $node = $this->getNode($id);

        if ($node == null) {
            return $id;
        }

        if ($pkg && isset($node['pkg'])) {
            $node = $this->getNode($node['pkg'], "pkg");
        }

        $uri = $node['uri'];

        return $uri;
    }

    protected function last($array) {
        return count($array) ? $array[count($array) -1] : null;
    }

    protected function checkStack() {
        $last = $this->last($this->stacks);

        if (isset($last) && isset($last['type'])) {
            $last['type'] === 'js' ? $this->endScript() : $this->endStyle();
        }
    }

    public function startScript($uri = '', $prefix = '', $affix = '') {
        $this->checkStack();
        $this->stacks[] = array(
            'uri' => $uri,
            'prefix' => $prefix,
            'affix' => $affix,
            'type' => 'js'
        );
        ob_start();
    }

    public function endScript() {
        $last = array_pop($this->stacks);

        if (!isset($last) || $last['type'] !== 'js') {
            throw new \Exception("Syntax Error");
        }

        $body = trim(ob_get_clean());

        if ($body) {
//            $this->addJsEmbed($body, $last['uri'], $last['prefix']);
            echo "<!--f.jb(".$last['prefix'].", ".$last['affix'].", ".$body.")-->";
        } else {
//            $this->addJs($last['uri'], $last['prefix'], $last['affix']);
            echo "<!--f.js(".$last['prefix'].", ".$last['affix'].", ".$last['uri'].")-->";
        }
    }

    public function startStyle($uri = '', $prefix = '', $affix = '') {
        $this->checkStack();
        $this->stacks[] = array(
            'uri' => $uri,
            'prefix' => $prefix,
            'affix' => $affix,
            'type' => 'css'
        );
        ob_start();
    }

    public function endStyle() {
        $last = array_pop($this->stacks);

        if (!isset($last) || $last['type'] !== 'css') {
            throw new \Exception("Syntax Error");
        }

        $body = trim(ob_get_clean());

        if ($body) {
//            $this->addCssEmbed($body, $last['uri'], $last['prefix']);
            echo "<!--f.cb(".$last['prefix'].", ".$last['affix'].", ".$body.")-->";
        } else {
//            $this->addCss($last['uri'], $last['prefix'], $last['affix']);
            echo "<!--f.cs(".$last['prefix'].", ".$last['affix'].", ".$last['uri'].")-->";
        }
    }

    public function placeholder($type, $resourceType = '') {
        if ($resourceType) {
            $this->resoucrcType = $resourceType;
        }

        if ($type === 'framework') {
            return self::FRAMEWORK_HOOK;
        } else if ($type === "styles") {
            return self::CSS_LINKS_HOOK;
        } else if ($type === 'framework_config') {
            return self::FRAMEWORK_CONFIG_HOOK;
        } else if ($type === 'framework_config_with_script') {
            return self::FRAMEWORK_CONFIG_HOOK_WITH_SCRIPT;
        }

        return self::JS_SCRIPT_HOOK;
    }

    public function exists($id) {
        return $this->getNode($id) != null;
    }

    protected function getNode($id, $type = 'res') {
        $data = $this->getMap($id);

        if ($data != null && isset($data[$type][$id])) {
            return $data[$type][$id];
        }

        return null;
    }

    protected function getMap($id) {
        list($namespace) = explode(":", $id);

        if ($namespace === $id) {
            $namespace = '__global__';
        }

        if (empty($this->maps[$namespace])) {
            $filename = 'map.json';

            if ($namespace !== '__global__') {
                $filename = $namespace.'-map.json';
            }

            $filename  = $this->path."/".$filename;

            if ($this->files->exists($filename)) {
                $contents = $this->files->get($filename);
                return $this->maps[$namespace] = json_decode($contents, true);
            }
        }

        return isset($this->maps[$namespace]) ? $this->maps[$namespace] : null;
    }

    protected function search($arr, callable $callback) {
        $found = false;

        foreach($arr as $index => $value) {
            if (call_user_func($callback, $value, $index)) {
                return $index;
            }
        }

        return $found;
    }

    protected function calculate() {
        if ($this->_calculated){
            return;
        }

        $this->_calculated = true;

        $res = $this->res;
        $this->res = array();
        $this->loaded = array();

        // add freamework
        if ($this->framework) {
            $this->add($this->framework, false, '', '', true);
            isset($this->res[0]) && ($this->res[0]['isFramework'] = true);
        }

        foreach ($res as $item) {
            if (isset($item['id'])) {
                $this->add($item['id'], $item['async'], $item['prefix'], $item['affix'], true);
            } else {
                $this->res[] = $item;
            }
        }

        $asyncCss = array();
        foreach($this->res as $item) {
            $list = null;

            if ($item['type'] === 'js') {
                if (empty($item['async'])) {
                    $this->js[] = $item;
                } else {
                    $this->asyncs[] = $item;
                }
            } else if ($item['type'] === 'css') {
                if (empty($item['async'])) {
                    $this->css[] = $item;
                } else {
                    $asyncCss[] = $item;
                }
            }
        }

        foreach($asyncCss as $item) {
            $idx = $this->search($this->css, function($target) use($item) {
                return $item['uri'] === $target['uri'];
            });

            if ($idx === false) {
                $this->css[] = $item;
            }
        }
    }

    protected function buildResourceMap() {
        $res = array();
        $pkg = array();

        $this->calculate();

        foreach($this->loaded as $id => $deffer) {
            if ($deffer !== true) {
                continue;
            }

            $node = $this->getNode($id);
            if (!isset($node) || $node['type'] !== 'js') {
                continue;
            }

            $item = array(
                'url' => $node['uri'],
                'type' => $node['type']
            );

            if (isset($node['deps'])) {
                $deps = array();
                foreach ($node['deps'] as $depId) {
                    $dep = $this->getNode($depId);

                    if (isset($dep) && $dep['type'] === 'js') {
                        $deps[] = $depId;
                    }
                }

                // 尼玛，filter 后 json 序列化成对象
                // $deps = array_filter($node['deps'], function($id) {
                //     return !empty($this->loaded[$id]) && !preg_match('/\.css$/', $id);
                // });

                if (count($deps)) {
                    $item['deps'] = $deps;
                }
            }

            $moudleId = isset($node['extra']) && isset($node['extra']['moduleId']) ? $node['extra']['moduleId'] : preg_replace('/\.js$/', '', $id);
            

            if (!empty($node['pkg'])) {
                $item['pkg'] = $node['pkg'];
                $pkgNode = $this->getNode($node['pkg'], 'pkg');
                $pkgItem = array(
                    'uri' => $pkgNode['uri'],
                    'type' => $pkgNode['type']
                );
                $pkg[$node['pkg']] = $pkgItem;
            }
            
            $res[$moudleId] = $item;
        }

        if (empty($res)) {
            return '';
        }

        $map = array(
            'res' => $res
        );

        if (!empty($pkg)) {
            $map['pkg'] = $pkg;
        }

        return 'require.resourceMap('.json_encode($map).');';
    }

    protected function buildAMDPath() {
        $paths = array();
        $this->calculate();
        foreach($this->loaded as $id => $deffer) {
            if ($deffer !== true) {
                continue;
            }

            $node = $this->getNode($id);
            if (!isset($node) || $node['type'] !== 'js') {
                continue;
            }

            $moudleId = isset($node['extra']) && isset($node['extra']['moduleId']) ? $node['extra']['moduleId'] : preg_replace('/\.js$/', '', $id);
            $uri = $node['uri'];

            if (!empty($node['pkg'])) {
                $pkgNode = $this->getNode($node['pkg'], 'pkg');
                $uri = $pkgNode['uri'];
            }

            $uri = preg_replace('/\.js$/', '', $uri);
            $paths[$moudleId] = $uri;
        }

        if (!empty($paths)) {
            return 'require.config({paths:'.json_encode($paths).'});';
        }

        return "";
    }

    public function filter($content) {

        $content = preg_replace_callback('/<!--f\.(\w+)\(([\s\S]+?)\)-->\n?/', function($m) {
            $params = explode(",", $m[2], 3);

            if ($m[1] == 'r') {
                $this->add($params[0], false, isset($params[1]) ? $params[1] : "", isset($params[2]) ? $params[2] : "");
            } else if ($m[1] == 'jb') {
                $this->addJsEmbed($params[2], $params[0], $params[1]);
            } else if ($m[1] == 'js') {
                $this->addJs($params[2], $params[0], $params[1]);
            } else if ($m[1] == 'cb') {
                $this->addCssEmbed($params[2], $params[0], $params[1]);
            } else if ($m[1] == 'cs') {
                $this->addCss($params[2], $params[0], $params[1]);
            }

            return "";
        }, $content);

        $content = trim($content);
        $this->calculate();

        if (false !== strpos($content, self::FRAMEWORK_HOOK)) {
            $framework = '';
            $idx = $this->search($this->js, function($item) {
                return !empty($item['isFramework']);
            });

            if ($idx !== false) {
                $item = $this->js[$idx];
                array_splice($this->js, $idx, 1);
                $framework = '<script type="text/javascript" src="'.$item['uri'].'"></script>';
            }

            $content = str_replace(self::FRAMEWORK_HOOK, $framework, $content);
        }

        $resourcemapOutputed = false;

        if (false !== strpos($content, self::FRAMEWORK_CONFIG_HOOK)) {
            $resourcemap = $this->resoucrcType === 'mod' ? $this->buildResourceMap() : $this->buildAMDPath();
            $resourcemapOutputed = true;
            $content = str_replace(self::FRAMEWORK_CONFIG_HOOK, $resourcemap, $content);
        } else if (false !== strpos($content, self::FRAMEWORK_CONFIG_HOOK_WITH_SCRIPT)) {
            $resourcemap = $this->resoucrcType === 'mod' ? $this->buildResourceMap() : $this->buildAMDPath();
            if ($resourcemap) {
                $resourcemap = "<script>${resourcemap}</script>";
            }
            $resourcemapOutputed = true;
            $content = str_replace(self::FRAMEWORK_CONFIG_HOOK_WITH_SCRIPT, $resourcemap, $content);
        }

        if (false !== strpos($content, self::JS_SCRIPT_HOOK)) {
            $js = '';

            if (!$resourcemapOutputed && ($resourcemap = $this->resoucrcType === 'mod' ? $this->buildResourceMap() : $this->buildAMDPath())) {
                array_splice($this->js, 0, 0, array(array(
                    'embed' => 1,
                    'content' => $resourcemap,
                    'prefix' => '',
                    'affix' => '',
                    'type' => 'js'
                )));
            }

            $pool = array();
            $prefix = '';
            $affix = '';

            foreach($this->js as $item) {
                if (!empty($item['embed']) && $item['prefix'] === $prefix && $item['affix'] === $affix) {
                    $prefix = $item['prefix'];
                    $affix = $item['affix'];
                    $pool[] = $item['content'];
                } else {
                    if (count($pool)) {
                        $js .= $prefix.'<script type="text/javascript">'.join("\n;", $pool).'</script>'.$affix;
                        $pool = array();
                        $prefix = '';
                        $affix = '';
                    }

                    if (empty($item['embed'])) {
                        $js .= $item['prefix'].'<script type="text/javascript" src="'.$item['uri'].'"></script>'.$item['affix'];
                    } else {
                        $js .= $item['prefix'].'<script type="text/javascript">'.$item['content'].'</script>'.$item['affix'];
                    }
                }
            }
            if (count($pool)) {
                $js .= $prefix.'<script type="text/javascript">'.join("\n;", $pool).'</script>'.$affix;
            }

            $content = str_replace(self::JS_SCRIPT_HOOK, $js, $content);
        }

        if (false !== strpos($content, self::CSS_LINKS_HOOK)) {
            $css = '';
            $pool = array();
            $prefix = '';
            $affix = '';

            foreach($this->css as $item) {
                if (!empty($item['embed']) && $item['prefix'] === $prefix && $item['affix'] === $affix) {
                    $prefix = $item['prefix'];
                    $affix = $item['affix'];
                    $pool[] = $item['content'];
                } else {
                    if (count($pool)) {
                        $css .= $prefix.'<style type="text/css">'.join("\n;", $pool).'</style>'.$affix;
                        $pool = array();
                        $prefix = '';
                        $affix = '';
                    }

                    if (empty($item['embed'])) {
                        $css .= $item['prefix'].'<link rel="stylesheet" type="text/css" href="'.$item['uri'].'" />'.$item['affix'];
                    } else {
                        $css .= $item['prefix'].'<style type="text/css">'.$item['content'].'</style>'.$item['affix'];
                    }
                }
            }

            if (count($pool)) {
                $css .= $prefix.'<style type="text/css">'.join("\n;", $pool).'</style>'.$affix;
            }

            $content = str_replace(self::CSS_LINKS_HOOK, $css, $content);
        }

        return $content;
    }
}
