<?php
namespace Selenia\Localization;

use Selenia\Interfaces\AssignableInterface;
use Selenia\Traits\ConfigurationTrait;

/**
 * Configuration settings for the Localization module.
 *
 * @method $this selectionMode (string $mode) How to automatically set the current locale.  Either 'session' or 'url'.
 * @method string getSelectionMode ()
 */
class LocalizationConfig implements AssignableInterface
{
  use ConfigurationTrait;

  /**
   * @var string
   */
  private $selectionMode;

}
