<?php
namespace Selenia\Http\Controllers;

use Exception;
use PDO;
use PDOStatement;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionException;
use ReflectionObject;
use Selenia\Application;
use Selenia\Authentication\Exceptions\AuthenticationException;
use Selenia\DataObject;
use Selenia\Exceptions\Fatal\ConfigException;
use Selenia\Exceptions\Fatal\DataModelException;
use Selenia\Exceptions\Fatal\FileNotFoundException;
use Selenia\Exceptions\FatalException;
use Selenia\Exceptions\Flash\FileException;
use Selenia\Exceptions\Flash\ValidationException;
use Selenia\Exceptions\FlashMessageException;
use Selenia\Exceptions\FlashType;
use Selenia\Exceptions\HttpException;
use Selenia\Matisse\Components\Page;
use Selenia\Matisse\Context;
use Selenia\Matisse\DataRecord;
use Selenia\Matisse\DataSet;
use Selenia\Matisse\DataSource;
use Selenia\Matisse\Exceptions\DataBindingException;
use Selenia\Matisse\MatisseEngine;
use Selenia\Routing\PageRoute;
use Selenia\Routing\Router;
use Zend\Diactoros\Response\HtmlResponse;

ob_start ();

class Controller
{
  public           $TEMPLATE_EXT = '.html';
  /**
   * The current request URI.
   * This property is useful for databing with the expression {!controller.URI}.
   */
  public $URI;
  /**
   * A list of parameter names (inferred from the page definition on the sitemap)
   * and correponding values present on the current URI.
   * @var array
   */
  public $URIParams;
  /**
   * The current request URI without the page number parameters.
   * This property is useful for databing with the expression {!controller.URI_noPage}.
   */
  public $URI_noPage;
  /**
   * Information about the route associated with this controller.
   * @var PageRoute
   */
  public $activeRoute;
  /**
   * When `true`, the framework will attempt to automatically load the model object by fetching key information from
   * the URL, the route's `preset` property or from the request data.
   * This setting is usually defined on routes, but if no routing is being used, it can also be set here.
   * @var bool
   */
  public $autoloadModel = false;
  /**
   * Matisse rendering context.
   * @var Context
   */
  public $context;
  /**
   * If $dataClass is defined, this property may hold a comma-separated list of field names used
   * on the default query.
   * @var string
   */
  public $dataFields = '';
  /**
   * If $dataClass is defined, this property may hold a WHERE expression for the default query.
   * @var string
   */
  public $dataFilter = '';
  /**
   * If $dataClass is defined, the instantiated instance is stored in this property.
   * @var DataObject
   */
  public $dataItem = null;
  /**
   * If $dataClass is defined, this property may hold an array with information about the parameters
   * automatically supplied to the query.
   * Each array entry is a string with a constant value or a databinding expression in the format:
   * {!dataSourceName.dataFieldName}
   * @var array
   */
  public $dataQueryParams = null;
  /**
   * If $dataClass is defined, this property may hold a SORT BY expression for the default query.
   * @var string
   */
  public $dataSortBy = '';
  /**
   * If no sitemap is used, this property controls the creation of a default
   * data source.
   * Possible values are:
   * <p>
   * '' - no default datasource;<br/>
   * 'form' - create a single record default datasource;<br/>
   * 'grid' - create a multi-record default datasource.<br/>
   * </p>
   * @var String
   */
  public $defaultPageFormat = '';
  /**
   * A templating engine instance.
   * @var MatisseEngine
   */
  public $engine;
  /**
   * Associative array of all components on the page which have an explicit ID.
   * @var array of Component
   */
  public $id = [];
  /**
   * Set to true to handle the request in a way more adapted to XML web services.
   * @var Boolean
   */
  public $isWebService = false;
  /**
   * A two letter code for currently active language. NULL if i18n is disabled.
   * @var string
   */
  public $lang = null;
  /**
   * The ISO language code of the active language (ex. en-US).
   * @var string
   */
  public $langISO = null;
  /**
   * Array of information about each enabled language.
   * Each entry is in the format: 'langCode' => array('value'=>,'ISO'=>,'label'=>,'locale'=>)
   * @var array
   */
  public $langInfo;
  /**
   * The human readable name of the active language (ex. English).
   * @var string
   */
  public $langLabel = null;
  /**
   * The locale language code of the active language (ex. en_US).
   * @var string
   */
  public $locale = null;
  /**
   * @var int Maximum number of pages.
   */
  public $max = 1;
  /**
   * The current module's folder full physical URI.
   * @var string
   */
  public $moduleURI;
  /**
   * Points to the root of the components tree.
   * @var Page
   */
  public $page;
  /**
   * @var int Current page number.
   */
  public $pageNumber = 1;
  /**
   * @var string A & separated list of key=value pairs to initialize the dataItem.
   */
  public $preset = null;
  /**
   * Stores the POST information that was being sent before the login form appeared.
   * @var string
   */
  public $prevPost = '';
  /**
   * The loader which has loaded this controller.
   * @var Router
   */
  public $router;
  /**
   * Indicate if advanced XML/HTML view processing is enabled.
   * Set to false if your controller generates the response via respond().
   * @var boolean
   */
  public $viewProcessing = true;
  /**
   * @var string The virtual URI following the ? symbol on the current page's URL.
   */
  public $virtualURI;
  /**
   * If specified on a subclass, the controller will automatically instantiate and initialize
   * a corresponding instance on setupModel() and also setup a default data source named 'default'
   * on setupViewModel().
   * @see Controller::dataItem
   * @var string The name of the class to be instantiated.
   */
  protected $dataClass = null;
  /**
   * Specifies the URL of the index page, to where the browser should naviagate upon the
   * successful insertion / update of records.
   * If not defined on a subclass then the request will redisplay the same page.
   * @var string
   */
  protected $indexPage = null;
  /**
   * A list of languages codes for the available languages, as configured on Application.
   * @var string
   */
  protected $languages;
  /**
   * If specified, allows a controller to simultaneously define $dataClass and $modelMethod.
   *
   * Syntax:
   *
   *       $model = 'ModelClass'
   *       $model = 'ModelClass::modelMethod'
   *       $model = ['ModelClass', 'modelMethod']
   *
   * @var string|array
   */
  protected $model = null;
  /**
   * When set, the value will be used to set the default data source for the view.
   * @var PDOStatement|array
   */
  protected $modelData;
  /**
   * If specified on a subclass, the controller will automatically invoke the specified method on an
   * instance of $dataClass to retrieve the default data source.
   * @var string
   */
  protected $modelMethod = null;
  /**
   * If set, defines the page title. It will generate a document `<title>` and it can be used on
   * breadcrumbs.
   * @var string
   */
  protected $pageTitle = null;
  /**
   * @var string URI to redirect to when the request processing finishes.
   */
  protected $redirectURI = null;
  /**
   * If set to true, the view will be rendered on the POST request without a redirection taking place.
   * @var bool
   */
  protected $renderOnPOST = false;
  /**
   * @var array|null
   */
  public $flashMessage = null;

