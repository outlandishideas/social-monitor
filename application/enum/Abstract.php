<?php
/**
 * @link    http://github.com/myclabs/php-enum
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

/**
 * Base Enum class
 *
 * Create an enum by implementing this class and adding class constants.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 */
abstract class Enum_Abstract
{
    /**
     * Enum value
     * @var mixed
     */
    protected $value;

    /**
     * Store existing constants in a static cache per object.
     * @var array
     */
    private static $constantsCache = array();

    /**
     * Store for enum objects
     * @var array
     */
    private static $enumCache = array();

    /**
     * Creates a new value of some type
     * @param mixed $value
     * @throws \UnexpectedValueException if incompatible type is given.
     */
    protected function __construct($value)
    {
        $possibleValues = self::enumMap();
        if (! in_array($value, $possibleValues)) {
            throw new \UnexpectedValueException("Value '$value' is not part of the enum " . get_called_class());
        }
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->value;
    }

    /**
     * Returns all possible values as an array
     * @return array Constant name in key, constant value in value
     */
    public static function enumMap()
    {
        $calledClass = get_called_class();
        if(!array_key_exists($calledClass, self::$constantsCache)) {
            $reflection = new \ReflectionClass($calledClass);
            self::$constantsCache[$calledClass] = $reflection->getConstants();
        }
        return self::$constantsCache[$calledClass];
    }

    /**
     * @return Enum_PresenceType[]
     */
    public static function enumValues()
    {
        $values = array();
        foreach (static::enumMap() as $type) {
            $values[] = static::get($type);
        }
        return $values;
    }

    /**
     * Ensures that each enum is only created once
     * @param $name
     * @return mixed
     */
    public static function get($name) {
        $class = get_called_class();
        $key = $class . '_' . $name;
        if (!isset(self::$enumCache[$key])) {
            $enum = new static($name);
            self::$enumCache[$key] = $enum;
        }
        return self::$enumCache[$key];
    }

    /**
     * Returns a value when called statically like so: MyEnum::SOME_VALUE() given SOME_VALUE is a class constant
     * @deprecated
     * @param string $name
     * @param array  $arguments
     * @return static
     * @throws \BadMethodCallException
     */
    public static function __callStatic($name, $arguments)
    {
        if (defined("static::$name")) {
            return self::get(constant("static::$name"));
        }
        throw new \BadMethodCallException("No static method or enum constant '$name' in class " . get_called_class());
    }
}