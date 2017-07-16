<?php

namespace PhpBoot\Controller;
use FastRoute\RouteCollector;
use PhpBoot\Application;
use PhpBoot\Utils\SerializableFunc;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ControllerBuilder
 */
class ControllerBuilder
{
    public function __construct($className)
    {
        $this->className = $className;
    }
    /**
     * 添加路由
     * @param Route $route
     * @param string $actionName class method
     * @return void
     */
    public function addRoute($actionName, Route $route)
    {
        !array_key_exists($actionName, $this->routes) or fail("repeated @route for {$this->className}::$actionName");
        $this->routes[$actionName] = $route;
    }
    /**
     * 获取路由列表
     * @return Route[]
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * 获取路由列表
     * @params Route[] $routes
     */
    public function setRoutes($routes)
    {
        $this->routes = $routes;
    }
    /**
     * 获取指定名称的路由
     * @param $actionName
     * @return Route|false
     */
    public function getRoute($actionName)
    {
        if (array_key_exists($actionName, $this->routes)){
            return $this->routes[$actionName];
        }
        return false;
    }

    static public function dispatch(
        $className,
        $actionName,
        Route $route,
        Application $app,
        Request $request)
    {
        $ctrl = $app->make($className);
        return $route->invoke([$ctrl, $actionName], $request);
    }


//    /**
//     * 应用路由, 使路由生效
//     * @param RouteCollector $r
//     * @return RouteCollector
//     */
//    public function build(RouteCollector $r)
//    {
//        foreach ($this->routes as $actionName=>$route){
//            $r->addRoute(
//                $route->getMethod(),
//                $route->getUri(),
//                new SerializableFunc(self::class.'::dispatch', $this->className, $actionName, $route)
//            );
//        }
//        return $r;
//    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * 获取uri前缀
     * @return string
     */
    public function getPathPrefix()
    {
        return $this->prefix;
    }

    /**
     * 设置uri前缀
     * @param string $prefix
     */
    public function setPathPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * @return string
     */
    public function getFileName()
    {
        return $this->fileName;
    }

    /**
     * @param string $fileName
     */
    public function setFileName($fileName)
    {
        $this->fileName = $fileName;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getSummary()
    {
        return $this->summary;
    }

    /**
     * @param string $summary
     */
    public function setSummary($summary)
    {
        $this->summary = $summary;
    }

    /**
     * @var string
     * the prefix path for all routes of the controller
     */
    private $prefix;

    /**
     * @var string
     */
    private $className;

    /**
     * @var Route[]
     */
    private $routes=[];

    /**
     * @var string
     */
    private $description='';
    /**
     * @var string
     */
    private $summary='';

    /**
     * @var string
     */
    private $fileName;
}