<?php
namespace Selene\Commands;
use Robo\Task\File\Replace;
use Robo\Task\FileSystem\CopyDir;
use Selene\Traits\CommandAPIInterface;

/**
 * Implements the Selene Task Runner's pre-set build commands.
 */
trait CreateCommands
{
  use CommandAPIInterface;

  /**
   * Scaffolds a new module for your application
   * @param string $moduleName The full name (vendor-name/module-name) of the module to be created
   */
  function createModule ($moduleName = null)
  {
    $___MODULE___ = $moduleName ?: $this->askDefault ("Module name", "vendor-name/module-name");
    $o        = explode ('/', $___MODULE___);
    if (count ($o) != 2)
      $this->error ("Invalid module name");
    list ($vendor, $module) = $o;
    $___NAMESPACE___ = ucfirst (dehyphenate ($vendor));
    $___CLASS___     = ucfirst (dehyphenate ($module));
    if (!$moduleName) {
      $___NAMESPACE___ = $this->askDefault ("PHP namespace for the module's classes", $___NAMESPACE___);
      $___CLASS___     = $this->askDefault ("Name of the class that represents the module:", $___CLASS___);
    }

    $path = "{$this->app()->modulesPath}/$___MODULE___";
    if (file_exists ($path) || file_exists ("{$this->app()->defaultModulesPath}/$___MODULE___"))
      $this->error ("Module $___MODULE___ already exists");

    (new CopyDir (["{$this->app()->scaffoldsPath}/module" => $path]))->run ();
    $this->fs()->rename("$path/src/Config/___CLASS___.php", "$path/src/Config/$___CLASS___.php")->run();

    $from = [
      '___MODULE___',
      '___CLASS___',
      '___NAMESPACE___'
    ];
    $to = [
      $___MODULE___,
      $___CLASS___,
      $___NAMESPACE___
    ];

    (new Replace ("$path/src/Config/$___CLASS___.php"))->from ($from)->to ($to)->run ();
    (new Replace ("$path/bootstrap.php"))->from ($from)->to ($to)->run ();

    $this->done ("Module <info>$___MODULE___</info> created");

    /** @var ModuleCommands $self */
    $self = $this;
    $self->moduleRegister($___MODULE___);
  }

}