  static function modPathOf ($virtualURI = '', $params = null)
  {
    global $application;
    if ($virtualURI == '')
      return '.';
    $append = (!empty($params) ? '?' . $params : '');
    if ($virtualURI[0] == '/')
      return "$virtualURI$append";
    else return "$application->baseURI/$virtualURI$append";
  }

  static function pageNotFound ($virtualURI = '')
  {
    global $application;

    if (!empty($application->URINotFoundURL)) {
      if (preg_match ('#^(\w\w)/#', $virtualURI, $match))
        $lang = $match[1];
      else $lang = $application->defaultLang;
      $URI = str_replace ('{lang}', $lang, $application->URINotFoundURL);
      header ('Location: ' . "$application->baseURI/$URI" . '?URL=' . $_SERVER['REQUEST_URI'], true, 303);
      exit;
    }
    else throw new HttpException (404,
      "<h1>Not Found</h1><p>The requested URL <code><big>$application->baseURI/<b>$virtualURI</b></big></code> was not found on this server.</p>");
  }

  static function redirect ($url)
  {
    header ('Location: ' . $url, true, 303);
    exit();
  }

  static function ref ()
  {
    return get_called_class ();
  }

  /**
   * Performs the main execution sequence.
   * Provides support for:
   * - the standard GET/POST/redirect cycle;
   * - exception handling;
   * - authentication.
   * Every page controller should call this method.
   * Request handling has 2 phases:
   * 1 - processRequest() - optional - performs actions requested by the client;
   * 2 - processView() - optional - generates the user interface and any relevant information to display on the client.
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface      $response
   * @param callable               $next
   * @return HtmlResponse
   * @throws FlashMessageException
   * @throws Exception
   */
  final function __invoke (ServerRequestInterface $request, ResponseInterface $response, callable $next)
  {
    global $controller, $application;
    $controller   = $this;
    $authenticate = false;
    try {
      $this->URI = $_SERVER['REQUEST_URI'];
      // remove page number parameter
      /*
      $this->URI_noPage = preg_replace ('#&?' . $application->pageNumberParam . '=\d*#', '', $this->URI);
      $this->URI_noPage = preg_replace ('#\?$#', '', $this->URI_noPage);
      */
      $this->setupController ();
      $this->initTemplateEngine ();
      $this->configPage ();
      $authenticate = $this->authenticate ();
      if ($authenticate === 'retry') {
        $this->setRedirection ();
        $this->redirectAndHalt ();
      }
      $this->initialize (); //custom setup
      if (!$authenticate) {
        // Normal page request (it's not a login form).
        $this->setupModel ();
        if ($this->wasPosted ()) {
          if (!$this->isWebService)
            $this->setRedirection (); //defaults to the same URI
          try {
            $res = $this->processRequest ();
            if ($res)
              return $res;
            //if not a web service, the processing stops here.
          } catch (ValidationException $e) {
            $this->cancelRedirection ();
            throw $e;
          }
        }
      }
      if ($this->wasPosted ()) {
        if ($this->renderOnPOST)
          $this->processView ($authenticate);
        else {
          if ($authenticate)
            $this->processView ($authenticate);
          $this->redirectAndHalt ();
        }
      }
      else if (is_null ($this->redirectURI)) {
        if (!$this->viewProcessing || !$this->processView ($authenticate)) {
          $this->respond ();
        }
      }
      $this->finalize ();
      $content = ob_get_clean ();
      return new HtmlResponse ($content);
    } catch (Exception $e) {
      if ($e instanceof FlashMessageException) {
        if (isset($this->redirectURI) && $e->getCode () != FlashType::FATAL) {
          $this->setStatusFromException ($e);
          $this->redirectAndHalt ();
        }
        @ob_clean ();
      }
      if (!($e instanceof FlashMessageException) || $e->getCode () == FlashType::FATAL || $this->isWebService) {
        if ($this->isWebService) {
          @ob_get_clean ();
          http_response_code (500);
          echo $e->getMessage ();
          if ($application->debugMode)
            echo "\n\nStack trace:\n" . $e->getTraceAsString () . "\n";
          exit;
        }
        throw $e;
      }
      else {
        $this->setStatusFromException ($e);
        try {
          if (!$this->processView ($authenticate)) //retry the view, this time displaying the error message
          {
            @ob_clean ();
            echo "<pre>" . $e->getMessage () . "\n\n" . htmlentities ($e->getTraceAsString ()) . "</pre>";
          }
        } catch (Exception $e) {
          echo "<pre>" . $e->getMessage () . "\n\n" . htmlentities ($e->getTraceAsString ()) . "</pre>";
        }
      }
    }
  }

