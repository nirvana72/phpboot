<?php
namespace PhpBoot\Annotation\Entity\Annotations;

use PhpBoot\Annotation\Entity\EntityAnnotationHandler;
use PhpBoot\Metas\PropertyMeta;

class PropertyAnnotationHandler extends EntityAnnotationHandler
{

    public function handle($block)
    {
        $meta = $this->builder->getProperty($block->name);
        if(!$meta){
            $meta = new PropertyMeta($block->name);
            $this->builder->setProperty($block->name, $meta);
        }
        $meta->description = $block->description;
        $meta->summary = $block->summary;
    }
}