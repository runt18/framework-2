<?php
global $FRAMEWORK;
$FRAMEWORK = '../vendor/selene-framework/selene-kernel/src';

class Application
{
  const INI_FILENAME         = 'application.ini.php';
  const DEFAULT_INI_FILENAME = 'application.defaults.ini.php';

  public static $TAGS = [
    'button'      => 'Button',
    'calendar'    => 'Calendar',
    'checkbox'    => 'Checkbox',
    'data-grid'   => 'DataGrid',
    'field'       => 'Field',
    'file-upload' => 'FileUpload',
    'head'        => 'Head',
    'html-editor' => 'HtmlEditor',
    'image'       => 'Image',
    'image-field' => 'ImageField',
    'input'       => 'Input',
    'label'       => 'Label',
    'link'        => 'Link',
    'main-menu'   => 'MainMenu',
    'model'       => 'Model',
    'paginator'   => 'Paginator',
    'radiobutton' => 'Radiobutton',
    'selector'    => 'Selector',
    'tab'         => 'Tab',
    'tab-page'    => 'TabPage',
    'tabs'        => 'Tabs',
  ];

  /**
   * The application name.
   * This should be composed only of alphanumeric characters. It is used as the session name.
   * If not specified, defaults to the parent application name or, if not defined, the application path or,
   * if it is a root application, the server name.
   * @var string
   */
  public $name;
  /**
   * The real application name.
   * @var string
   */
  public $appName;
  /**
   * A site name that can be used on autogenerated window titles (using the title tag).
   * @var string
   */
  public $title;
  public $rootPath;
  /**
   * The URI of current application's directory.
   * @var string
   */
  public $URI;
  /**
   * The file path of current application's directory.
   * @var string
   */
  public $directory;
  /**
   * The URI of current main application's root directory.
   * @var string
   */
  public $baseURI;
  /**
   * The file path of current main application's root directory.
   * @var string
   */
  public $baseDirectory;
  /**
   * The colon delimited list of directory paths.
   * @var string
   */
  public $includePath;
  /**
   * The path of the framework directory.
   * @var string
   */
  public $frameworkPath;
  /**
   * The virtual URI specified after the application's base URI.
   * @var string
   */
  public $VURI;
  /**
   * The relative path of the public folder inside a module.
   * @var string
   */
  public $modulePublicPath;
  /**
   * The application's main public (web) folder.
   * @var string
   */
  public $appPublicPath;
  /**
   * The mapped public URI of the framework's public directory.
   * @var string
   */
  public $frameworkURI;
  public $modelPath;
  public $viewPath;
  /**
   * The relative path of the views folder inside a module.
   * @var string
   */
  public $moduleViewsPath;
  /**
   * The relative path of the templates folder inside a module.
   * @var string
   */
  public $moduleTemplatesPath;
  public $addonsPath;
  /**
   * The path of the application's language files' folder.
   * @var string
   */
  public $langPath;
  /**
   * The relative path of the language files' folder inside a module.
   * @var string
   */
  public $moduleLangPath;
  /**
   * The folder where the framework will search for your application-specific modules.
   * <p>If a module is not found there, it will then search on `defaultModulesPath`.
   * <p>Set by application.ini.php.
   * @var String
   */
  public $modulesPath;
  /**
   * <p>The fallback folder name where the framework will search for modules.
   * <p>Modules installed as Composer packages will be found there.
   * <p>Set by application.ini.php.
   * @var String
   */
  public $defaultModulesPath;
  /**
   * A list of modules that are always bootstrapped when the framework boots.
   * <p>A `bootstrap.php` file will be executed on each registered module.
   * @var array
   */
  public $modules;
  /**
   * Folder path for the configuration files.
   * @var string
   */
  public $configPath;

  /* Template related */
  public $templatesPath;
  public $pageTemplate;

  /* Archive related */
  public $archivePath;
  public $imageArchivePath;
  public $fileArchivePath;
  public $inlineArchivePath;
  public $galleryPath;

  /* Cache related */
  public $cachePath;
  public $imagesCachePath;
  public $stylesCachePath;
  public $CSS_CachePath;