  /**
   * Responds to the standard 'delete' controller action.
   * The default procedure is to delete the object on the database.
   * Override to implement non-standard behaviour.
   * @param DataObject $data
   * @param null       $param
   * @throws FlashMessageException
   * @throws DataModelException
   * @throws Exception
   * @throws FatalException
   */
  function action_delete (DataObject $data = null, $param = null)
  {
    if (!isset($data))
      throw new FlashMessageException('Can\'t delete NULL DataObject.', FlashType::FATAL);
    if (!isset($data->id) && isset($param)) {
      $data->setPrimaryKeyValue ($param);
      $data->read ();
    }
    $data->delete ();
    if (!$this->autoRedirect ())
      throw new FatalException("No index page defined.");
  }

  /**
   * Allows processing on the server side to occur and redraws the current page.
   * This is useful, for instance, for updating a form by submitting it without actually saving it.
   * The custom processing will usually take place on the render() or the viewModel() methods, but you may also
   * override this method; just make sure you call the inherited one.
   * @param DataObject $data  The current model object as being filled out on the form, if any.
   * @param string     $param A JQuery selector for the element that should automatically receive focus after the page
   *                          reloads.
   */
  function action_refresh (DataObject $data = null, $param = null)
  {
    $this->renderOnPOST = true;
    if ($param)
      $this->page->addInlineDeferredScript ("$('$param').focus()");
  }

  /**
   * Respondes to the standard 'submit' controller action.
   * The default procedure is to either call insert() or update().
   * Override to implement non-standard behaviour.
   * @param DataObject $data
   * @param null       $param
   * @throws FlashMessageException
   */
  function action_submit (DataObject $data = null, $param = null)
  {
    if (!isset($data))
      throw new FlashMessageException('Can\'t insert/update NULL DataObject.', FlashType::FATAL);
    if ($data->isNew ())
      $this->insertData ($data, $param);
    else $this->updateData ($data, $param);
  }

  function beginJSONResponse ()
  {
    header ('Content-Type: application/json');
  }

  function beginXMLResponse ()
  {
    header ('Content-Type: text/xml');
    echo '<?xml version="1.0" encoding="utf-8"?>';
  }

  function getDataRecord ($name = null)
    //rarely overriden
  {
    if (is_null ($name)) {
      $ds = property ($this->page, 'defaultDataSource');
      if (isset($ds)) {
        $it = $ds->getIterator ();
        if ($it->valid ())
          return $it->current ();
        return null;
      }
      else throw new DataBindingException(null, "The default data source for the page is not defined.");
    }
    $ctx = $this->context;
    if (array_key_exists ($name, $ctx->dataSources)) {
      $it = $ctx->dataSources[$name]->getIterator ();
      if ($it->valid ()) return $it->current ();
      return null;
    }
    throw new DataBindingException(null, "Data source <b>$name</b> is not defined.");
  }

  function getField ($field, $dataSource = null)
  {
    return $this->getDataRecord ($dataSource)[$field];
  }

  /**
   * Returns the specified HTTP request header.
   * @param string $name The header name. Ex: 'Content-Type'
   * @return string|null null if the header doesn't exist.
   */
  function getHeader ($name)
  {
    return get (getallheaders (), $name);
  }

  final function getPageURI ()
  {
    $uri = $_SERVER['REQUEST_URI'];
    $i   = strpos ($uri, '?');
    if (!$i) return $uri;
    else return substr ($uri, 0, $i);
  }

  function getRowOffset ()
  {
    global $application;
    return ($this->pageNumber - 1) * $application->pageSize;
  }

  function getTitle ()
    // override to return the title of the current page
  {
    return coalesce (
      isset($this->activeRoute) ? $this->activeRoute->title : null,
      $this->pageTitle,
      ''
    );
  }

  /**
   * Perform application-specific transformation on data source data before it is
   * stored for use on the view.
   * @param string $dataSourceName
   * @param mixed  $data can be an array or a DataObject
   */
  function interceptViewDataRecord ($dataSourceName, $data)
  {
    // override
  }

  /**
   * Perform application-specific transformation on data source data before it is
   * stored for use on the view.
   * @param string $dataSourceName
   * @param array  $data a sequential array of dictionary arrays
   */
  function interceptViewDataSet ($dataSourceName, array &$data)
  {
    // override
  }

  /**
   * Loads the record with the id specified on from the request URI into the model object.
   *
   * If the URI parameter is empty, the model is returned unmodified.
   *
   * @param DataObject $model
   * @param string     $param The parameter name. As a convention, it is usually `id`.
   * @return DataObject|false The input model on success, `false` if it was not found.
   */
  function loadRequested (DataObject $model, $param = 'id')
  {
    $id = $this->param ($param);
    if (!$id) return $model;
    $f = $model->find ($id);
    return $f ? $model : false;
  }

