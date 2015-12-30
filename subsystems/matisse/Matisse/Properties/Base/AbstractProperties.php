<?php
namespace Selenia\Matisse\Properties\Base;

use Selenia\Matisse\Components\Base\Component;
use Selenia\Matisse\Exceptions\ComponentException;
use Selenia\Matisse\Interfaces\ComponentPropertiesInterface;
use Selenia\Matisse\Properties\TypeSystem\type;

abstract class AbstractProperties implements ComponentPropertiesInterface
{
  /**
   * The component that owns these properties.
   *
   * @var Component
   */
  protected $component;

  function __construct (Component $ownerComponent)
  {
    $this->component = $ownerComponent;
  }

  /**
   * Checks if the component supports setting/getting a specific attribute.
   *
   * @param string $propName
   * @param bool   $asSubtag When true, the attribute MUST be able to be specified in subtag form.
   *                         When false, the attribute can be either a tag attribute or a subtag.
   * @return bool
   */
  abstract function defines ($propName, $asSubtag = false);

  /**
   * Returns all property values, indexed by property name.
   *
   * @return array A map of property name => property value.
   */
  abstract function getAll ();

  /**
   * @param string $propName Property name.
   * @return array Always returns an array, even if no enumeration is defined for the target property.
   * @throws \Selenia\Matisse\Exceptions\ReflectionPropertyException
   */
  abstract function getEnumOf ($propName);

  /**
   * Returns all declared property names.
   *
   * @return string[]
   */
  abstract function getPropertyNames ();

  /**
   * Returns the type ID of a property's secondary type (usually, a collection item type).
   *
   * @param string $propName
   * @return string
   */
  abstract function getRelatedTypeOf ($propName);

  /**
   * Returns the type ID of a property.
   *
   * @param string $propName
   * @return string
   */
  abstract function getTypeOf ($propName);

  /**
   * Checks if a property type is restricted to a set of allowed values.
   *
   * @param string $propName
   * @return bool
   */
  abstract function isEnum ($propName);

  function apply (array $props)
  {
    foreach ($props as $k => $v)
      $this->set ($k, $v);
  }

  /**
   * Checks if a property can be specified on markup as a subtag.
   *
   * @param string $propName
   * @return bool
   */
  function canBeSubtag ($propName)
  {
    if ($this->defines ($propName)) {
      $type = $this->getTypeOf ($propName);
      switch ($type) {
        case type::content:
        case type::collection:
        case type::metadata:
        case type::string:
          return true;
      }
    }
    return false;
  }

  function get ($propName, $default = null)
  {
    return property ($this, $propName, $default);
  }

  /**
   * Returns a subset of the available properties, filtered by the a specific type ID.
   *
   * @param string $type One of the {@see type}::XXX constants.
   * @return array A map of property name => property value.
   */
  function getPropertiesOf ($type)
  {
    $result = [];
    $names  = $this->getPropertyNames ();
    if (isset($names))
      foreach ($names as $name)
        if ($this->getTypeOf ($name) == $type)
          $result[$name] = $this->get ($name);
    return $result;
  }

  /**
   * Returns the type name of a property's secondary type (usually, a collection item type).
   *
   * @param string $propName
   * @return false|string
   */
  function getRelatedTypeNameOf ($propName)
  {
    $id = $this->getRelatedTypeOf ($propName);
    return type::getNameOf ($id);
  }

  /**
   * Returns the type name of a property.
   *
   * @param string $propName
   * @return false|string
   */
  function getTypeNameOf ($propName)
  {
    $id = $this->getTypeOf ($propName);
    return type::getNameOf ($id);
  }

  /**
   * Checks if a property is of a scalar type.
   *
   * @param string $propName
   * @return bool
   */
  function isScalar ($propName)
  {
    $type = $this->getTypeOf ($propName);
    return $type == type::bool || $type == type::id || $type == type::number ||
           $type == type::string;
  }

  /**
   * Validates, typecasts and assigns a value to a property.
   *
   * @param string $propName
   * @param mixed  $value
   */
  function set ($propName, $value)
  {
    $this->ensurePropertyExists ($propName);
    $this->setPropertyValue ($propName, $value);
  }

  /**
   * Assign a new owner to the properties object. This will also do a deep clone of the component's properties.
   *
   * @param Component $owner
   */
  function setComponent (Component $owner)
  {
    $this->component = $owner;
    $props           = $this->getPropertiesOf (type::content);
    foreach ($props as $name => $value)
      if (!is_null ($value)) {
        /** @var Component $c */
        $c = clone $value;
        $c->attachTo ($owner);
        $this->$name = $c;
      }
    $props = $this->getPropertiesOf (type::collection);
    foreach ($props as $name => $values)
      if (!empty($values))
        $this->$name = Component::cloneComponents ($values, $owner);
  }

  /**
   * Returns the value converted to a the data type required by the specified property.
   *
   * @param string $name
   * @param mixed  $v
   * @return bool|float|int|null|string|\Traversable
   * @throws ComponentException
   */
  function typecastPropertyValue ($name, $v)
  {
    $type = $this->getTypeOf ($name);
    if (!type::validate ($type, $v))
      throw new ComponentException ($this->component,
        sprintf (
          "%s is not a valid value for a component property of type <b>%s</b>",
          is_scalar ($v)
            ? sprintf ("<kbd>%s</kbd>", var_export ($v, true))
            : sprintf ("A value of PHP type <b>%s</b>", typeOf ($v)),
          type::getNameOf ($type)
        ));

    return type::typecast ($type, $v);
  }

  /**
   * Throws an exception if the specified property is not available.
   *
   * @param $propName
   * @throws ComponentException
   */
  protected function ensurePropertyExists ($propName)
  {
    if (!$this->defines ($propName)) {
      throw new ComponentException(
        $this->component,
        sprintf ("Invalid property <kbd>%s</kbd> specified for a %s instance.", $propName, typeInfoOf ($this))
      );
    }
  }

  /**
   * Called whenever a property's value changes.
   *
   * @param string $propName
   */
  protected function onPropertyChange ($propName)
  {
    // noop
  }

  /**
   * Sets a property's value, typecasting that value and/or transforming it as necessary.
   *
   * <p>This is meant to be called by subclasses' property setters.
   *
   * @param string $propName
   * @param mixed  $v
   * @throws ComponentException
   */
  protected function setPropertyValue ($propName, $v)
  {
    if ($this->isScalar ($propName) && $this->isEnum ($propName))
      $this->validateEnum ($propName, $v);

    $newV = $this->typecastPropertyValue ($propName, $v);

    if ($this->$propName !== $newV) {
      $this->$propName = $newV;
      $this->onPropertyChange ($propName);
    }
  }

  /**
   * Throws an exception if the the specified value is not valid for the given enumerated property.
   *
   * @param string $name
   * @param mixed  $v
   * @throws ComponentException
   */
  protected function validateEnum ($name, $v)
  {
    $enum = $this->getEnumOf ($name);
    if (array_search ($v, $enum) === false) {
      $list = implode ('</b>, <b>', $enum);
      throw new ComponentException ($this->component,
        "Invalid value for attribute/parameter <b>$name</b>.\nExpected: <b>$list</b>.");
    }
  }

}