  /* Page processing control settings */
  public $enableCompression;
  public $debugMode;
  public $condenseLiterals;
  public $packScripts;
  public $packCSS;
  public $resourceCaching;
  /**
   * @var Boolean True to generate the standard framework scripts.
   */
  public $frameworkScripts;
  /**
   * Defines the file path for the RoutingMap class, set on application.ini.php.
   * @var String
   */
  public $routingMapFile;
  /**
   * Defines the file path for the Model collection or its XML description, set on application.ini.php.
   * @var String
   */
  public $modelFile;
  /**
   * Defines the file path for the data sources collection or its XML description, set on application.ini.php.
   * @var String
   */
  public $dataSourcesFile;
  /**
   * Defines the file path for the SEO information collection or its XML description, set on application.ini.php.
   * @var String
   */
  public $SEOFile;
  /**
   * The class to be instantiated when creating an automatic controller.
   * @var string
   */
  public $autoControllerClass;
  /**
   * The application'a routing map.
   * @var RoutingMap
   */
  public $routingMap;
  /**
   * A map of URI prefixes to application configuration files.
   * @var array
   */
  public $subApplications;
  /**
   * Holds an array of multiple DataSourceInfo for each site page or null;
   * @var array
   */
  public $dataSources;
  /**
   * Holds an array of SEO infomation for each site page or null;
   * @var array
   */
  public $SEOInfo;
  /**
   * The default URI when none is specified on the URL.
   * The URI locates an entry on the routing map where additional info. is used to
   * load the default page.
   * Set by application.ini.php
   * @var String
   */
  public $defaultURI;
  /**
   * The address of the page to be displayed when the current page URI is invalid.
   * If null an exception is thrown.
   * @var string
   */
  public $URINotFoundURL = null;

  /* Session related */
  public $isSessionRequired;
  public $autoSession = false;
  /**
   * Set to false to disable application-specific sessions and use a global scope.
   * @var Boolean
   */
  public $globalSessions = false;

  /**
   * Favorite icon URL.
   * @var string
   */
  public $favicon = '';

  /**
   * Set to true to redirect the browser to the generated thumbnail instead of streaming it.
   * @var Boolean
   */
  public $imageRedirection;

  /**
   * Enables output post-processing for keyword replacement.
   * Disable this if the app is not multi-language to speed-up page rendering.
   * Keywords syntax: $keyword
   * @var bool
   */
  public $translation = false;
  /**
   * List of languages enabled on the application.
   *
   * <p>Each language should be specified like this: `langCode:ISOCode:langLabel:locale1|locale2`
   *
   * <p>Ex.
   * ```
   * [
   *   'en:en-US:English:en_US|en_US.UTF-8|us',
   *   'pt:pt-PT:Português:pt_PT|pt_PT.UTF-8|ptg',
   *   'es:es-ES:Español:es_ES|es_ES.UTF-8|esp'
   * ]
   * ```
   * @var string[]
   */
  public $languages = [];

  /**
   * A two letter code for default site language. NULL if i18n is disabled.
   * <p>This is set on the environment (ex: .env).
   * @var string
   */
  public $defaultLang = null;

  /**
   * The default page size for the default data source.
   * @var number
   */
  public $pageSize;

  /**
   * The URL parameter name used for pagination.
   * @var string
   */
  public $pageNumberParam;

  /**
   * Define a message to be displayed if the browser is IE6.
   * If not set or empty, no message is shown.
   * For multilingual sites, the text may be a $XXX translation code.
   * @var string
   */
  public $oldIEWarning;

  /**
   * If set, this defines the public IP of the production server hosting the release website.
   * This will be used to check if the website is running on the production webserver.
   * @see Controller->isProductionSite
   * @var string
   */
  public $productionIP;

  /**
   * Defines the Google Anallytics account ID.
   * This is required if the GoogleAnalytics template is present on the page.
   * @var string
   */
  public $googleAnalyticsAccount;
  /**
   * The homepage's breadcrumb icon class(es).
   * @var string
   */
  public $homeIcon;
  /**
   * The homepage's breadcrumb title.
   * @var string
   */
  public $homeTitle;
  /**
   * A map of mappings from virtual URIs to external folders.
   * <p>This is used to expose assets from composer packages.
   * <p>Array of URI => physical folder path
   * @var array
   */
  public $mountPoints = [];
  /**
   * Additional template directories to be registered on the templating engine.
   * @var array
   */
  public $templateDirectories = [];
  /**
   * Search paths for module language files, in order of precedence.
   * @var array
   */
  public $languageFolders = [];

  public function run ($dir, $appDir, $baseOffs = '')
  {
    ErrorHandler::init ();
    $this->setup ($dir, $appDir, $baseOffs);
    ModuleLoader::loadAndRun ();
  }

  /**
   * Composer packages can call this method to expose assets on web.
   * @param string $URI
   * @param string $path
   */
  public function mount ($URI, $path)
  {
    $this->mountPoints[$URI] = $path;
  }

