<?php
namespace Selenia\Navigation\Lib;

use PhpKit\Flow\Flow;
use Psr\Http\Message\ServerRequestInterface;
use Selenia\Exceptions\Fault;
use Selenia\Faults\Faults;
use Selenia\Interfaces\Navigation\NavigationLinkInterface;
use Selenia\Traits\InspectionTrait;

/**
 * TODO: optimize children list to be evaluated only on iteration.
 */
class NavigationLink implements NavigationLinkInterface
{
  use InspectionTrait;

  /**
   * Note: this will be assigned a reference to an array on a {@see NavigationInterface} instance.
   * @var NavigationLinkInterface[]
   */
  public $IDs;
  /**
   * Note: this is accessible to `Navigation`.
   * @var bool
   */
  public $group = false;
  /** @var bool */
  private $available;
  /** @var string|null When set, `url()` will always return its value. */
  private $cachedUrl = null;
  /** @var bool|callable */
  private $enabled = true;
  /** @var string */
  private $icon = '';
  /** @var string */
  private $id = '';
  /** @var NavigationLinkInterface[] */
  private $links = [];
  /** @var NavigationLinkInterface */
  private $parent;
  /** @var ServerRequestInterface */
  private $request;
  /** @var string|callable */
  private $title = '';
  /** @var string|callable|null When null, the value will be computed on demand */
  private $url = null;
  /** @var bool|callable */
  private $visible = true;
  /** @var bool */
  private $visibleIfUnavailable = false;

  /**
   * Checks if the given argument is a valid iterable value. If it's not, it throws a fault.
   * @param NavigationLinkInterface[]|\Traversable|callable $navMap
   * @return \Iterator
   * @throws Fault {@see Faults::ARG_NOT_ITERABLE}
   */
  static function validateNavMap ($navMap)
  {
    if (!is_iterable ($navMap))
      throw new Fault (Faults::ARG_NOT_ITERABLE);
  }

  function enabled ($enabled = null)
  {
    if (is_null ($enabled))
      return is_callable ($enabled = $this->enabled) ? $enabled() : $enabled;
    $this->enabled = $enabled;
    return $this;
  }

  public function getDescendants ()
  {
    return Flow::from ($this->links)->recursive (
      function (NavigationLinkInterface $link) { return $link->links (); }
    )->reindex ()->getIterator ();
  }

  public function getIterator ()
  {
    return Flow::from ($this->links)->reindex ()->getIterator ();
  }

  function getMenu ()
  {
    return Flow::from ($this->links)->where (
      function (NavigationLinkInterface $link) { return $link->isActuallyVisible (); }
    )->reindex ()->getIterator ();
  }

  function icon ($icon = null)
  {
    if (is_null ($icon)) return $this->icon;
    $this->icon = $icon;
    return $this;
  }

  function id ($id = null)
  {
    if (is_null ($id)) return $this->id;
    if (isset($this->IDs[$id]))
      throw new Fault (Faults::DUPLICATE_LINK_ID, $id);
    $this->id = $id;
    return $this->IDs[$id] = $this;
  }

  function isActuallyEnabled ()
  {
    $this->url (); // updates $this->available
    return $this->enabled () && $this->available;
  }

  function isActuallyVisible ()
  {
    $this->url (); // updates $this->available
    return $this->visible () && ($this->available || $this->visibleIfUnavailable);
  }

  function isGroup ()
  {
    return $this->group;
  }

  function links ($navigationMap = null)
  {
    if (is_null ($navigationMap)) return $this->links;
    $this->links = [];
    return $this->merge ($navigationMap);
  }

  function merge ($navigationMap)
  {
    self::validateNavMap ($navigationMap);
    /**
     * @var string                  $key
     * @var NavigationLinkInterface $link
     */
    foreach (iterator ($navigationMap) as $key => $link) {
      $this->links[$key] = $link->parent ($this); // assigns $link and sets its parent
      if (is_string ($key)) $link->url ($key);
    }
    return $this;
  }

  function parent (NavigationLinkInterface $parent = null)
  {
    if (is_null ($parent)) return $this->parent;
    $this->parent = $parent;
    return $this;
  }

  function rawUrl ()
  {
    if (isset($this->cachedUrl))
      return $this->cachedUrl;
    if (is_callable ($url = $this->url))
      $url = $url();
    if (isset($url) && $this->parent && !str_beginsWith ($url, 'http') && ($url === '' || $url[0] != '/')) {
      $base = $this->parent->url ();
      $url  = isset($base) && isset($url) ? ltrim ("$base/$url", '/') : null;
    }
    return $this->cachedUrl = $url;
  }

  function request (ServerRequestInterface $request = null)
  {
    if (is_null ($request))
      return $this->request ?: $this->request = ($this->parent ? $this->parent->request () : null);
    $this->request = $request;
    return $this;
  }

  function title ($title = null)
  {
    if (is_null ($title))
      return is_callable ($title = $this->title) ? $title() : $title;
    $this->title = $title;
    return $this;
  }

  function url ($url = null)
  {
    if (is_null ($url)) {
      $url = $this->rawUrl ();
      if (isset($url))
        $url = $this->evaluateUrl ($url);
      return $url;
    }
    //else DO NOT CACHE IT YET!
    $this->url = $url;
    return $this;
  }

  function visible ($visible = null)
  {
    if (is_null ($visible))
      return is_callable ($visible = $this->visible) ? $visible() : $visible;
    $this->visible = $visible;
    return $this;
  }

  function visibleIfUnavailable ($visible = null)
  {
    if (is_null ($visible)) return $this->visibleIfUnavailable;
    $this->visibleIfUnavailable = $visible;
    return $this;
  }

  private function evaluateUrl ($url)
  {
    $request         = $this->getRequest ();
    $this->available = true;
    $url             = preg_replace_callback ('/@\w+/', function ($m) use ($request) {
      $v = $request->getAttribute ($m[0]);
      if (is_null ($v)) {
        $this->available = false;
        return ''; //to preg_replace
      }
      return $v;
    }, $url);
    return $this->available ? $url : null;
  }

  /**
   * @return ServerRequestInterface
   * @throws Fault
   */
  private function getRequest ()
  {
    $request = $this->request ();
    if (!$request)
      throw new Fault (Faults::REQUEST_NOT_SET);
    return $request;
  }

}
