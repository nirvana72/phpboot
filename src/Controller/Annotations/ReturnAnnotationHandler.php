<?php

namespace PhpBoot\Controller\Annotations;


use PhpBoot\Annotation\AnnotationBlock;
use PhpBoot\Annotation\AnnotationTag;
use PhpBoot\Controller\ControllerContainer;
use PhpBoot\Entity\ContainerFactory;

class ReturnAnnotationHandler
{
    /**
     * @param ControllerContainer $container
     * @param AnnotationBlock|AnnotationTag $ann
     */
    public function __invoke(ControllerContainer $container, $ann)
    {
        $target = $ann->parent->name;
        $route = $container->getRoute($target);
        $route->setReturnString($ann->description);
    }
}