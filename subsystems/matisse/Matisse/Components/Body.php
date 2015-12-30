<?php
namespace Selenia\Matisse\Components;

use Selenia\Matisse\Components\Base\Component;
use Selenia\Matisse\Properties\Base\GenericProperties;

/**
 * The Body component allows one to set the body tag's attributes and/or, optionally, specify the content that will
 * be wrapped by the `form` HTML element.
 */
class Body extends Component
{
  protected static $propertiesClass = GenericProperties::class;

  public $allowsChildren = true;
  /** @var GenericProperties */
  public $props;

  /**
   * Adds the content of the `content` parameter to the page's body element.
   * Adds the attributes defined via the html-attrs parameter to the page's body element.
   */
  protected function render ()
  {
    $html = $this->getContent ();

    if ($html != '')
      $this->page->bodyContent .= $html;

    $attrs = $this->props->getAll ();
    $a     = [];
    foreach ($attrs as $k => $v) {
      if (isset($v) && $v !== '' && is_scalar ($v))
        $a[$k] = $v;
    }
    $this->page->bodyAttrs = array_merge ($this->page->bodyAttrs, $a);
  }
}
