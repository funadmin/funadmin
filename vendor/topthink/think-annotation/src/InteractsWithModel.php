<?php

namespace think\annotation;

use ReflectionClass;
use ReflectionMethod;
use think\annotation\model\option\Append;
use think\annotation\model\option\CreateTime;
use think\annotation\model\option\Hidden;
use think\annotation\model\option\Mapping;
use think\annotation\model\option\Pk;
use think\annotation\model\option\Suffix;
use think\annotation\model\option\Type;
use think\annotation\model\option\UpdateTime;
use think\annotation\model\option\Visible;
use think\annotation\model\Relation;
use think\annotation\model\relation\BelongsTo;
use think\annotation\model\relation\BelongsToMany;
use think\annotation\model\relation\HasMany;
use think\annotation\model\relation\HasManyThrough;
use think\annotation\model\relation\HasOne;
use think\annotation\model\relation\HasOneThrough;
use think\annotation\model\relation\MorphByMany;
use think\annotation\model\relation\MorphMany;
use think\annotation\model\relation\MorphOne;
use think\annotation\model\relation\MorphTo;
use think\annotation\model\relation\MorphToMany;
use think\App;
use think\helper\Str;
use think\ide\ModelGenerator;
use think\Model;
use think\model\Collection;

/**
 * Trait InteractsWithModel
 * @package think\annotation
 *
 * @property App $app
 * @mixin Model
 */
trait InteractsWithModel
{
    protected array $detected = [];

    protected function detectModelAnnotations()
    {
        if ($this->app->config->get('annotation.model.enable', true)) {

            Model::maker(function (Model $model) {
                $className = get_class($model);
                if (!isset($this->detected[$className])) {
                    $annotations = $this->reader->getAnnotations(new ReflectionClass($model), Relation::class);

                    foreach ($annotations as $annotation) {

                        $relation = function () use ($annotation) {

                            $refMethod = new ReflectionMethod($this, Str::camel(class_basename($annotation)));

                            $args = [];
                            foreach ($refMethod->getParameters() as $param) {
                                $args[] = $annotation->{$param->getName()};
                            }

                            return $refMethod->invokeArgs($this, $args);
                        };

                        call_user_func([$model, 'macro'], $annotation->name, $relation);
                    }

                    $options = [];
                    $refClass = new ReflectionClass($model);

                    // Handle annotations with a common method
                    $this->processModelAnnotation($refClass, $options, Append::class, 'append', 'append');
                    $this->processModelAnnotation($refClass, $options, CreateTime::class, 'create_time', 'name');
                    $this->processModelAnnotation($refClass, $options, Hidden::class, 'hidden', 'hidden');
                    $this->processModelAnnotation($refClass, $options, Pk::class, 'pk', 'name');
                    $this->processModelAnnotation($refClass, $options, Suffix::class, 'suffix', 'suffix');
                    $this->processModelAnnotation($refClass, $options, UpdateTime::class, 'update_time', 'name');
                    $this->processModelAnnotation($refClass, $options, Visible::class, 'visible', 'visible');

                    // Handle repeatable annotations with a common method
                    $this->processRepeatableAnnotation($refClass, $options, Type::class, 'type', 'name', 'type');
                    $this->processRepeatableAnnotation($refClass, $options, Mapping::class, 'mapping', 'field', 'name');

                    $this->detected[$className] = [
                        'options' => $options,
                    ];
                } else {
                    $options = $this->detected[$className]['options'];
                }

                //options
                $model->setOptions($options);
            });

            $this->app->event->listen(ModelGenerator::class, function (ModelGenerator $generator) {

                $annotations = $this->reader->getAnnotations($generator->getReflection(), Relation::class);

                foreach ($annotations as $annotation) {
                    $property = Str::snake($annotation->name);
                    switch (true) {
                        case $annotation instanceof HasOne:
                            $generator->addMethod($annotation->name, \think\model\relation\HasOne::class, [], '');
                            $generator->addProperty($property, $annotation->model, true);
                            break;
                        case $annotation instanceof BelongsTo:
                            $generator->addMethod($annotation->name, \think\model\relation\BelongsTo::class, [], '');
                            $generator->addProperty($property, $annotation->model, true);
                            break;
                        case $annotation instanceof HasMany:
                            $generator->addMethod($annotation->name, \think\model\relation\HasMany::class, [], '');
                            $generator->addProperty($property, $annotation->model . '[]', true);
                            break;
                        case $annotation instanceof HasManyThrough:
                            $generator->addMethod($annotation->name, \think\model\relation\HasManyThrough::class, [], '');
                            $generator->addProperty($property, $annotation->model . '[]', true);
                            break;
                        case $annotation instanceof HasOneThrough:
                            $generator->addMethod($annotation->name, \think\model\relation\HasOneThrough::class, [], '');
                            $generator->addProperty($property, $annotation->model, true);
                            break;
                        case $annotation instanceof BelongsToMany:
                            $generator->addMethod($annotation->name, \think\model\relation\BelongsToMany::class, [], '');
                            $generator->addProperty($property, $annotation->model . '[]', true);
                            break;
                        case $annotation instanceof MorphOne:
                            $generator->addMethod($annotation->name, \think\model\relation\MorphOne::class, [], '');
                            $generator->addProperty($property, $annotation->model, true);
                            break;
                        case $annotation instanceof MorphMany:
                            $generator->addMethod($annotation->name, \think\model\relation\MorphMany::class, [], '');
                            $generator->addProperty($property, 'mixed', true);
                            break;
                        case $annotation instanceof MorphTo:
                            $generator->addMethod($annotation->name, \think\model\relation\MorphTo::class, [], '');
                            $generator->addProperty($property, 'mixed', true);
                            break;
                        case $annotation instanceof MorphToMany:
                        case $annotation instanceof MorphByMany:
                            $generator->addMethod($annotation->name, \think\model\relation\MorphToMany::class, [], '');
                            $generator->addProperty($property, Collection::class, true);
                            break;
                    }
                }
            });
        }
    }

    /**
     * Process a model annotation and add it to the options array
     *
     * @param ReflectionClass $refClass The reflection class of the model
     * @param array &$options The options array to modify
     * @param string $annotationClass The annotation class to look for
     * @param string $optionKey The key to use in the options array
     * @param string $propertyName The property name to get from the annotation
     */
    protected function processModelAnnotation(ReflectionClass $refClass, array &$options, string $annotationClass, string $optionKey, string $propertyName): void
    {
        if ($annotation = $this->reader->getAnnotation($refClass, $annotationClass)) {
            $options[$optionKey] = $annotation->{$propertyName};
        }
    }

    /**
     * Process repeatable model annotations and add them to the options array
     *
     * @param ReflectionClass $refClass The reflection class of the model
     * @param array &$options The options array to modify
     * @param string $annotationClass The annotation class to look for
     * @param string $optionKey The key to use in the options array
     * @param string $keyProperty The property name to use as key in the result array
     * @param string $valueProperty The property name to use as value in the result array
     */
    protected function processRepeatableAnnotation(ReflectionClass $refClass, array &$options, string $annotationClass, string $optionKey, string $keyProperty, string $valueProperty): void
    {
        $annotations = $this->reader->getAnnotations($refClass, $annotationClass);
        if (!empty($annotations)) {
            $options[$optionKey] = [];
            foreach ($annotations as $annotation) {
                $options[$optionKey][$annotation->{$keyProperty}] = $annotation->{$valueProperty};
            }
        }
    }
}
