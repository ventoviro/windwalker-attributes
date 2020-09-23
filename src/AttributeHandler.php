<?php

/**
 * Part of Windwalker project.
 *
 * @copyright  Copyright (C) 2019 LYRASOFT.
 * @license    MIT
 */

declare(strict_types=1);

namespace Windwalker\Attributes;

/**
 * The AttributeHandler class.
 */
class AttributeHandler
{
    /**
     * @var callable
     */
    public $handler;

    public \Reflector $reflector;

    public AttributesResolver $resolver;

    /**
     * AttributeHandler constructor.
     *
     * @param  callable            $handler
     * @param  \Reflector          $reflactor
     * @param  AttributesResolver  $resolver
     */
    public function __construct(callable $handler, \Reflector $reflactor, AttributesResolver $resolver)
    {
        $this->set($handler);
        $this->reflector = $reflactor;
        $this->resolver  = $resolver;
    }

    public function __invoke(&...$args)
    {
        // try {
            return ($this->handler)(...$args);
        // } catch (\Throwable $e) {
        //     show($this->handler, $this->reflactor);
        //     exit(' @Checkpoint');
        // }
    }

    public function set(callable $handler)
    {
        $this->handler = $handler;

        return $this;
    }

    public function get(): callable
    {
        return $this->handler;
    }

    /**
     * @return \Reflector
     */
    public function getReflector(): \Reflector
    {
        return $this->reflector;
    }

    /**
     * @return AttributesResolver
     */
    public function getResolver(): AttributesResolver
    {
        return $this->resolver;
    }
}