  function markerHit ($name)
  {
    //Override
  }

  /**
   * Returns the URI parameter with the specified name.
   * @param string $name The parameter name, as specified on the route.
   * @return string
   */
  function param ($name)
  {
    return get ($this->URIParams, $name);
  }

  /**
   * Defines a named data source for the view.
   * @deprecated
   * @see setModel()
   * @param string     $name
   * @param DataSource $data
   * @param boolean    $isDefault
   * @param boolean    $overwrite
   * @throws DataBindingException
   */
  function setDataSource ($name, DataSource $data, $isDefault = false, $overwrite = true)
  {
    $name      = empty($name) ? 'default' : $name;
    $isDefault = $isDefault || $name == 'default';
    $ctx       = $this->context;
    if ($isDefault) {
      if (isset($ctx->dataSources['default']) && !$overwrite)
        throw new DataBindingException(null,
          "The default data source for the page has already been set.\n\nThe current default data source is:\n<pre>$name</pre>");
    }
    $ctx->dataSources[$name] = $data;
  }

  /**
   * Assigns the specified data to a new (or existing) data source with the
   * specified name.
   * @param string $name The data source name.
   * @param mixed  $data An array, object or <i>null</i>.
   */
  function setViewModel ($name, $data)
  {
    $ctx = $this->context;
    if (!isset($data))
      $ctx->dataSources[$name] = new DataSet ();
    else if ($data instanceof DataSource)
      $ctx->dataSources[$name] = $data;
    else if ((is_array ($data) && isset($data[0])) || $data instanceof PDOStatement)
      $ctx->dataSources[$name] = new DataSet($data);
    else $ctx->dataSources[$name] = new DataRecord($data);
  }

  /**
   * Allows access to the components tree generated by the parsing process.
   * Component specific initialization can be performed here before the
   * page is rendered.
   * Override to add extra initialization.
   */
  function setupView ()
  {
    global $application;
    $this->page->title = str_replace ('@', $this->getTitle (), $application->title);
    $this->page->addScript ("$application->frameworkURI/js/engine.js");
    if (isset($this->flashMessage))
      $this->displayStatus ($this->flashMessage['type'], $this->flashMessage['message']);
    $this->page->defaultDataSource =& $this->context->dataSources['default'];
  }

  /**
   * Initializes a data object for a typical GET request.
   * It is initialized either from the database by primary key value, or
   * initialized from values sent with the request itsef.
   * @param DataObject $data
   */
  function standardDataInit (DataObject $data)
  {
    if (isset($data)) {
      if (isset($this->URIParams))
        extend ($data, $this->URIParams);
      if ($data->isInstanceRequested ()) {
        $data->setPrimaryKeyValue ($data->getRequestedPrimaryKeyValue ());
        if (!$data->read ())
          $data->initFromQueryString ();
        return;
      }
      if (!$data->isNew ())
        $data->read ();
      $data->initFromQueryString ();
    }
  }

  final function wasPosted ()
  {
    return $_SERVER['REQUEST_METHOD'] == 'POST';
  }

  protected function afterPageRender ()
  {
    //override
  }

  protected function applyPresets ()
  {
    if (isset($this->preset)) {
      $presets = explode ('&', $this->preset);
      foreach ($presets as $preset) {
        $presetParts = explode ('=', $preset);
        if ($presetParts[1][0] == '{') {
          $field                             = substr ($presetParts[1], 1, strlen ($presetParts[1]) - 2);
          $this->dataItem->{$presetParts[0]} = get ($this->URIParams, $field);
        }
        else $this->dataItem->{$presetParts[0]} = $presetParts[1];
      }
    }
  }

  /**
   * @return bool|string
   * <li> True is a login form should be displayed.
   * <li> False to proceed as a normal request.
   * <li> <code>'retry'</code> to retry GET request by redirecting to same URI.
   */
  protected function authenticate ()
  {
    global $application, $session;
    $authenticate = false;
    if (isset($session) && $application->requireLogin) {
      $this->getActionAndParam ($action, $param);
      $authenticate = true;
      if ($action == 'login') {
        $prevPost = get ($_POST, '_prevPost');
        try {
          $this->login ();
          if ($prevPost)
            $_POST = unserialize (urldecode ($prevPost));
          else $_POST = [];
          $_REQUEST = array_merge ($_POST, $_GET);
          if (empty($_POST))
            $_SERVER['REQUEST_METHOD'] = 'GET';
          if ($this->wasPosted ())
            $authenticate = false; // user is now logged in; proceed as a normal request
          else $authenticate = 'retry';
        } catch (AuthenticationException $e) {
          $this->setStatus (FlashType::WARNING, $e->getMessage ());
          // note: if $prevPost === false, it keeps that value instead of (erroneously) storing the login form data
          if ($action)
            $this->prevPost = isset($prevPost) ? $prevPost : urlencode (serialize ($_POST));
        }
      }
      else {
        $authenticate = !$session->validate ();
        if ($authenticate && $action)
          $this->prevPost = urlencode (serialize ($_POST));
        if ($this->isWebService) {
          $username = get ($_SERVER, 'PHP_AUTH_USER');
          $password = get ($_SERVER, 'PHP_AUTH_PW');
          if ($username) {
            $session->login ($application->defaultLang, $username, $password);
            $authenticate = false;
          }
        }
      }
    }
    return $authenticate;
  }

