<?php
namespace Electro;

use Electro\Core\Assembly\Config\AssemblyModule;
use Electro\Core\Logging\Config\LoggingModule;
use Electro\Core\WebApplication\ApplicationMiddlewareAssembler;
use Electro\Exceptions\Fatal\ConfigException;
use Electro\Interfaces\DI\InjectorInterface;
use Electro\Interfaces\Http\ApplicationMiddlewareAssemblerInterface;
use Electro\Interfaces\Navigation\NavigationProviderInterface;

class Application
{
  const FRAMEWORK_PATH = 'private/packages/electro/framework';
  /**
   * The real application name.
   *
   * @var string
   */
  public $appName = '⚡️ ELECTRO';
  /**
   * The file path of current main application's root directory.
   *
   * @var string
   */
  public $baseDirectory;
  /**
   * The URI of current main application's root directory.
   *
   * @var string
   */
  public $baseURI;
  /**
   * Whether to compress or not the HTTP response with gzip enconding.
   *
   * @var bool
   */
  public $compressOutput = false;
  /**
   * Remove white space around raw markup blocks?
   *
   * @var boolean
   */
  public $condenseLiterals;
  /**
   * A mapping between modules view templates base directories and the corresponding PHP namespaces that will be
   * used for resolving view template paths to PHP controller classes.
   *
   * @var array
   */
  public $controllerNamespaces = [];
  /**
   * A map of absolute view file paths to PHP controller class names.
   *
   * <p>This is used by the `Include` component.
   *
   * @var array
   */
  public $controllers = [];
  /**
   * Favorite icon URL.
   *
   * @var string
   */
  public $favicon = 'data:;base64,iVBORw0KGgo=';
  /**
   * @var string
   */
  public $fileArchivePath = 'private/storage/files';
  /**
   * @var string
   */
  public $fileBaseUrl = 'files';
  /**
   * The path of the framework kernel's directory.
   *
   * @var string
   */
  public $frameworkPath;
  /**
   * The mapped public URI of the framework's public directory.
   *
   * @var string
   */
  public $frameworkURI = 'framework';
  /**
   * @var string
   */
  public $imageArchivePath = 'private/storage/images';
  /**
   * Set to true to redirect the browser to the generated thumbnail instead of streaming it.
   *
   * @var Boolean
   */
  public $imageRedirection = false;
  /**
   * @var string
   */
  public $imagesCachePath = 'private/storage/cache/images';
  /**
   * The colon delimited list of directory paths.
   *
   * @var string
   */
  public $includePath;
  /**
   * @var \Electro\Core\DependencyInjection\Injector
   */
  public $injector;
  /**
   * If `true` the application is a console app, otherwise it may be a web app.
   *
   * @see \Electro\Application::$isWebBased
   * @var bool
   */
  public $isConsoleBased = false;
  /**
   * If `true` the application is a web app, otherwise it may be a console app.
   *
   * @see \Electro\Application::$isConsoleBased
   * @var bool
   */
  public $isWebBased = false;
  /**
   * Search paths for module language files, in order of precedence.
   *
   * @var array
   */
  public $languageFolders = [];
  /**
   * The relative URL of the login form page.
   *
   * @var string
   */
  public $loginFormUrl = 'login/login';
  /**
   * Directories where macros can be found.
   * <p>They will be search in order until the requested macro is found.
   * <p>These paths will be registered on the templating engine.
   * <p>This is preinitialized to the application macro's path.
   *
   * @var array
   */
  public $macrosDirectories = [];
  /**
   * Relative to the root folder.
   *
   * @var string
   */
  public $macrosPath = 'private/resources/macros';
  /**
   * The relative path of the language files' folder inside a module.
   *
   * @var string
   */
  public $moduleLangPath = 'resources/lang';
  /**
   * The relative path of the macros folder inside a module.
   *
   * @var string
   */
  public $moduleMacrosPath = 'resources/macros';
  /**
   * The relative path of the public folder inside a module.
   *
   * @var string
   */
  public $modulePublicPath = 'public';
  /**
   * The path to the folder where symlinks to all modules' public folders are placeed.
   *
   * @var string
   */
  public $modulesPublishingPath = 'modules';
  /**
   * The relative path of the views folder inside a module.
   *
   * @var string
   */
  public $moduleViewsPath = 'resources/views';
  /**
   * A list of modules that are always bootstrapped when the framework boots.
   * <p>A `bootstrap.php` file will be executed on each registered module.
   *
   * @var array
   */
  public $modules = [];
  /**
   * The folder where the framework will search for your application-specific modules.
   * <p>If a module is not found there, it will then search on `defaultModulesPath`.
   * <p>Set by application.ini.php.
   *
   * @var String
   */
  public $modulesPath = 'private/modules';
  /**
   * The application name.
   * This should be composed only of alphanumeric characters. It is used as the session name.
   *
   * @var string
   */
  public $name = 'electro';
  /**
   * All registered navigation providers.
   * <p>This will be read when the Navigation service is injected for the first time.
   * It can hold class names or instances.
   *
   * @var NavigationProviderInterface[]|string[]
   */
  public $navigationProviders = [];
  /**
   * Maximum width and/or height for uploaded images.
   * Images exceeding this dimensions are resized to fit them.
   *
   * @var int
   */
  public $originalImageMaxSize = 1024;
  /**
   * JPEG compression factor for resampled uploaded images.
   *
   * @var int
   */
  public $originalImageQuality = 95;
  /**
   * The URL parameter name used for pagination.
   *
   * @var string
   */
  public $pageNumberParam = 'p';
  /**
   * The default page size for pagination (ex: on the DataGrid). It is only applicable when the user has not yet
   * selected a custom page size.
   *
   * @var number
   */
  public $pageSize = 15;
  /**
   * <p>The fallback folder name where the framework will search for modules.
   * <p>Plugin modules installed as Composer packages will be found there.
   * <p>Set by application.ini.php.
   *
   * @var String
   */
  public $pluginModulesPath = 'private/plugins';
  /**
   * @var string[] A list of "preset" class names.
   */
  public $presets = [];
  /**
   * @var string The file path of a router script for the build-in PHP web server.
   */
  public $routerFile = 'private/packages/electro/framework/devServerRouter.php';
  /**
   * @var string
   */
  public $storagePath = 'private/storage';
  /**
   * Registered Matisse tags.
   *
   * @var array
   */
  public $tags = [];
  /**
   * A list of task classes from each module that provides tasks to be merged on the main robofile.
   *
   * @var string[]
   */
  public $taskClasses = [];
  /**
   * A site name that can be used on auto-generated window titles (using the title tag).
   * The symbol @ will be replaced by the current page's title.
   *
   * @var string
   */
  public $title = '@';
  /**
   * Enables output post-processing for keyword replacement.
   * Disable this if the app is not multi-language to speed-up page rendering.
   * Keywords syntax: $keyword
   *
   * @var bool
   */
  public $translation = false;
  /**
   * Folders where views can be found.
   * <p>They will be search in order until the requested view is found.
   *
   * @var array
   */
  public $viewsDirectories = [];

