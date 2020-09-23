# Windwalker Attributes Resolver Package

This package provides a universal interface to manage (PHP8 Attributes)[https://stitcher.io/blog/attributes-in-php-8] ((RFC)[https://wiki.php.net/rfc/attributes_v2]) 
and help developers construct the attribute processors.

## Installation

This package is currently in Alpha, you must allow dev version in your composer settings.

```
composer require windwaker/attributes dev-master
``` 

## Getting Started

First, you must create your own Attributes. This is a simple example wrapper to wrap any object.

```php
use Windwalker\Attributes\AttributeHandler;
use Windwalker\Attributes\AttributeInterface;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Wrapper implements AttributeInterface
{
    public object $inner;

    public function __invoke(AttributeHandler $handler): callable
    {
        return function () use ($handler) {
            $this->inner = $handler();
            return $this;
        };
    }
}
```

In `__invoke()`, always return a callback, you can do what you want in this callback.

The `$handler()` will return the value which return by previous attribute handler. 
All callbacks will be added to a stack and run after all attributes processed. This is very similar 
to middleware handler.

Then, register this attribute to resolver.

```php
use Windwalker\Attributes\AttributesResolver;
use Windwalker\DI\Attributes\AttributeType;

$attributes = new AttributesResolver();
$attributes->registerAttribute(\Wrapper::class, AttributeType::CLASSES);

// Now, try to wrap an object.
            
#[\Wrapper] 
class Foo {
    
}

$foo = new \Foo();
$foo = $attributes->decorateObject($foo);

$foo instanceof \Wrapper;
$foo->inner instanceof \Foo;
```

## Available Types & Actions

Currently, there has 4 types, You can use `registerAttribute()` to control attribute working scope.

### Object & Classes 

```php
use Windwalker\Attributes\AttributesResolver;
use Windwalker\Attributes\AttributeType;

$attributes = new AttributesResolver();

// Work on Class and Object
$attributes->registerAttribute(\Decorator::class, AttributeType::CLASSES);

// Decorate existing object
$object = $attributes->decorateObject($object);

// Create object from class and decorate it.
$object = $attributes->createObject(\Foo::class, ...$args);
```

### Function, Method and any Callable

```php
use Windwalker\Attributes\AttributesResolver;
use Windwalker\Attributes\AttributeType;

$attributes = new AttributesResolver();

// Work on method, function, Closure or callable.
$attributes->registerAttribute(\Autowire::class, AttributeType::CALLABLE);

$result = $attributes->call($callable, ...$args);
```

### Properties

```php
use Windwalker\Attributes\AttributesResolver;
use Windwalker\Attributes\AttributeType;

$attributes = new AttributesResolver();

// Work on object properties
$attributes->registerAttribute(\Inject::class, AttributeType::PROPERTIES);

$object = new class {
    #[\Inject()]
    protected ?\Foo $foo = null;
};

$object = $attributes->resolveProperties($object);
```

### Parameters

```php
use Windwalker\Attributes\AttributesResolver;
use Windwalker\Attributes\AttributeType;

$attributes = new AttributesResolver();

// Work on callable parameters.
$attributes->registerAttribute(\StrUpper::class, AttributeType::PARAMETERS);
$func = function (
    #[\StrUpper]
    $foo    
) {
    return $foo;
};

$result = $attributes->call($func, ['flower'], /* $context to bind this */); // "FLOWER"
```

## Write Your Own Attribute Handler

### Object & Classes

This is a Decorator example:  

```php
use Windwalker\Attributes\AttributeHandler;
use Windwalker\Attributes\AttributeInterface;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Decorator implements AttributeInterface
{
    protected string $class;
    
    protected array $args = [];
    
    public function __construct(string $class, ...$args)
    {
        $this->class = $class;
        $this->args = $args;
    }

    public function __invoke(AttributeHandler $handler)
    {
        return fn (...$newInstanceArgs) => new ($this->class)($handler(...$newInstanceArgs), ...$this->args); 
    }
}
```

There are 2 methods can decorate object or class.

- `decorateObject(object $object): object`
- `createObject(string $class, ...$args): object`

If you call `decorateObject($object)`, the `$handler(<void>)` will only return object which you sent into.

And if you call `createObject($class, ...$args)`, the `$handler(...$args)` will create object 
by the class and pass `...$args` to constructor.

Then, use your own function wrap it, all handlers will be a callback stack and called after all attributes processed.

Example:

```php
use Windwalker\Attributes\AttributesResolver;
use Windwalker\Attributes\AttributeType;

#[\Decorator(\Component::class, ['template' => 'foo.php'])]
class Foo 
{
    //
}

$attributes = new AttributesResolver();

// Work on Class and Object
$attributes->registerAttribute(\Decorator::class, AttributeType::CLASSES);

// Decorate existing object
$component = $attributes->decorateObject($object);

// Create object from class and decorate it.
$component = $attributes->createObject(\Foo::class, ...$args);
```

### Use Custom Object Builder

If you want to integrate with some Container packages, please set custom object builder.

```php
$attributes->setBuilder(function (string $class, ...$args) use ($container) {
    return $container->createObject($class, ...$args);
});
```

> TODO: Support custom call() handler.

### Callable

An example to control HTTP allow methods and Json Response.

```php
use Windwalker\Attributes\AttributeHandler;
use Windwalker\Attributes\AttributeInterface;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
class Method implements AttributeInterface
{
    protected array $allows = [];
    
    public function __construct(string|array $allows = [])
    {
        $this->allows = array_map('strtoupper', (array) $allows);
    }

    public function __invoke(AttributeHandler $handler)
    {
        return function ($request, $reqHandler) use ($handler) {
            if (!in_array($request->getMethod(), $this->allows, true)) {
                throw new \RuntimeException('Invalid Method', 405);
            }
            // You can change parameters here.
    
            $res = $handler($request, $reqHandler);

            // You can also modify return value.
            return $res;
        }; 
    }
}
```

```php
use Windwalker\Attributes\AttributeHandler;
use Windwalker\Attributes\AttributeInterface;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
class Json implements AttributeInterface
{
    public function __invoke(AttributeHandler $handler)
    {
        return function ($request, $reqHandler) use ($handler) {
            $res = $handler($request, $reqHandler);
            $res = $res->withHeader('Content-Type', 'application/json');
            return $res;
        }; 
    }
}
```

The `$handler(...$args)` in callable attributes is to call the target callable, we can change/validate parameters 
or modify the return value.

Usage:

```php
use Windwalker\Attributes\AttributesResolver;
use Windwalker\Attributes\AttributeType;

class Controller 
{
    #[\Method('GET')]
    #[\Json]
    public function index()
    {
        return new Response();
    }
}

$attributes = new AttributesResolver();

$attributes->registerAttribute(\Method::class, AttributeType::CALLABLE);
$attributes->registerAttribute(\Json::class, AttributeType::CALLABLE);

// Call
$jsonResponse = $attributes->call(
    [new \Controller(), 'index'], // Callable 
    [$request, 'handler' => $reaHandler], // Args should be array, support php8 named arguments
    [?object $context = null] // Context is an object wll bind as this for the callable, default is NULL. 
);
```

### Parameters

An example to handler parameters to upper case

```php
use Windwalker\Attributes\AttributeHandler;
use Windwalker\Attributes\AttributeInterface;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Upper implements AttributeInterface
{
    public function __invoke(AttributeHandler $handler)
    {
        return fn () => strtoupper((string) $handler());
    }
}
```

The `$handler()` in parameter attributes is to simply get parameter values, you can modify this value and return it.

```php
use Windwalker\Attributes\AttributesResolver;
use Windwalker\Attributes\AttributeType;

class Http 
{
    public static function request(
        #[\Upper]
        string $method,
        mixed $data = null,
        array $options = []
    ) {
        // $method should always upper case.
    }
}

$attributes = new AttributesResolver();

$attributes->registerAttribute(\Upper::class, AttributeType::PARAMETERS);

// Decorate existing object
$jsonResponse = $attributes->call([\Http::class, 'request'], ['POST', 'foo=bar']);
```

### Properties

This is an example to handle all properties of an object.

```php
use Windwalker\Attributes\AttributeHandler;
use Windwalker\Attributes\AttributeInterface;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Wrapper implements AttributeInterface
{
    public function __invoke(AttributeHandler $handler)
    {
        /** @var $ref ReflectionProperty */
        $ref = $handler->getReflector();

        // Since php8 supports union type, we should get first exists class type as possible type.
        $type = ReflectionHelper::getFirstExistsClassType($ref);
        $class = $type->getName();

        return fn () => new $class($handler());
    }
}
```

The `$handler()` in properties attributes is to simply get property values, you can modify this value and return it.
No matter these properties are public or protected, AttributesResolver will force set value into it.

Usage:

```php
use Windwalker\Attributes\AttributesResolver;
use Windwalker\Attributes\AttributeType;

$object = new class {
    #[\Wrapper]
    protected ?Collection $options = null;
};

$attributes = new AttributesResolver();

$attributes->registerAttribute(\Wrapper::class, AttributeType::PROPERTIES);

$object = $attributes->resolveProperties($object);
```

## About `AttributeHandler`

`AttributeHandler` is the only parameter of our attribute processor.

```php
use Windwalker\Attributes\AttributeHandler;
use Windwalker\Attributes\AttributeInterface;

#[\Attribute]
class MyAttribute implements AttributeInterface
{
    public function __invoke(AttributeHandler $handler)
    {
        /** 
         * $ref can be:
         * @see \ReflectionObject for classes type 
         * @see \ReflectionClass  for classes type
         * @see \ReflectionFunctionAbstract for callable type
         * @see \ReflectionParameter for parameters type
         * @see \ReflectionProperty for properties type
         */
        $ref = $handler->getReflector();
      
        // The AttributesResolver object
        $resolver = $handler->getReflector(); 

        // Get previous result
        $result = $handler(...);
    }
}
```

## Integrate to Any Objects

You can create AttributesResolver in some object to help this object handle attributes, here we use EventDispatcher as example:

```php
use Windwalker\Attributes\AttributesAwareTrait;use Windwalker\Attributes\AttributesResolver;

class EventDispatcher 
{
    use AttributesAwareTrait;

    public function __construct()
    {
        $this->prepareAttributes($this->getAttributesResolver());
    }

    protected function prepareAttributes(AttributesResolver $resolver)
    {
        $resolver->registerAttribute(\ListenerTo::class, );
    }
}
```



