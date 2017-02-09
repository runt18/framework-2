<?php

namespace Electro\Tasks\Commands;

use Electro\Caching\Config\CachingSettings;
use Electro\Configuration\Lib\IniFile;
use Electro\ConsoleApplication\ConsoleApplication;
use Electro\Interfaces\ConsoleIOInterface;
use Electro\Kernel\Config\KernelSettings;
use Electro\Kernel\Services\ModulesInstaller;
use Robo\Task\FileSystem\CleanDir;
use Robo\Task\FileSystem\FilesystemStack;

/**
 * Implements the Electro Task Runner's pre-set build commands.
 *
 * @property KernelSettings     $app
 * @property ConsoleIOInterface $io
 * @property FilesystemStack    $fs
 * @property CachingSettings    $cachingSettings
 * @property ConsoleApplication $consoleApp
 * @property ModulesInstaller   $modulesInstaller
 */
trait MiscCommands
{
  /**
   * Clear all cache contents
   *
   * @throws \Exception
   */
  function cacheClear ()
  {
    $target = $this->kernelSettings->storagePath . DIRECTORY_SEPARATOR . $this->cachingSettings->cachePath;
    (new CleanDir($target))->run ();
    if (!$this->nestedExec)
      $this->io->done ("Cache contents cleared");
  }

  /**
   * Disable the caching subsystem
   *
   * @throws \Exception
   */
  function cacheDisable ()
  {
    (new IniFile('.env'))->load ()->set ('CACHING', 'false')->save ();
    $this->io->writeln ("Caching: <info>disabled</info>");
  }

  /**
   * Enable the caching subsystem
   *
   * @throws \Exception
   */
  function cacheEnable ()
  {
    (new IniFile('.env'))->load ()->set ('CACHING', 'true')->save ();
    $this->io->writeln ("Caching: <info>enabled</info>");
  }

  /**
   * Check whether the caching subsystem is enabled or not
   *
   * @throws \Exception
   */
  function cacheStatus ()
  {
    $file = (new IniFile('.env'))->load ();
    $v    = strtolower ($file->get ('CACHING'));
    $v    = $v == 'true' ? 'enabled' : ($v == 'false' ? 'disabled' : 'not set');
    $this->io->writeln (sprintf ("Caching: <info>%s</info>", $v));
  }

  /**
   * Forces the reinstallation of all packages, clears all caches and reinitializes all modules
   */
  function rebuild ()
  {
    $this->initConfig (['overwrite' => true]);
    $this->cacheClear ();
    $this->clearDir ($this->app->packagesPath);
    $this->clearDir ($this->app->pluginsPath);
    $this->composerUpdate ();
    $this->modulesInstaller->rebuildRegistry ();
    $this->init();
  }

}