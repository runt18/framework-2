<?php
namespace Selene\Matisse;
use Selene\Matisse\Exceptions\ComponentException;

class AttributeType
{
  /** Alphanumeric identifier. */
  const ID = 1;
  /** Plain text. */
  const TEXT = 2;
  /** Number */
  const NUM = 3;
  /** Boolean (1/0, yes/no, on/off, true/false). */
  const BOOL = 4;
  /** Parameter list. This attribute type is an array of Parameters. */
  const PARAMS = 5;
  /** Source code. This attribute type is a parameter with child components. */
  const SRC = 6;
  /** Data source. This attribute type is a DataSource object. */
  const DATA = 7;
  /** Binding expression. This attribute is a string.
   * Do not define attributes/parameters of this type. It is used only on template instances when binding expreesions
   * are specified for template parameters instead of constant values. */
  const BINDING = 8;
}

class ComponentAttributes
{

  /**
   * An array of names for each attribute data type.
   * Its public to allow access from the Template class.
   * @var array
   */
  public static $TYPE_NAMES = [
    'undefined', 'identifier', 'text', 'number', 'boolean', 'parameters', 'source', 'data', 'binding'
  ];
  protected static $BOOLEAN_VALUES = [
    0       => false,
    1       => true,
    'false' => false,
    'true'  => true,
    'no'    => false,
    'yes'   => true,
    'off'   => false,
    'on'    => true
  ];
  public $id;
  public $styles;
  public $class;
  public $disabled   = false;
  public $html_attrs = '';
  /**
   * The component that owns these attributes.
   * @var Component
   */
  protected $component;
  public function __construct ($component)
  {
    $this->component = $component;
  }
  public static function getBoolean ($mixed)
  {
    if (is_bool ($mixed))
      return $mixed;
    if (is_string ($mixed) && array_key_exists ($mixed, self::$BOOLEAN_VALUES))
      return self::$BOOLEAN_VALUES[$mixed];
    return !is_null ($mixed) && !empty($mixed);
  }
  public static function validateScalar ($type, $v)
  {
    if (isset($v) && $v !== '') {
      switch ($type) {
        case AttributeType::BOOL:
          return self::getBoolean ($v);
        //throw new InvalidArgumentException("<b>$v</b> (PHP type ".gettype($v).") is not a valid <b>boolean</b> value.");
        case AttributeType::ID:
          if (preg_match ('#^\w+$#', $v) === false)
            throw new \InvalidArgumentException("<b>$v</b> (PHP type " . gettype ($v) .
                                                ") is not a valid <b>identifier</b>.");
          return $v;
        case AttributeType::NUM:
          if (is_numeric ($v)) return intval ($v);
          throw new \InvalidArgumentException("<b>$v</b> (PHP type " . gettype ($v) .
                                              ") is not a valid <b>number</b>.");
        case AttributeType::TEXT:
          if (!is_scalar ($v))
            throw new \InvalidArgumentException("A value of PHP type <b>" . gettype ($v) .
                                                "</b> is not valid for a <b>text</b> attribute/parameter.");
          if (!is_string ($v))
            return $v; //for mixed value attributes
          $v = preg_replace ('#<br ?/?>$|<p>&nbsp;</p>#', '', $v);
          $v = preg_replace ('#&nbsp;</p>#', '</p>', $v);
          return $v;
        case AttributeType::DATA:
          if ($v instanceof DataSet)
            return $v;
          if (is_string ($v) && strpos ($v, '{') !== false)
            return $v;
          if (is_array ($v) || is_object ($v))
            return new DataSet($v);
          throw new \InvalidArgumentException((is_scalar ($v) ? "The value <b>$v</b>" : 'A value') .
                                              " of PHP type <b>" .
                                              gettype ($v) .
                                              "</b> is not valid for a <b>data</b> attribute/parameter.");
      }
      if (isset(self::$TYPE_NAMES[$type]))
        throw new \InvalidArgumentException("Invalid attempt to validate an attribute/parameter value of type <b>" .
                                            self::$TYPE_NAMES[$type] . "<b> with code $type.");
    }
    return null;
  }
  public static function getTypeIdOf ($typeName)
  {
    return array_search ($typeName, self::$TYPE_NAMES);
  }
  public function __get ($name)
  {
    throw new ComponentException($this->component, "Can't read non existing attribute <b>$name</b>.");
  }
  public function __set ($name, $value)
  {
    throw new ComponentException($this->component, "Can't set non existing attribute <b>$name</b>.");
  }
  public function get ($name, $default = null)
  {
    if (isset($this->$name))
      return $this->$name;
    return $default;
  }
  public function set ($name, $value)
  {
    if (!$this->defines ($name))
      throw new ComponentException($this->component, "Invalid attribute <b>$name</b> specified.");
    if ($this->isScalar ($name))
      $this->setScalar ($name, $value);
    else $this->$name = $value;
  }
  public function getTypeOf ($name)
  {
    $fn = "typeof_$name";
    if (method_exists ($this, $fn))
      return $this->$fn();
    return null;
  }
  public function getEnumOf ($name)
  {
    return $this->{"enum_$name"}();
  }
  public function getTypeNameOf ($name)
  {
    $t = $this->getTypeOf ($name);
    if (!is_null ($t))
      return self::$TYPE_NAMES[$t];
    return self::$TYPE_NAMES[0];
  }
  public function defines ($name)
  {
    return method_exists ($this, "typeof_$name");
  }
  public function isEnum ($name)
  {
    return method_exists ($this, "enum_$name");
  }
  public function isScalar ($name)
  {
    $type = $this->getTypeOf ($name);
    return $type == AttributeType::BOOL || $type == AttributeType::ID || $type == AttributeType::NUM ||
           $type == AttributeType::TEXT;
  }
  public function getAttributeNames ()
  {
    $r = new \ReflectionClass(get_class ($this));
    $a = $r->getProperties (\ReflectionProperty::IS_PUBLIC);
    $s = $r->getProperties (\ReflectionProperty::IS_STATIC);
    $p = array_diff ($a, $s);
    $r = [];
    foreach ($p as $prop)
      $r[] = $prop->name;
    return $r;
  }
  public function getAll ()
  {
    $r = new \ReflectionClass(get_class ($this));
    $a = $r->getProperties (\ReflectionProperty::IS_PUBLIC);
    $s = $r->getProperties (\ReflectionProperty::IS_STATIC);
    $p = array_diff ($a, $s);
    $r = [];
    foreach ($p as $prop)
      $r[$prop->name] = $this->{$prop->name};
    return $r;
  }
  public function getAttributesOfType ($type)
  {
    $result = [];
    $names  = $this->getAttributeNames ();
    if (isset($names))
      foreach ($names as $name)
        if ($this->getTypeOf ($name) == $type)
          $result[$name] = $this->get ($name);
    return $result;
  }
  public function getScalar ($name)
  {
    return self::validateScalar ($this->getTypeOf ($name), $this->get ($name));
  }
  public function setScalar ($name, $v)
  {
    if ($this->isEnum ($name)) {
      $enum = $this->getEnumOf ($name);
      if (array_search ($v, $enum) === false) {
        $list = implode ('</b>, <b>', $enum);
        throw new ComponentException($this->component,
          "Invalid value for attribute/parameter <b>$name</b>.\nExpected: <b>$list</b>.");
      }
    }
    $this->$name = self::validateScalar ($this->getTypeOf ($name), $v);
  }
  public function setComponent (Component $owner)
  {
    $this->component = $owner;
    $attrs           = $this->getAttributesOfType (AttributeType::SRC);
    foreach ($attrs as $name => $value)
      if (!is_null ($value)) {
        /** @var Component $c */
        $c = clone $value;
        $c->attachTo ($owner);
        $this->$name = $c;
      }
    $attrs = $this->getAttributesOfType (AttributeType::PARAMS);
    foreach ($attrs as $name => $values)
      if (!empty($values))
        $this->$name = Component::cloneComponents ($values, $owner);
  }
  protected function typeof_html_attrs ()
  {
    return AttributeType::TEXT;
  }
  protected function typeof_id ()
  {
    return AttributeType::ID;
  }
  protected function typeof_styles ()
  {
    return AttributeType::TEXT;
  }
  protected function typeof_class ()
  {
    return AttributeType::ID;
  }
  protected function typeof_disabled ()
  {
    return AttributeType::BOOL;
  }

}
