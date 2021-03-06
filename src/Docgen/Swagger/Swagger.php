<?php
namespace PhpBoot\Docgen\Swagger;

use PhpBoot\Application;
use PhpBoot\Controller\ControllerContainer;
use PhpBoot\Controller\ExceptionRenderer;
use PhpBoot\Controller\Route;
use PhpBoot\Docgen\Swagger\Schemas\ArraySchemaObject;
use PhpBoot\Docgen\Swagger\Schemas\BodyParameterObject;
use PhpBoot\Docgen\Swagger\Schemas\OperationObject;
use PhpBoot\Docgen\Swagger\Schemas\OtherParameterObject;
use PhpBoot\Docgen\Swagger\Schemas\PrimitiveSchemaObject;
use PhpBoot\Docgen\Swagger\Schemas\RefSchemaObject;
use PhpBoot\Docgen\Swagger\Schemas\ResponseObject;
use PhpBoot\Docgen\Swagger\Schemas\SimpleModelSchemaObject;
use PhpBoot\Docgen\Swagger\Schemas\SwaggerObject;
use PhpBoot\Docgen\Swagger\Schemas\TagObject;
use PhpBoot\Entity\ArrayContainer;
use PhpBoot\Entity\EntityContainer;
use PhpBoot\Entity\ScalarTypeContainer;
use PhpBoot\Entity\TypeContainerInterface;
use PhpBoot\Metas\ParamMeta;
use PhpBoot\Metas\ReturnMeta;
use PhpBoot\Utils\ArrayHelper;
use PhpBoot\Validator\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Swagger extends SwaggerObject
{

    /**
     * @param Application $app
     * @param ControllerContainer[] $controllers
     */
    public function appendControllers(Application $app, $controllers)
    {
        foreach ($controllers as $controller) {
            $this->appendController($app, $controller);
        }
    }

    /**
     * @param Application $app
     * @param ControllerContainer $controller
     */
    public function appendController(Application $app, ControllerContainer $controller)
    {
        //tags
        $tag = new TagObject();
        $tag->name = $controller->getSummary();
        $tag->description = $controller->getDescription();

        foreach ($controller->getRoutes() as $action => $route) {
            $op = new OperationObject();
            $op->tags = [$controller->getSummary()];
            $op->summary = $route->getSummary();
            $op->description = $route->getDescription();
            
            $op->parameters = $this->getParamsSchema($app, $controller, $action, $route);
            if($this->hasFileParam($route)){
                $op->consumes = ['multipart/form-data'];
            }

            $op->responses['200'] = $this->getReturnSchema($app, $route->getReturnString()?:'void');
            
            $op->responses += $this->getExceptionsSchema($app, $controller, $action, $route);
            
            $uri = $app->getFullUri($route->getUri());
            if (!isset($this->paths[$uri])) {
                $this->paths[$uri] = [];
            }
            $method = strtolower($route->getMethod());
            $this->paths[$uri][$method] = $op;
        }
    }

    /**
     * @param Application $app
     * @param string $returnString
     * @return ResponseObject
     */
    public function getReturnSchema(Application $app, $returnString) {
        $retName = 'ret';
        $msgName = 'msg';
        $dataName = 'data';
        if ($app->has('swagger')) {
            $config = $app->get('swagger');
            $retName = $config['retName'];
            $msgName = $config['msgName'];
            $dataName = $config['dataName'];
        }

        $schema = new SimpleModelSchemaObject();
        $retSchema = new PrimitiveSchemaObject();
        $retSchema->type = 'integer';
        $schema->properties[$retName] = $retSchema;
        $msgSchema = new PrimitiveSchemaObject();
        $msgSchema->type = 'string';
        $schema->properties[$msgName] = $msgSchema;  

        $returnString = trim($returnString);
        if ($returnString !== 'void') {
            if (strpos($returnString, 'object[]') === 0) {
                $returnString = trim(substr($returnString, 8));
                $obj = json_decode($returnString);
                $arraySchema = new ArraySchemaObject();
                $arraySchema->items = $this->getPropertySchema($obj);
                $schema->properties[$dataName] = $arraySchema;
            } 
            elseif (strpos($returnString, 'object') === 0) {
                $returnString = trim(substr($returnString, 6));
                $obj = json_decode($returnString);
                $schema->properties[$dataName] = $this->getPropertySchema($obj);
            } 
            elseif (strpos($returnString, 'int[]') === 0) {
                $propertySchema = new PrimitiveSchemaObject();
                $propertySchema->type = 'integer';
                $arraySchema = new ArraySchemaObject();
                $arraySchema->items = $propertySchema;
                $schema->properties[$dataName] = $arraySchema;
            } 
            elseif (strpos($returnString, 'int') === 0) {
                $msgSchema = new PrimitiveSchemaObject();
                $msgSchema->type = 'integer';
                $schema->properties[$dataName] = $msgSchema;
            } 
            elseif (strpos($returnString, 'string[]') === 0) {
                $propertySchema = new PrimitiveSchemaObject();
                $propertySchema->type = 'string';
                $arraySchema = new ArraySchemaObject();
                $arraySchema->items = $propertySchema;
                $schema->properties[$dataName] = $arraySchema;
            } 
            elseif (strpos($returnString, 'string') === 0) {
                $msgSchema = new PrimitiveSchemaObject();
                $msgSchema->type = 'string';
                $schema->properties[$dataName] = $msgSchema;
            }
        }
        
        $responseObject = new ResponseObject();
        // $responseObject->description = '正常返回的描述';
        $responseObject->schema = $schema;
        return $responseObject;
    }

    /**
     * @param object $obj
     * @return SimpleModelSchemaObject
     */
    private function getPropertySchema($obj) {
        $schema = new SimpleModelSchemaObject();
        foreach($obj as $k => $v) {
            if (is_array($v)) {
                $arraySchema = new ArraySchemaObject();
                if (is_object($v[0])) {
                    $arraySchema->items = $this->getPropertySchema($v[0]);
                } else {
                    $propertySchema = new PrimitiveSchemaObject();
                    $propertySchema->type = is_numeric($v[0]) ? 'integer' : 'string';
                    $arraySchema->items = $propertySchema;
                }
                $schema->properties[$k] = $arraySchema;
            } elseif (is_object($v)) {
                $schema->properties[$k] = $this->getPropertySchema($v);
            } else {
                $propertySchema = new PrimitiveSchemaObject();
                $propertySchema->type = is_numeric($v) ? 'integer' : 'string';
                $schema->properties[$k] = $propertySchema;
            }
        }
        return $schema;
    }

    /**
     * @return string
     */
    public function toJson()
    {
        $json = $this->toArray();
        return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return self::objectToArray($this);
    }

    /**
     * @param $object
     * @return array
     */
    static public function objectToArray($object)
    {
        if (is_object($object)) {
            $object = get_object_vars($object);
        }
        $res = [];
        foreach ($object as $k => $v) {
            if ($v === null) {
                continue;
            }
            if (is_array($v) || is_object($v)) {
                $res[$k] = self::objectToArray($v);
            } else {
                $res[$k] = $v;
            }
        }
        return $res;
    }

    /**
     * @param Application $app
     * @param ControllerContainer $controller
     * @param $action
     * @param Route $route
     * @return array
     */
    public function getExceptionsSchema(Application $app,
                                        ControllerContainer $controller,
                                        $action,
                                        Route $route)
    {
        $handler = $route->getExceptionHandler();
        if (!$handler) {
            return [];
        }
        $schemas = [];
        foreach ($handler->getExceptions() as $exception) {
            list($name, $desc) = $exception;

            $ins = null;
            try{
                $ins = $app->make($name);
            }catch (\Exception $e){
                try{
                    $ins = new $name("");
                }catch (\Exception $e){

                }
            }

            //TODO status 重复怎么办
            if ($ins instanceof HttpException) {
                $status = $ins->getStatusCode();
            } else {
                $status = 500;
            }
            if (isset($schemas[$status])) {
                //$this->warnings[] = "status response $status has been used for $name, $desc";
                $res = $schemas[$status];
            } else {
                $res = new ResponseObject();
            }
            $shortName = self::getShortClassName($name);
            $desc = "$shortName: $desc";
            $res->description = self::implode("\n", [$res->description, $desc]);
            if($ins){
                $error = $app->get(ExceptionRenderer::class)->render($ins)->getContent();
                if($error){
                    $res->examples = [$shortName => $error];
                }
            }
            //$res->schema = new RefSchemaObject("#/definitions/$name");
            $schemas[$status] = $res;

        }
        return $schemas;
    }

    /**
     * @param Application $app
     * @param ControllerContainer $controller
     * @param $action
     * @param Route $route
     * @param EntityContainer $container
     * @return RefSchemaObject
     */
    public function getRefSchema(Application $app,
                                 ControllerContainer $controller,
                                 $action,
                                 Route $route,
                                 EntityContainer $container)
    {
        $name = $container->getClassName();
        if (!isset($this->definitions[$name])) {
            $this->definitions[$name] = $this->getObjectSchema($app, $controller, $action, $route, $container);
        }
        return new RefSchemaObject("#/definitions/$name");
    }

    public function getParamsSchema(Application $app,
                                    ControllerContainer $controller,
                                    $action,
                                    Route $route)
    {
        $params = $route->getRequestHandler()->getParamMetas();
        $parameters = [];
        $body = [];

        foreach ($params as $name => $param) {
            if ($param->isPassedByReference) {
                continue;
            }

            $name = substr($param->source, strlen('request.'));
            if ($route->hasPathParam($param->name)) {
                $in = 'path';
            } elseif (in_array($route->getMethod(), ['POST', 'PUT', 'PATCH'])) {
                $in = 'body';
            } else {
                $in = 'query';
            }

            if ($in === 'body') {
                if (!$name) {
                    $body = $param;
                } else {
                    ArrayHelper::set($body, $name, $param);
                }
            } else {
                if ($param->container instanceof ArrayContainer) {
                    $paramSchema = $this->getArraySchema($app, $controller, $action, $route, $param->container);
                    //TODO array for validation
                } elseif ($param->container instanceof EntityContainer) {
                    $paramSchema = $this->getRefSchema($app, $controller, $action, $route, $param->container);
                    //TODO array for validation
                } else {
                    $paramSchema = new PrimitiveSchemaObject();
                    $paramSchema->type = self::mapType($param->type);
                    self::mapValidation($param->validation, $paramSchema);
                }
                $paramSchema->in = $in;
                $paramSchema->name = $name;
                $paramSchema->description = $param->description;
                $paramSchema->default = $param->default;
                $paramSchema->required = !$param->isOptional;
                $parameters[] = $paramSchema;
            }
        }

        if ($body) {
            $paramSchema = new BodyParameterObject();
            $paramSchema->name = 'body';
            $paramSchema->in = 'body';
            $paramSchema->schema = $this->getRequestSchema($body);
            $parameters[] = $paramSchema;
        }

        return $parameters;
    }

    // 解析请求参数对象
    private function getRequestSchema($params) {
        $schema = new SimpleModelSchemaObject();
        foreach($params as $param) {
            // 如果参数是个数组
            if ($param->container instanceof ArrayContainer) {
                $arraySchema = new ArraySchemaObject();
                $itemContainer = $param->container->getContainer();
                if ($itemContainer instanceof ScalarTypeContainer) { // 数组是普通类型
                    $propertySchema = new PrimitiveSchemaObject();
                    $propertySchema->type = self::mapType($itemContainer->getType());
                    $arraySchema->items = $propertySchema;
                }
                if ($itemContainer instanceof EntityContainer) {  // 数组是对象类型
                    $props = $itemContainer->getProperties();
                    $arraySchema->items = $this->getRequestEntitySchema($props);
                }
                $schema->properties[$param->name] = $arraySchema;
            }
            // 参数是个对象
            if ($param->container instanceof EntityContainer) {
                $props = $param->container->getProperties();
                $schema->properties[$param->name] = $this->getRequestEntitySchema($props);
            }
            // 参数是普通类型
            if ($param->container instanceof ScalarTypeContainer) {
                $propertySchema = new PrimitiveSchemaObject();
                $propertySchema->type = self::mapType($param->container->getType());
                $schema->properties[$param->name] = $propertySchema;
            }
        }
        return $schema;
    }

    // 如果参数是个实体
    private function getRequestEntitySchema($props) {
        $schema = new SimpleModelSchemaObject();
        foreach ($props as $key => $prop) {
            if (trim(substr($prop->type, -2)) === '[]') {
                // 对象里参数是个数组
                $type = substr($prop->type, 0,-2);
                $arraySchema = new ArraySchemaObject();
                $propertySchema = new PrimitiveSchemaObject();
                $propertySchema->type = self::mapType($type);
                $arraySchema->items = $propertySchema;
                $schema->properties[$key] = $arraySchema;
            } else {
                // 对象里参数是个普通类型
                $propertySchema = new PrimitiveSchemaObject();
                $propertySchema->type = self::mapType($prop->type);
                $schema->properties[$key] = $propertySchema;
            }
        }
        return $schema;
    }

    /**
     * @param Application $app
     * @param ControllerContainer $controller
     * @param $action
     * @param Route $route
     * @param ArrayContainer $container
     * @return ArraySchemaObject
     */
    public function getArraySchema(Application $app,
                                   ControllerContainer $controller,
                                   $action,
                                   Route $route,
                                   ArrayContainer $container)
    {
        $schema = new ArraySchemaObject();
        $itemContainer = $container->getContainer();
        if ($itemContainer instanceof EntityContainer) {
            $itemSchema = $this->getRefSchema($app, $controller, $action, $route, $itemContainer);
        } elseif ($itemContainer instanceof ArrayContainer) {
            $itemSchema = $this->getArraySchema($app, $controller, $action, $route, $itemContainer);
        } elseif ($itemContainer instanceof ScalarTypeContainer) {
            $itemSchema = new PrimitiveSchemaObject();
            $itemSchema->type = self::mapType($itemContainer->getType());
        } else {
            $itemSchema = new PrimitiveSchemaObject();
            //$itemSchema->type = 'mixed';
        }
        $schema->items = $itemSchema;
        return $schema;
    }

    public function getObjectSchema(Application $app,
                                    ControllerContainer $controller,
                                    $action,
                                    Route $route,
                                    EntityContainer $container)
    {
        $schema = new SimpleModelSchemaObject();
        $schema->description = self::implode("\n", [$container->getSummary(), $container->getDescription()]);

        foreach ($container->getProperties() as $property) {

            if (!$property->isOptional) {
                $schema->required[] = $property->name;
            }
            if ($property->container instanceof EntityContainer) {
                $propertySchema = $this->getRefSchema($app, $controller, $action, $route, $property->container);
            } elseif ($property->container instanceof ArrayContainer) {
                $propertySchema = $this->getArraySchema($app, $controller, $action, $route, $property->container);
            } else {
                $propertySchema = new PrimitiveSchemaObject();
                $propertySchema->type = self::mapType($property->type);
                $propertySchema->description = self::implode("\n", [$property->summary, $property->description]);
                self::mapValidation($property->validation, $propertySchema);
                unset($propertySchema->required);
            }
            $schema->properties[$property->name] = $propertySchema;
        }

        return $schema;
    }

    public function hasFileParam(Route $route)
    {
        $params = $route->getRequestHandler()->getParamMetas();
        foreach ($params as $name => $param) {
            if(strpos($param->source, 'request.files.')===0){
                return true;
            }
        }
        return false;
    }
    /**
     * @param string $v
     * @param PrimitiveSchemaObject $schemaObject
     * @return PrimitiveSchemaObject
     */
    static public function mapValidation($v, PrimitiveSchemaObject $schemaObject)
    {
        if(!$v){
            return $schemaObject;
        }
        $rules = explode('|', $v);
        foreach ($rules as $r) {
            $params = explode(':', trim($r));
            $rule = $params[0];
            $params = isset($params[1]) ? explode(',', $params[1]) : [];

            if ($rule == 'required') {
                $schemaObject->required = true;
            } elseif ($rule == 'in') {
                $schemaObject->enum = $params;
            } elseif ($rule == 'lengthBetween' && isset($params[0]) && isset($params[1])) {
                $schemaObject->minLength = intval($params[0]);
                $schemaObject->maxLength = intval($params[1]);
            } elseif ($rule == 'lengthMin'&& isset($params[0])) {
                $schemaObject->minLength = intval($params[0]);
            } elseif ($rule == 'lengthMax'&& isset($params[0])) {
                $schemaObject->maxLength = intval($params[0]);
            } elseif ($rule == 'min'&& isset($params[0])) {
                $schemaObject->minimum = floatval($params[0]);
            } elseif ($rule == 'max'&& isset($params[0])) {
                $schemaObject->maximum = floatval($params[0]);
            } elseif ($rule == 'regex'&& isset($params[0])) {
                $schemaObject->pattern = $params[0];
            } elseif ($rule == 'optional') {
                $schemaObject->required = false;
            }
        }
        return $schemaObject;
    }

    /**
     * @param string $type
     * @return string
     */
    static public function mapType($type)
    {
        //TODO 如何处理 file、mixed 类型
        $map = [
            'int' => 'integer',
            'bool' => 'boolean',
            'float' => 'number',
            'mixed' => null,
        ];
        if (array_key_exists($type, $map)) {
            return $map[$type];
        }
        return $type;
    }

    /**
     * @param $className
     * @return string
     */
    static public function getShortClassName($className)
    {
        $className = explode('\\', $className);
        $className = $className[count($className) - 1];
        return $className;
    }

    static public function implode($glue , array $pieces )
    {
        $pieces = array_filter($pieces, function($i){return trim($i) !== '';});
        return implode($glue, $pieces);
    }
}