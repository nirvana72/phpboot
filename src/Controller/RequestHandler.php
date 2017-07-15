<?php

namespace PhpBoot\Controller;

use PhpBoot\Metas\ParamMeta;
use PhpBoot\Utils\ArrayAdaptor;
use PhpBoot\Validator\Validator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class RequestHandler
{
    /**
     * ParamsBuilder constructor.
     * @param ParamMeta[] $paramMates
     */
    public function __construct(array $paramMates){
        $this->paramMetas = $paramMates;
    }

    /**
     * @param Request $request
     * @param array $params
     * @return void
     */
    public function handle(Request $request, array &$params){

        $vld = new Validator();
        $requestArray = new ArrayAdaptor($request);
        $inputs = [];
        foreach ($this->paramMetas as $k=>$meta){
            if($meta->isPassedByReference){
                // param PassedByReference is used to output
                continue;
            }
            $source = \JmesPath\search($meta->source, $requestArray);
            if ($source !== null){
                if($meta->builder){
                    $inputs[$meta->name] = $meta->builder->build($source);
                }else{
                    $inputs[$meta->name] = $source;
                }
                if($meta->validation){
                    $vld->rule($meta->validation, $meta->name);
                }
            }else{
                $meta->isOptional or fail(new BadRequestHttpException("param $source is required"));
                $inputs[$meta->name] = $meta->default;
            }
        }
        $vld->withData($inputs)->validate() or fail(
            new \InvalidArgumentException(
                json_encode(
                    $vld->errors(),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                )
            )
        );

        $pos = 0;
        foreach ($this->paramMetas as $meta){
            if($meta->isPassedByReference){
                $params[$pos] = null;
            }else{
                $params[$pos] = $inputs[$meta->name];
            }
            $pos++;

        }
    }

    public function getParamNames(){
        return array_map(function($meta){return $meta->name;}, $this->paramMetas);
    }

    /**
     * 获取参数列表
     * @return ParamMeta[]
     */
    public function getParamMetas(){
        return $this->paramMetas;
    }

    /**
     * 获取指定参数信息
     * @param $name
     * @return ParamMeta|null
     */
    public function getParamMeta($name){
        foreach ($this->paramMetas as $meta){
            if($meta->name == $name){
                return $meta;
            }
        }
        return null;
    }

    /**
     * @var ParamMeta[]
     */
    private $paramMetas = [];
}