  protected function autoRedirect ()
  {
    if ($this->isWebService)
      return true;
    if (isset($this->activeRoute))
      $this->gotoModuleIndex ();
    else if (isset($this->indexPage))
      $this->setRedirection (null, $this->indexPage);
    else return false;
    return true;
  }

  protected final function cancelRedirection ()
  {
    $this->redirectURI = null;
  }

  /**
   * Initializes the current page and related info.
   * Usually not overriden,
   * @throws ConfigException
   * @throws FatalException
   * @global Application $application
   */
  protected function configPage ()
  {
    global $application;
    if (isset($application->routingMap)) {
      if (!isset($this->router))
        throw new ConfigException("The module for the current URI is not working properly.<br>You should check the class code.");
      $this->activeRoute = $this->router->activeRoute;
      $this->URIParams   = $this->activeRoute->getURIParams ();
      $this->virtualURI  = $this->router->virtualURI;
    }
  }

  /**
   * Defines the set of fields which will be fetched to a data object from a POST request.
   * All other values on the request will be ignored.
   * @return array If NULL all the data object's fields fields will be fetched.
   */
  protected function defineDataFields ()
  {
    return null;
  }

  /**
   * Loads or generates the view's source markup.
   * <p>Override to manually include the view's source markup.
   * @return bool Usually you should return false. Return <b>true</b> to cancel additional processing beyond this point.
   * @throws FatalException
   * @global Application $application
   */
  protected function defineView ()
  {
    ob_start ();
    $r    = $this->render ();
    $view = ob_get_clean ();
    if ($this->isWebService) {
      $this->beginJSONResponse ();
      if (is_null ($r))
        http_response_code (204);
      else echo json_encode ($r);
      return true;
    }
    if (isset($r)) {
      echo $r;
      return true;
    }
    if (strlen ($view)) {
      $this->parseView ($view);
      return false;
    }
    if (isset($this->router)) {
      if (isset($this->activeRoute->view))
        $this->loadView ($this->activeRoute->view, true);
      return false;
    }
    else {
      preg_match ('#(\w+?)\.php#', $this->URI, $match);
      if (!count ($match))
        throw new FatalException("Invalid URI <b>$this->URI</b>");
      $path = $match[1] . $this->TEMPLATE_EXT;
      return !$this->loadView ($path);
    }
  }

  protected function displayStatus ($status, $message)
  {
    if (!is_null ($status)) {
      if ($this->page)
        switch ($status) {
          case FlashType::FATAL:
            $this->page->fatal ($message);
            break;
          case FlashType::ERROR:
            $this->page->error ($message);
            break;
          case FlashType::WARNING:
            $this->page->warning ($message);
            break;
          default:
            $this->page->info ($message);
        }
      else echo $message;
    }
  }

  /**
   * Invokes the right controller method in response to the POST request's specified action.
   * @param DataObject $data
   * @return ResponseInterface|null
   * @throws FlashMessageException
   * @throws FileException
   */
  protected function doFormAction (DataObject $data = null)
  {
    if (count ($_POST) == 0 && count ($_FILES) == 0)
      throw new FileException(FileException::FILE_TOO_BIG, ini_get ('upload_max_filesize'));
    $this->getActionAndParam ($action, $param);
    $class = new ReflectionObject($this);
    try {
      $method = $class->getMethod ('action_' . $action);
    } catch (ReflectionException $e) {
      throw new FlashMessageException('Class <b>' . $class->getName () . "</b> can't handle action <b>$action</b>.",
        FlashType::ERROR);
    }
    return $method->invoke ($this, $data, $param);
  }

  protected function finalize ()
  {
    //override
  }

  protected function getActionAndParam (&$action, &$param)
  {
    $action = get ($_REQUEST, '_action', '');
    if (preg_match ('#(\w*):(.*)#', $action, $match)) {
      $action = $match[1];
      $param  = $match[2];
    }
    else $param = null;
  }

  protected function gotoModuleIndex ()
  {
    global $application;
    if (isset($this->activeRoute->indexURL))
      $this->thenGoTo ($this->activeRoute->indexURL);
    else {
      /** @var PageRoute $index */
      $index = $this->activeRoute->getIndex ();
      if (!$index)
        $this->thenGoTo ($application->homeURI);
      else $this->thenGoTo ($index->evalURI ($this->URIParams));
    }
  }

  /**
   * Initializes Search Engine Optimization information for the current page.
   * @global Application $application
   */
  protected function initSEO ()
  {
    global $application;
    if (isset($application->routingMap)) {
      if (isset($this->activeRoute->keywords))
        $this->page->keywords =
          isset($this->lang) ? get ($this->activeRoute->keywords, $this->lang, '') : $this->activeRoute->keywords;
      if (isset($this->activeRoute->description))
        $this->page->description =
          isset($this->lang) ? get ($this->activeRoute->description, $this->lang, '') : $this->activeRoute->description;
    }
  }

  /**
   * Creates and configures an instance of the template engine.
   */
  protected function initTemplateEngine ()
  {
    global $application;
    $this->engine = new MatisseEngine;
    $pipeHandler  = clone $application->pipeHandler;
    $pipeHandler->registerFallbackHandler ($this);
    $ctx                      = $this->context = $this->engine->createContext ($application->tags, $pipeHandler);
    $ctx->condenseLiterals    = $application->condenseLiterals;
    $ctx->debugMode           = $application->debugMode;
    $ctx->templateDirectories = $application->templateDirectories;
    $ctx->presets             = map ($application->presets, function ($class) use ($application) { return $application->injector->make ($class); });
    $this->page               = new Page($ctx);
  }

