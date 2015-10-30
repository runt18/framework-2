<?php
namespace Selenia\FileServer\Services;

use Selenia\Application;
use Selenia\Traits\InspectionTrait;

class FileServerMappings
{
  use InspectionTrait;

  const ref = __CLASS__;

  static $INSPECTABLE = ['mountPoints'];
  /**
   * @var Application
   */
  private $app;
  /**
   * A map of mappings from virtual URIs to external folders.
   * <p>This is used to expose assets from composer packages.
   * <p>Array of URI => physical folder path
   * @var array
   */
  private $mountPoints = [];

  function __construct (Application $app)
  {
    $this->app = $app;
  }


  /**
   * Modules and other packages can call this method to expose internal assets and scripts on web.
   * @param string $URI
   * @param string $path
   */
  function map ($URI, $path)
  {
    $this->mountPoints[$URI] = $path;
  }

  function toFilePath ($URI, &$isMapped = false)
  {
    $p = strpos ($URI, '/');
    if ($p) {
      $head = substr ($URI, 0, $p);
      if ($head == 'modules') {
        $p    = strpos ($URI, '/', $p + 1);
        $p    = strpos ($URI, '/', $p + 1);
        $head = substr ($URI, 0, $p);
      }
      $tail = substr ($URI, $p + 1);
      if (isset($this->mountPoints[$head])) {
        if (func_num_args () == 2)
          $isMapped = true;
        $path = $this->mountPoints[$head] . "/$tail";
        return $path;
      }
    }
    return "{$this->app->baseDirectory}/$URI";
  }

}
