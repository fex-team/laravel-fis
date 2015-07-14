功能说明
======================

## 扩展 ViewResolver
  
目的是为了让模板路径支持用 fis 的`资源 ID` 格式。

比如：

* `widget/header.blade.php`
* `common:widget/header.blade.php`

Laravel 中自带模板路径路径写法太诡异。不能写后缀 `.balde.php`，文件夹用 `.` 分割。

## 扩展模板引擎

目的是在模板解析后，吐出到浏览器端前，进行一次 `filter` 过滤，替换占位符。实现把收集到的 js 和 css 替换到页面中适当的位置。

## 共享 `__fis` 对象

让模板中可以通过 `$__fis` 变量来控制 fis 的[资源加载实例](https://github.com/fex-team/laravel-fis/blob/master/src/Fis/Resource.php)。

## 扩展 blade 语法

让 blade 支持更多的用法，更多说明请查看 laravel 解决反感中[扩展的 blade 说明](https://github.com/fis-scaffold/laravel#扩展的-blade-语法说明)