  /**
   * Initializes the controller.
   * Override to implement initialization code that should run before all other processing on the controller.
   * Make sure to always call the parent function.
   * @global Application $application
   */
  protected function initialize ()
  {
  }

  /**
   * Respondes to the standard 'submit' controller action when a primary key value is not present on the request.
   * The default procedure is to create a new record on the database.
   * Override to implement non-standard behaviour.
   * @param DataObject $data
   * @param null       $param
   * @throws Exception
   */
  protected function insertData (DataObject $data, $param = null)
  {
    $data->insert ();
    if ($this->isWebService)
      echo "<pk>{$data->getPrimaryKeyValue()}</pk>";
    if (!$this->autoRedirect ())
      $this->setRedirection ($data->primaryKeyName . '=' .
                             DataObject::getNewPrimaryKeyValue ()); //only for standalone (non module) pages
  }

  /**
   * Should be overriden when submitted data should be preprocessed.
   * @param DataObject $data
   */
  protected function interceptFormData (DataObject $data)
  {
    if (isset($this->URIParams)) {
      extendNonEmpty ($data, $this->URIParams);
    }
  }

  protected function join ($masterSourceName, $slavesBaseName, $masterData, DataObject $slaveTemplate, $joinExpr,
                           $masterKeyField = 'id')
  {
    $ctx = $this->context;
    if (!isset($ctx->dataSources[$masterSourceName]))
      $this->setDataSource ($masterSourceName, new DataSet($masterData));
    foreach ($masterData as &$record) {
      $slaveData    = clone $slaveTemplate;
      $slaveDataSet = new DataSet($slaveData->queryBy ($joinExpr, null, null, [$record[$masterKeyField]]));
      $this->setDataSource ($slavesBaseName . $record[$masterKeyField], $slaveDataSet);
    }
  }

  /**
   * Attempts to load the specified view file.
   * @param string $path
   * @param bool   $errorIfNotFound When true an exception is thrown if the view file is not found, instead of returning
   *                                `false`.
   * @return bool <b>true</b> if the file was found.
   * @throws FatalException
   */
  protected function loadView ($path, $errorIfNotFound = false)
  {
    global $application;
    $dirs = $application->viewsDirectories;
    foreach ($dirs as $dir) {
      $p    = "$dir/$path";
      $view = loadFile ($p);
      if ($view) {
        $this->parseView ($view);
        return true;
      }
    }
//          $path2 = ErrorHandler::shortFileName ($path2);
    if ($errorIfNotFound) {
      $paths = implode ('', map ($dirs, function ($path) {
        return "<li><path>$path</path>";
      }));
      throw new FatalException("View <b>$path</b> was not found.<p>Search paths:<ul>$paths</ul>");
    }
    return false;
  }

  /**
   * This method may be overridden to try/catch login errors.
   * @return void
   */
  protected function login ()
  {
    global $session, $application;
    $session->login ($application->defaultLang);
  }

  /**
   * Override to return the main model for the controller / view.
   *
   * > This model will be available on both GET and POST requests and it will also be set as the default data source
   * for the view model.
   *
   * <p>If not set, the model will be created by the controller by inspecting:
   * - the model property of the current route;
   * - the controller's model property;
   * - the controller's dataClass and modelMethod properties.
   *
   * @return DataObject|PDOStatement|array
   */
  protected function model ()
  {
    return null;
  }

  protected function paginate (array &$data, $pageSize = 0)
  {
    global $application;
    if (!$pageSize)
      $pageSize = $application->pageSize;
    $this->pageNumber = get ($_REQUEST, $application->pageNumberParam, 1);
    $count            = count ($data);
    if ($count > $pageSize) {
      $this->max = ceil ($count / $pageSize);
      if ($this->pageNumber > 1) {
        $skip = $this->getRowOffset ();
        array_splice ($data, 0, $skip);
      }
      array_splice ($data, $pageSize);
    }
  }

  protected function parseView ($viewTemplate)
  {
    $this->engine->parse ($viewTemplate, $this->context, $this->page);
  }

  /**
   * Responds to a POST request.
   * @param DataObject $data
   * @return null|ResponseInterface
   */
  protected final function processForm (DataObject $data = null)
  {
    if (isset($data)) {
      $data->loadFromHttpRequest ($this->defineDataFields ());
      $this->interceptFormData ($data);
    }
    return $this->doFormAction ($data);
  }

  /**
   * Implements page specific action processing, in response to a POST request.
   * To implement standard behavior, override and make a call to $this->processForm($data),
   * where $data is the data object to be processed.
   * If you use the standard dataItem property, there is no need to override this method.
   * @return null|ResponseInterface
   */
  protected function processRequest ()
  {
    if (isset($this->dataItem))
      return $this->processForm ($this->dataItem);
    else return $this->processForm ();
  }

  /**
   * Performs all processing related to the view generation.
   * @param bool $authenticate Is this a login form?
   * @return bool False if the view rendering was interrupted..
   * @throws FatalException
   * @throws FileNotFoundException
   */
  protected function processView ($authenticate = false)
  {
    global $application;
    $this->setupBaseModel ();
    if (!$authenticate) {
      // Normal page rendering (not a login form).

      $this->setupViewModel (); //custom setup
      $this->setViewModel ('page', $this->page);
      if ($this->defineView ())
        return false;
    }
    else {
      if ($this->isWebService) {
        http_response_code (401);
        header ('WWW-Authenticate: Basic');
        echo "Unauthorized";
        return true;
      }
      // Show login form.
      $path = $application->loginView;
      $this->loadView ($path, true);
      $this->setViewModel ('page', $this->page);
      $this->page->formAutocomplete = true;
    }
    $this->initSEO ();
    $this->setupView ();
    $output = $this->renderView ();
    echo $output;
    $this->afterPageRender ();
    return true;
  }

