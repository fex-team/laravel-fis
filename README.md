# FEX-Team/Laravel-FIS

[![Laravel](https://img.shields.io/badge/Laravel-~5.0-orange.svg?style=flat-square)](http://laravel.com)

适用于 Laravel 5 的 FIS 资源加载器。

- 关于前端编译部分，请查看[fis3 laravel 脚手架](https://github.com/fis-scaffold/laravel)。
- 关于本模块功能扩展，请查看 [功能说明](./functions.md)


## Getting Started

1. 添加依赖到 `composer.json` 文件中，并通过 `composer update` 下载下来。

  ```
  "require": {
  ...
  
  "fex-team/laravel-fis": "*",
  
  ...
  },
  ```
2. 添加 Provider 到 `config/app.php` 配置项中。

  ```php
  'providers' => [

    /*
     * Laravel Framework Service Providers...
     */
    'Illuminate\Foundation\Providers\ArtisanServiceProvider',
    'Illuminate\Auth\AuthServiceProvider',
  
    // ...

    // 添加 FIS 的 Provider
    'Fis\Providers\ResourceProvider',

  ],
  
  ```
3. 如果你想更直接的使用 FIS Facades 的话，请添加 aliases。同样是 `config/app.php` 配置项中。

  ```php
  'aliases' => [

    'App'       => 'Illuminate\Support\Facades\App',
    'Artisan'   => 'Illuminate\Support\Facades\Artisan',

    // ...

    'Fis'       => 'Fis\Facades\Fis',
  ],
  ```
4. 默认 fis 产出的 `map.json` 文件应该存放在 `resources/map` 目录，如果想修改，请修改 `config/view.php` 配置文件。

  ```php
  /*
  |--------------------------------------------------------------------------
  | View Storage Paths
  |--------------------------------------------------------------------------
  |
  | Most templating systems load templates from disk. Here you may specify
  | an array of paths that should be checked for your views. Of course
  | the usual Laravel view path has already been registered for you.
  |
  */

  'paths' => [
    realpath(base_path('resources/views'))
  ],

  // ...

  // 配置 map.json 读取目录，程序运行前，请先记得用 fis 编译产出到 Laravel 项目目录。 
  'mapPath' => realpath(base_path('resources/map'))
  ```

  ## Change Log

  ### 2015/07/13 发布 1.0 版本