  /**
   * Sets up the application configuration.
   * When overriding this method, always call the super() after running your own
   * code, so that paths computed here can take into account your changes.
   */
  public function setup ($dir, $appDir, $baseOffs)
  {
    global $FRAMEWORK, $NO_APPLICATION;

    $uri     = $_SERVER['REQUEST_URI'];
    $baseURI = dirname ($_SERVER['SCRIPT_NAME']);
    $vuri    = substr ($uri, strlen ($baseURI) + 1) ?: '';

    //var_dump($_SERVER);exit;
    $this->isSessionRequired = false;
    $this->directory         = $dir;
    $this->baseDirectory     = realpath ("$dir$baseOffs");
    $this->URI               = $baseURI;
    $this->baseURI           = "$baseURI$baseOffs";
    $this->frameworkPath     = realpath ("$appDir/$FRAMEWORK");
    $this->VURI              = $vuri;
    $this->rootPath          = dirname ($appDir);

    $this->setIncludePath ();

    // Load default configuration.

    $iniPath = $this->frameworkPath . DIRECTORY_SEPARATOR . self::DEFAULT_INI_FILENAME;
    $this->loadConfig ($iniPath);

    // Load application-specific configuration.

    $iniPath = $this->rootPath . DIRECTORY_SEPARATOR . $this->configPath . DIRECTORY_SEPARATOR . self::INI_FILENAME;
    $this->loadConfig ($iniPath);

    foreach ($this->subApplications as $prefix => $path) {
      if (substr ($vuri, 0, strlen ($prefix)) == $prefix) {
        $iniPath = $this->rootPath . DIRECTORY_SEPARATOR . $this->configPath . DIRECTORY_SEPARATOR . $path;
        $this->loadConfig ($iniPath);
      }
    }

    $this->templateDirectories[] = $this->templatesPath;
    $this->languageFolders[] = $this->langPath;
    $this->bootModules ();

    if (empty($this->name))
      $this->name = $this->URI ? $this->URI : $_SERVER['SERVER_NAME'];
    if (isset($_ENV['APP_DEFAULT_LANG']))
      $this->defaultLang = $_ENV['APP_DEFAULT_LANG'];

    $this->mount ($this->frameworkURI, dirname ($this->frameworkPath) . "/$this->modulePublicPath");

    if (!$NO_APPLICATION) {
      $this->loadRoutes ();
    }
  }

  public function setIncludePath ($extra = '')
  {
    if (!empty($extra)) {
      $extra .= PATH_SEPARATOR;
      set_include_path ($this->includePath = $extra . $this->includePath);
      return;
    }
    $path = $extra . $this->rootPath;
    set_include_path ($path);
    $this->includePath = $path;
    //var_dump($this);exit;
  }

  public function toURL ($URI)
  {
    $port = ':' . $_SERVER['SERVER_PORT'];
    if ($port == ":80")
      $port = '';
    return "http://{$_SERVER['SERVER_NAME']}$port$URI";
  }

  public function toURI ($path)
  {
    return "$this->baseURI/$path";
  }

  public function fromPathToURL ($path)
  {
    return $this->toURL ($this->toURI ($path));
  }

  public function toFilePath ($URI)
  {
    if ($URI[0] == '/')
      return $this->baseDirectory . substr ($URI, strlen ($this->baseURI));
    return "$this->baseDirectory" . DIRECTORY_SEPARATOR . "$URI";
  }

  public function toRelativePath ($URI)
  {
    global $application;
    return substr ($URI, strlen ($application->baseURI) + 1);
  }

  public function toThemeURI ($relativeURI, Theme &$theme)
  {
    return "$this->baseURI/$theme->path/$relativeURI";
  }

  public function getAddonURI ($addonName)
  {
    return "$this->baseURI/$this->addonsPath/$addonName";
  }

  public function getImageURI ($fileName)
  {
    return "$this->baseURI/$this->imageArchivePath/$fileName";
  }

  public function getFileURI ($fileName)
  {
    return "$this->baseURI/$this->fileArchivePath/$fileName";
  }

  /**
   * Given a theme's stylesheet or CSS URI this method returns an unique name
   * suitable for naming a file on the cache folder.
   * @param String $URI The absolute URI of the original file.
   * @return String A file name.
   */
  public function generateCacheFilename ($URI)
  {
    $themesPath = strpos ($URI, $this->themesPath) !== false ? $this->themesPath : $this->defaultThemesPath;
    return str_replace ('/', '_', substr ($URI, strlen ($this->baseURI) + strlen ($themesPath) + 2));
  }

  private function bootModules ()
  {
    global $application; // Used by the loaded bootstrap.php

    foreach ($this->modules as $path) {
      $boot = "$path/bootstrap.php";
      $f    = @include "$this->modulesPath/$boot";
      if ($f === false)
        $f = @include "$this->defaultModulesPath/$boot";
      if ($f === false)
        throw new ConfigException("File <b>$boot</b> was not found.");
    }
  }

  private function loadConfig ($iniPath)
  {
    $ini = @include $iniPath;
    if ($ini)
      extend ($this, $ini['main']);
    else
      throw new ConfigException("Error parsing " . ErrorHandler::shortFileName ($iniPath));
  }

  private function loadRoutes ()
  {
    global $model; //used by PageRoute
    if (!empty($this->routingMapFile)) {
      $cfg = require $this->routingMapFile;
      $map = new RoutingMap();
      foreach ($cfg as $k => $v)
        $map->$k = $v;
      $this->routingMap = $map;
      $map->init ();
    }
  }

}
