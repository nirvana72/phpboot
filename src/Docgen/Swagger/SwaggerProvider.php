<?php
namespace PhpBoot\Docgen\Swagger;

use PhpBoot\Application;
use Symfony\Component\HttpFoundation\Response;

class SwaggerProvider
{
    /**
     * How to use
     *
     * SwaggerProvider::register($app, function(Swagger $swagger){
     *          $swagger->host = 'api.example.com',
     *          $swagger->info->description = '...';
     *          ...
     *      },
     *      '/docs')
     *
     * @param Application $app
     * @param string $prefix
     * @param callable $callback
     * @return void
     */
    static public function register(Application $app,
                                    callable $callback = null,
                                    $namesapce)
    {
      // 'App\Controllers\Admin' -> admin
      $path = explode('\\', $namesapce);
      $path = $path[count($path) - 1];
      $path = strtolower($path);
      $app->addRoute('GET', "/swagger-{$path}.json", function (Application $app) use($callback, $namesapce){
        $swagger = new Swagger();
        $swagger->appendControllers($app, $app->getControllers($namesapce));
        if($callback){
            $callback($swagger);
        }
        return new Response($swagger->toJson());
      });
    }
}