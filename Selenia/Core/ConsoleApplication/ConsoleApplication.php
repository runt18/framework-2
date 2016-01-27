<?php
namespace Selenia\Core\ConsoleApplication;

use PhpKit\WebConsole\DebugConsole\DebugConsole;
use PhpKit\WebConsole\ErrorConsole\ErrorHandler;
use Robo\Config;
use Robo\Result;
use Robo\Runner;
use Robo\TaskInfo;
use Selenia\Application;
use Selenia\Core\Assembly\Services\ModulesLoader;
use Selenia\Core\ConsoleApplication\Services\ConsoleIO;
use Selenia\Interfaces\ConsoleIOInterface;
use Selenia\Interfaces\InjectorInterface;
use Symfony\Component\Console\Application as SymfonyConsole;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class ConsoleApplication extends Runner
{
  /**
   * @var SymfonyConsole
   */
  public $console;
  /**
   * @var ConsoleIO
   */
  protected $io;
  /**
   * @var Application
   */
  private $app;
  /**
   * @var InjectorInterface
   */
  private $injector;

  function __construct (ConsoleIO $io, Application $app, SymfonyConsole $console, InjectorInterface $injector)
  {
    $this->io       = $io;
    $this->app      = $app;
    $this->console  = $console;
    $this->injector = $injector;
    $console->setAutoExit (false);
  }

  /**
   * A factory for creating an instance of a console-based Selenia application.
   *
   * <p>Boots a Selenia Application and creates a console command runner with a base configuration.
   * > You'll have to configure the IO channels (ex. calling `setupStandardIO()` on the runner) before running the
   * application.
   *
   * @param InjectorInterface $injector Provide your favorite dependency injector.
   * @return static
   */
  static function make (InjectorInterface $injector)
  {
    // Create and register the foundational framework services.

    $injector
      ->share ($injector)
      ->alias (InjectorInterface::class, get_class ($injector));

    /** @var Application $app */
    $app = $injector
      ->share (Application::class)
      ->make (Application::class);

    $app->isConsoleBased = true;
    $app->setup (getcwd ());
    $app->preboot ();

    // Setup debugging

    ErrorHandler::init ();
    DebugConsole::init ($app->debugMode);

    // Setup the console.

    $console = new SymfonyConsole ('Selenia Console');
    $io      = new ConsoleIO;

    $consoleApp = new static ($io, $app, $console, $injector);

    $injector
      ->alias (ConsoleIOInterface::class, ConsoleIO::class)
      ->share ($io)
      ->share ($console)
      ->share ($consoleApp);

    // Bootstrap the application's modules.

    /** @var ModulesLoader $modulesApi */
    $loader = $injector->make (ModulesLoader::class);
    $loader->bootModules ();

    // Return the initialized application.

    return $consoleApp;
  }

  /**
   * Runs the console.
   *
   * @param InputInterface|null $input Overrides the input, if specified.
   * @return int 0 if everything went fine, or an error code
   */
  function execute ($input = null)
  {
    // Setup

    register_shutdown_function ([$this, 'shutdown']);
    set_error_handler ([$this, 'handleError']);
    $this->stopOnFail ();
    $this->customizeColors ();

    // Merge tasks from all registered task classes

    foreach ($this->app->taskClasses as $class) {
      if (!class_exists ($class)) {
        $this->getOutput ()->writeln ("<error>Task class '$class' was not found</error>");
        exit(1);
      }
      $this->mergeTasks ($this->console, $class);
    }

    // Run the given command

    return $this->console->run ($input ?: $this->io->getInput (), $this->io->getOutput ());
  }

  /**
   * Returns the console application's underlying console instance.
   *
   * @return SymfonyConsole
   */
  function getConsole ()
  {
    return $this->console;
  }

  /**
   * Returns the console application's input/output interface.
   *
   * @return ConsoleIOInterface
   */
  function getIO ()
  {
    return $this->io;
  }

  /**
   * Runs the specified console command, with the given arguments, as if it was invoked from the command line.
   *
   * @param string   $name
   * @param string[] $args
   * @return int 0 if everything went fine, or an error code
   */
  function run ($name, array $args = [])
  {
    $args  = array_merge (['', $name], $args);
    $input = $this->prepareInput ($args);
    return $this->execute ($input);
  }

  /**
   * Creates the default console I/O channels.
   *
   * @param string[] $args
   */
  function setupStandardIO ($args)
  {
    // Color support manual override:
    $hasColorSupport = in_array ('--ansi', $args) ? true : (in_array ('--no-ansi', $args) ? false : null);

    $input  = $this->prepareInput ($args);
    $output = new ConsoleOutput (ConsoleOutput::VERBOSITY_NORMAL, $hasColorSupport);
    Config::setOutput ($output);
    $this->io->setInput ($input);
    $this->io->setOutput ($output);
  }

  /**
   * @param SymfonyConsole $app
   * @param string         $className
   */
  protected function mergeTasks ($app, $className)
  {
    $roboTasks = $this->injector->make ($className);

    $commandNames = array_filter (get_class_methods ($className),
      function ($m) use ($className) {
        $method = new \ReflectionMethod($className, $m);
        return !in_array ($m, ['__construct']) && !$method->isStatic (); // Reject constructors and static methods.
      });

    $passThrough = $this->passThroughArgs;

    foreach ($commandNames as $commandName) {
      $command = $this->createCommand (new TaskInfo($className, $commandName));
      $command->setCode (function (InputInterface $input) use ($roboTasks, $commandName, $passThrough) {
        // get passthru args
        $args = $input->getArguments ();
        array_shift ($args);
        if ($passThrough) {
          $args[key (array_slice ($args, -1, 1, true))] = $passThrough;
        }
        $args[] = $input->getOptions ();

        $res = call_user_func_array ([$roboTasks, $commandName], $args);
        if (is_int ($res)) exit($res);
        if (is_bool ($res)) exit($res ? 0 : 1);
        if ($res instanceof Result) exit($res->getExitCode ());
      });
      $app->add ($command);
    }
  }

  protected function stopOnFail ($stopOnFail = true)
  {
    Result::$stopOnFail = $stopOnFail;
  }

  private function customizeColors ()
  {
    $this->io
      ->setColor ('title', new OutputFormatterStyle ('magenta'))
      ->setColor ('question', new OutputFormatterStyle ('cyan'))
      ->setColor ('red', new OutputFormatterStyle ('red'))
      ->setColor ('warning', new OutputFormatterStyle ('black', 'yellow'))
      ->setColor ('kbd', new OutputFormatterStyle ('green'));
  }

}