  function __construct (InjectorInterface $injector)
  {
    $this->injector = $injector;
  }

  /**
   * Gets an array of file path mappings for the core framework, to aid debugging symlinked directiories.
   *
   * @return array
   */
  function getMainPathMap ()
  {
    $rp = realpath ($this->frameworkPath);
    return $rp != $this->frameworkPath ? [
      $rp => self::FRAMEWORK_PATH,
    ] : [];
  }

  /**
   * Boots up the core framework modules.
   *
   * <p>This occurs before the framework's main boot up sequence.
   * <p>Unlike the later, which is managed automatically, this pre-boot process is manually defined and consists of just
   * a few core services that must be setup before any other module loads.
   */
  function preboot ()
  {
    $assemblyModule = new AssemblyModule;
    $assemblyModule->register ($this->injector);
    $loggingModule = new LoggingModule;
    $loggingModule->register ($this->injector);
    // This can be overriden later, usually by a private application module.
    $this->injector->alias (ApplicationMiddlewareAssemblerInterface::class, ApplicationMiddlewareAssembler::class);
  }

  /**
   * Sets up the application configuration.
   * When overriding this method, always call the super() after running your own
   * code, so that paths computed here can take into account your changes.
   *
   * @param string $rootDir
   * @throws ConfigException
   */
  function setup ($rootDir)
  {
    $this->baseDirectory = $rootDir;
    $this->frameworkPath =
      "$rootDir/" . self::FRAMEWORK_PATH; // due to eventual symlinking, we can't use dirname(__DIR__) here
  }

  /**
   * Strips the base path from the given absolute path if it falls lies inside the applicatiohn.
   * Otherwise, it returns the given path unmodified.
   *
   * @param string $path
   * @return string
   */
  function toRelativePath ($path)
  {
    if ($path) {
      if ($path[0] == DIRECTORY_SEPARATOR) {
        $l = strlen ($this->baseDirectory);
        if (substr ($path, 0, $l) == $this->baseDirectory)
          return substr ($path, $l + 1);
      }
    }
    return $path;
  }

}