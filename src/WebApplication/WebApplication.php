<?php
namespace Electro\WebApplication;

use Electro\Interfaces\DI\InjectorInterface;
use Electro\Interfaces\ProfileInterface;
use Electro\Kernel\Config\KernelSettings;
use Electro\Kernel\Services\Bootstrapper;
use Electro\Kernel\Services\ModulesRegistry;
use PhpKit\WebConsole\DebugConsole\DebugConsole;
use PhpKit\WebConsole\DebugConsole\DebugConsoleSettings;
use PhpKit\WebConsole\ErrorConsole\ErrorConsole;
use const Electro\Kernel\Services\CONFIGURE;

/**
 * Provides the standard bootstrap procedure for web applications.
 *
 * - Sets up all framework services required for HTTP request handling.
 * - Transfers execution to the web-server subsystem.
 */
class WebApplication
{
  /**
   * @var KernelSettings
   */
  private $kernelSettings;
  /**
   * @var bool
   */
  private $debugMode;
  /**
   * @var InjectorInterface
   */
  private $injector;
  /**
   * @var WebServer
   */
  private $webServer;

  /**
   * @param InjectorInterface $injector     Provide your favorite dependency injector.
   * @param string            $profileClass The configuration profile's fully qualified class name.
   */
  function __construct (InjectorInterface $injector, $profileClass)
  {
    $this->injector = $injector;
    $injector
      ->share ($injector)
      ->alias (InjectorInterface::class, get_class ($injector))
      ->alias (ProfileInterface::class, $profileClass);
  }

  /**
   * Last resort error handler.
   * <p>It is only activated if an error occurs outside of the HTTP handling pipeline.
   *
   * @param \Exception|\Error $e
   */
  function exceptionHandler ($e)
  {
//    if ($this->logger)
//      $this->logger->error ($e->getMessage (),
//        ['stackTrace' => str_replace ("{$this->kernelSettings->baseDirectory}/", '', $e->getTraceAsString ())]);
    DebugConsole::outputContent (true);
  }

  /**
   * Bootstraps the application.
   *
   * @param string $rootDir  The application's root directory path.
   * @param int    $urlDepth How many URL segments should be stripped when calculating the application's root URL.
   */
  function run ($rootDir, $urlDepth = 0)
  {
    // On some web servers, the current directory may not be the application's root directory, so fix it.
    chdir ($rootDir);
    $rootDir = normalizePath ($rootDir);

    /** @var KernelSettings $kernelSettings */
    $kernelSettings = $this->kernelSettings = $this->injector
      ->share (KernelSettings::class, 'app')
      ->make (KernelSettings::class);

    $kernelSettings->isWebBased = true;
    $kernelSettings->setRootDir ($rootDir);

    // Pre-assembly setup.

    $this->setupDebugging ($rootDir);
    // Temporarily set framework path mapping here for errors thrown during modules loading.
    ErrorConsole::setPathsMap ($kernelSettings->getMainPathMap ());

    // Bootstrap the framework/application's modules.

    /** @var Bootstrapper $boot */
    $bootstrapper = $this->injector->make (Bootstrapper::class);
    $bootstrapper->on (CONFIGURE, function (WebServer $webServer) use ($urlDepth) {
      $this->webServer = $webServer;
      $webServer->setup ($urlDepth);
    });
    $bootstrapper->run ();

    // Post-assembly additional setup.

    if ($this->debugMode)
      $this->setDebugPathsMap ($this->injector->make (ModulesRegistry::class));

    $this->webServer->run ();
  }

  /**
   * Configures path mappings for the ErrorHandler, so that links to files on symlinked directories are converted to
   * links on the main project tree, allowing easier files editing on an IDE.
   *
   * @param ModulesRegistry $registry
   */
  private function setDebugPathsMap (ModulesRegistry $registry)
  {
    $map = $this->kernelSettings->getMainPathMap ();
    $map = array_merge ($map, $registry->getPathMappings ());
    ErrorConsole::setPathsMap ($map);
  }

  /**
   * @param string $rootDir
   */
  private function setupDebugging ($rootDir)
  {
    set_exception_handler ([$this, 'exceptionHandler']);

    $debug = $this->kernelSettings->debugMode = $this->debugMode = getenv ('DEBUG') == 'true';
    $this->injector->defineParam ('debugMode', $debug);

    $debugConsole = getenv ('CONSOLE') == 'true';
    $this->injector->defineParam ('debugConsole', $debugConsole);

    ErrorConsole::init ($debug, $rootDir);
    ErrorConsole::setAppName ($this->kernelSettings->appName);

    $settings                    = new DebugConsoleSettings;
    $settings->defaultPanelTitle = 'Inspector';
    $settings->defaultPanelIcon  = 'fa fa-search';
    DebugConsole::init ($debug, $settings);
  }

}