  protected function redirectAndHalt ()
    // override to implement actions to be performed before a redirection takes place
  {
    if (isset($this->redirectURI))
      self::redirect ($this->redirectURI);
  }

  /**
   * Allows subclasses to generate the view's markup dinamically.
   * If noting is sent to the output buffer from this method, the controller will try to load the view from metadata.
   * @return mixed If a return value is provided, it is assumed to be web service data and it will be output instead of
   * the output buffer content, without any further processing.
   * Note: if `isWebService` is enabled, the return value will be always used (even if null) and it will be encoded as
   * JSON.
   */
  protected function render ()
  {
    // Override
  }

  /**
   * Renders the components tree.
   */
  protected final function renderView ()
  {
    return $this->engine->render ($this->page);
  }

  /**
   * Generates a response to a GET request when viewProcessing = false.
   */
  protected function respond ()
  {
    //override if required
  }

  protected function saveData ($data = null)
  {
    if (!isset($data))
      $data = $this->dataItem;
    if (isset($data) && $data->isModified ())
      $this->action_submit ($data);
  }

  protected final function setRedirection ($redirectArgs = null, $redirectURI = null)
  {
    if (isset($redirectURI)) {
      if (isset($redirectArgs))
        $this->redirectURI = $redirectURI . '?' . $redirectArgs;
      else $this->redirectURI = $redirectURI;
    }
    else if (isset($redirectArgs))
      $this->redirectURI = $this->getPageURI () . '?' . $redirectArgs;
    else $this->redirectURI = $_SERVER['REQUEST_URI'];
  }

  protected final function setStatus ($type, $msg)
  {
    throw new FlashMessageException ($msg, $type);
  }

  protected final function setStatusFromException (FlashMessageException $e)
  {
    $_SESSION['formStatus'] = $e->getCode ();
    if ($e->getCode () != FlashType::FATAL)
      $_SESSION['formMessage'] = $e->getMessage ();
    else {
      $msg = "{$e->getMessage()}\n\nOn {$e->getFile()}, line {$e->getLine()}\nStack trace:\n";
      $msg .= preg_replace ('/#\d/', '<li>', $e->getTraceAsString ());
      /*
            foreach($e->getTrace() as $trace)
            {
                    $msg.='<li>'.$trace['function'].'()';//.implode(',',$trace['args']).')';
                    $msg.="<br>at {$trace['file']}, line {$trace['line']}.</li>";
            }*/
      $_SESSION['formMessage'] = $msg;
    }
  }

  /**
   * Sets up a set of standard data sources which are available for databinding on all the application's views.
   * When overriden the parent class method should always be called.
   */
  protected function setupBaseModel ()
  {
    global $application, $session;
    $_SESSION['isValid'] = isset($session) && $session->isValid;
    $this->setDataSource ('application', new DataRecord($application));
    $this->setDataSource ('session', new DataRecord($_SESSION));
    if (isset($session)) {
      $this->setDataSource ('user', new DataRecord ($session->user));
      $this->setDataSource ('sessionInfo', new DataRecord($session));
    }
    $this->setDataSource ('controller', new DataRecord($this));
    $this->setDataSource ('request', new DataRecord($_REQUEST));
    if (isset($this->activeRoute)) {
      $this->setDataSource ('sitePage', new DataRecord($this->activeRoute));
      $this->setDataSource ('config', new DataRecord($this->activeRoute->config));
    }
    if (isset($this->router))
      $this->setDataSource ('module', new DataRecord($this->router->moduleInfo));
    $this->setDataSource ('languages', new DataSet(isset($this->langInfo) ? array_values ($this->langInfo) : null));
    $this->setDataSource ("URIParams", new DataRecord($this->URIParams));
  }

  protected function setupController ()
  {
    date_default_timezone_set ('Europe/Lisbon');
  }

