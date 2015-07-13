<?php namespace Fis\Providers;

use Fis\Engines\HackedCompilerEngine;
use Fis\FisIdResolver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Fis\Resource as FisResource;
use App;

class ResourceProvider extends ServiceProvider {

    /**
	 * Bootstrap the application services.
	 *
	 * @return void
	 */
	public function boot()
	{
        $this->overrideViewResolver();
        $this->overrideCompilerEngine();
        $this->shareFis();
        $this->extendBlade();
	}

	/**
	 * Register the application services.
	 *
	 * @return void
	 */
	public function register()
	{
        $this->app->singleton('fis', function($app) {
            $mapPath = '';

            if (isset($app['config']['view']['mapPath'])) {
                $mapPath = $app['config']['view']['mapPath'];
            } else {
                $mapPath = realpath(base_path('resources/map'));
            }

            return new FisResource($app['files'], $mapPath);
        });
	}

    protected function overrideViewResolver() {
        $this->app->bind('view.finder', function($app)
        {
            $paths = $app['config']['view.paths'];
            return new FisIdResolver($app['files'], $paths);
        });
    }

    /**
     * For filter template output.
     */
    protected function overrideCompilerEngine() {
        $app = $this->app;
        $resolver = $app->make('view.engine.resolver');
        $resolver->register('blade', function() use ($app)
        {
            return new HackedCompilerEngine($app['blade.compiler'], $app['files']);
        });
    }

    protected function shareFis() {
        $fis = $this->app->make('fis');

        View::composer('*', function($view) use ($fis)
        {
            $view->with('__fis', $fis);
        });
    }

    /**
     * Extend Blade Syntax
     */
    protected function extendBlade() {
        $blade = $this->app['view']->getEngineResolver()->resolve('blade')->getCompiler();

        $blade->extend(function($value) use ($blade)
        {
            return $this->compileBlade($value, $blade);
        });
    }

    /**
     * Syntax:
     *
     * @framework('/static/mod.js')
     *
     * @require('/static/mod.js', $prefix, $affix)
     *
     * @uri('/static/mod.js')
     *
     * @widget('widget/xxx.blade.php', array('some'=>'data'));
     *
     * @script($uri, $prefix, $affix)
     * @endscript
     *
     * @style($uri, $prefix, $affix)
     * @endstyle
     *
     * @placeholder('framework')
     * @placeholder('resource_map');
     * @placeholder('styles');
     * @placeholder('scripts');
     */
    protected function compileBlade($value, $blade) {
        $value = $this->compileStatements($value, $blade);
        $value = preg_replace('/\{\\?([\s\S]+?)\\?\}/x', '<?php ${1} ?>', $value);
        return $value;
    }

    protected function compileStatements($value) {
        $callback = function($match) {
            if (method_exists($this, $method = 'compile'.ucfirst($match[1])))
            {
                $match[0] = $this->$method(array_get($match, 3), $match);
            }

            return isset($match[3]) ? $match[0] : $match[0].$match[2];
        };

        return preg_replace_callback('/\B@(\w+)([ \t]*)(\( ( (?>[^()]+) | (?3) )* \))?/x', $callback, $value);
    }

    protected function compileExtends($expression, $match) {
        $params = explode(",", $match[4]);
        $params[0] = "\$__fis->uri({$params[0]})";
        $expression = join(",", $params);
        return "@extends({$expression})";
    }

    protected function compileFramework($expression) {
        return "<?php \$__fis->setFramework{$expression}; ?>";
    }

    protected function compileRequire($expression, $match) {
        $params = explode(",", $match[4]);
        $params[0] = "<?php echo {$params[0]} ?>";
        $expression = join(", ", $params);
        return "<!--f.r(".$expression.")-->";
    }

    protected function compileInclude($expression, $match) {
        $params = explode(",", $match[4]);
        $params[0] = "\$__fis->uri({$params[0]})";
        $expression = join(",", $params);
        return "@include({$expression})";
    }

    protected function compileUri($expression) {
        return "<?php echo \$__fis->uri{$expression}; ?>";
    }

    protected function compileUrl($expression) {
        return "\$__fis->uri{$expression}";
    }

    protected function compileWidget($expression, $match) {
        $params = explode(",", $match[4]);
        $params[0] = "\$__fis->uri({$params[0]})";
        $expression = join(",", $params);
        return "@include({$expression})";
    }

    protected function compilePlaceholder($expression) {
        return "<?php echo \$__fis->placeholder{$expression}; ?>";
    }

    protected function compileScript($expression) {
        return "<?php \$__fis->startScript{$expression}; ?>";
    }

    protected function compileStyle($expression) {
        return "<?php \$__fis->startStyle{$expression}; ?>";
    }

    protected function compileEndscript($expression) {
        return "<?php \$__fis->endScript(); ?>";
    }

    protected function compileEndstyle($expression) {
        return "<?php \$__fis->endStyle(); ?>";
    }
}