  /**
   * Sets up a page specific data model for use on the processRequest() phase and/or on the processView() phase.
   *
   * Override this if you want to manually specify the model.
   * - The model is saved on `$this->dataItem`.
   * - Do not try to modify the default data source here, as it will be overridden with the value of `$this->dataItem`
   * later on.
   */
  protected function setupModel ()
  {
    global $model, $lastModel;
    $mod   = $this->model ();
    $model = $lastModel;
    if (isset($mod)) {
      if ($mod instanceof DataObject) {
        $this->dataItem = $mod;
        $this->applyPresets ();
        if (isset($this->activeRoute) && $this->activeRoute->autoloadModel)
          $this->standardDataInit ($mod);
        return;
      }
      $this->modelData = $mod;
      return;
    }

    if (isset($this->activeRoute)) {
      $thisModel = $this->activeRoute->getModel ();

      if (!empty($thisModel)) {
        list ($this->dataClass, $this->modelMethod) = parseMethodRef ($thisModel);
        $this->dataItem = new $this->dataClass;
        if (!isset($this->dataItem))
          throw new ConfigException("<p><b>Model class not found.</b>
  <li>Class:         <b>$this->dataClass</b>
  <li>Active module: <b>{$this->activeRoute->module}</b>
");
        $this->applyPresets ();
        if ($this->activeRoute->autoloadModel)
          $this->standardDataInit ($this->dataItem);
        return;
      }
    }

    if (isset($this->model))
      list ($this->dataClass, $this->modelMethod) = parseMethodRef ($this->model);

    if (isset($this->dataClass)) {
      if (!class_exists ($this->dataClass))
        throw new ConfigException ("Model not found: '<b>$this->dataClass</b>'<p>For controller: <b>" .
                                   get_class ($this) . '</b>');
      $this->dataItem = new $this->dataClass;
    }

    if (isset($this->dataItem)) {
      $this->applyPresets ();
      if ($this->autoloadModel)
        $this->standardDataInit ($this->dataItem);
    }
  }

  /**
   * Installs the module on the application.
   * Performs module initialization operations, including the creation of tables
   * on the database if they are not defined yet.
   * This method is called only when the user manually requests an application
   * configuration re-check.
   */
  protected function setupModule ()
  {
    //Override
  }

  /**
   * Sets up page specific data sources for use on the processView() phase only.
   *
   * Models for use on the processRequest() phase should be defined on setupModel().
   * Override to provide specific functionality.
   * If <code>dataItem</code> is set, the default action is to create a default
   * data source with either a single record (if the primary key has a value)
   * or with a default list (if the primary key has no value).
   */
  protected function setupViewModel ()
  {
    global $application;

    //Initialize data sources defined on the sitemap
    if (isset($this->activeRoute)) {
      if (isset($this->activeRoute->dataSources))
        foreach ($this->activeRoute->dataSources as $name => $dataSourceInfo)
          $this->setDataSource ($name, $dataSourceInfo->getData ($this, $name)); //interception is done inside getData()
    }

    $vm = $this->viewModel ();
    if ($vm) {
      if (is_array ($vm))
        foreach ($vm as $k => $v)
          $this->setViewModel ($k, $v);
      else throw new \RuntimeException ("Invalid view model");
    }

    if (isset($this->modelData)) {
      $this->setViewModel ('default', $this->modelData ?: null); // empty arrays are converted to null.
      return;
    }

    $ctx              = $this->context;
    $this->pageNumber = get ($_REQUEST, $application->pageNumberParam, 1);
    if (isset($this->activeRoute)) {
      if (isset($this->dataItem)) {
        if ($this->activeRoute->format == 'grid' && $this->dataItem->isNew ()) {
          if ($this->modelMethod)
            $st = $this->dataItem->{$this->modelMethod}();
          else $st =
            $this->dataItem->queryBy ($this->activeRoute->filter, $this->activeRoute->fieldNames,
              $this->activeRoute->sortBy);
          $data = $st instanceof PDOStatement ? $st->fetchAll (PDO::FETCH_ASSOC) : $st;
          $this->paginate ($data);
          $this->interceptViewDataSet ('default', $data);
          $this->setDataSource ('', new DataSet($data), true);
        }
        else {
          $this->interceptViewDataRecord ('default', $this->dataItem);
          $this->setDataSource ('', new DataRecord($this->dataItem), true);
        }
      }
    }
    else if (isset($this->dataItem))
      switch ($this->defaultPageFormat) {
        case 'grid':
          if (isset($this->dataQueryParams)) {
            $params = [];
            foreach ($this->dataQueryParams as $param) {
              if ($param[0] == '{') {
                $tmp = explode ('.', substr ($param, 1, -1));
                if (count ($tmp)) {
                  $dataSource = substr ($tmp[0], 1);
                  $dataField  = $tmp[1];
                }
                else {
                  $dataSource = 'default';
                  $dataField  = $tmp[0];
                }
                $ds       = get ($ctx->dataSources, $dataSource);
                $it       = $ds->getIterator ()->current ();
                $params[] = isset($ds) ? get ($it, $dataField) : null;
              }
              else $params[] = $param;
            }
          }
          else $params = null;
          if ($this->modelMethod)
            $st = $this->dataItem->{$this->modelMethod}();
          else $st = $this->dataItem->queryBy ($this->dataFilter, $this->dataFields, $this->dataSortBy, $params);
          $data = $st->fetchAll (PDO::FETCH_ASSOC);
          $this->interceptViewDataSet ('default', $data);
          $this->paginate ($data);
          $this->setDataSource ('', new DataSet($data));
          break;
        default:
          $this->interceptViewDataRecord ('default', $this->dataItem);
          $this->setDataSource ('', new DataRecord($this->dataItem));
          break;
      }
  }

  protected final function thenGoTo ($virtualURI, $redirectArgs = null)
  {
    $this->redirectURI = $this->modPathOf ($virtualURI, $redirectArgs);
  }

  protected final function thenGoToSelf ($redirectArgs = null)
  {
    //$x = explode('?',$this->URI);
    //$args = count($x) > 1 ? $x[1] : '';
    //$this->redirectURI = $x[0].'?'.$args.(isset($redirectArgs) ? "&$redirectArgs" : '');
  }

  /**
   * Respondes to the standard 'submit' controller action when a primary key value is present on the request.
   * The default procedure is to save the object to the database.
   * Override to implement non-standard behaviour.
   * @param DataObject $data
   * @param null       $param
   * @throws Exception
   */
  protected function updateData (DataObject $data, $param = null)
  {
    $data->update ();
    $this->autoRedirect ();
  }

  /**
   * Override to set addition models for the controller / view.
   *
   * > View models are available only on GET requests.
   *
   * @return array|void If you return an array, the keys will be set as datasource names.
   */
  protected function viewModel ()
  {
    //Override.
    return null;
  }

}
