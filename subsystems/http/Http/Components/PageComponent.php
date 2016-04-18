<?php
namespace Selenia\Http\Components;

use Exception;
use PhpKit\WebConsole\DebugConsole\DebugConsole;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionObject;
use ReflectionProperty;
use Selenia\Application;
use Selenia\Exceptions\Fatal\DataModelException;
use Selenia\Exceptions\FatalException;
use Selenia\Exceptions\Flash\FileException;
use Selenia\Exceptions\FlashMessageException;
use Selenia\Exceptions\FlashType;
use Selenia\Http\Lib\Http;
use Selenia\Interfaces\Http\RedirectionInterface;
use Selenia\Interfaces\Http\RequestHandlerInterface;
use Selenia\Interfaces\InjectorInterface;
use Selenia\Interfaces\ModelControllerInterface;
use Selenia\Interfaces\Navigation\NavigationInterface;
use Selenia\Interfaces\Navigation\NavigationLinkInterface;
use Selenia\Interfaces\SessionInterface;
use Selenia\Matisse\Components\Base\Component;
use Selenia\Matisse\Components\Base\CompositeComponent;
use Selenia\Matisse\Parser\DocumentContext;
use Selenia\Matisse\Parser\Expression;
use Selenia\Traits\PolymorphicInjectionTrait;

/**
 * The base class for components that are web pages.
 */
class PageComponent extends CompositeComponent implements RequestHandlerInterface
{
  use PolymorphicInjectionTrait;

  const isRootComponent = true;

  /**
   * @var Application
   */
  public $app;
  /**
   * The link that matches the current URL.
   *
   * @var NavigationLinkInterface
   */
  public $currentLink;
  /**
   * @var int Maximum number of pages.
   */
  public $max = 1;
  /**
   * @var array|Object The page's data model.
   */
  public $model;
  /**
   * @var NavigationInterface
   */
  public $navigation;
  /**
   * @var int Current page number.
   */
  public $pageNumber = 1;
  /**
   * If set, defines the page title. It will generate a document `<title>` and it can be used on
   * breadcrumbs.
   *
   * <p>Use this, instead of `title` to manually set the page title.
   *
   * @var string
   */
  public $pageTitle = null;
  /**
   * @var ServerRequestInterface This is always available for page components, and it is not injected.
   */
  public $request;
  /**
   * @var SessionInterface
   */
  public $session;
  /**
   * The current request URI without the page number parameters.
   * This property is useful for databing with the expression {!controller.URI_noPage}.
   *
   * @var string
   */
  protected $URI_noPage;
  /**
   * When true and `$indexPage` is not set, upon a POST the page will redirect to the parent navigation link.
   *
   * @var bool
   */
  protected $autoRedirectUp = false;
  /**
   * Specifies the URL of the index page, to where the browser should navigate upon the
   * successful insertion / update / deletion of records.
   * If not defined on a subclass then the request will redisplay the same page.
   *
   * @var string
   */
  protected $indexPage = null;
  /**
   * @var ModelControllerInterface
   */
  protected $modelController;
  /**
   * @var RedirectionInterface
   */
  protected $redirection;
  /**
   * If set to true, the view will be rendered on the POST request without a redirection taking place.
   *
   * @var bool
   */
  protected $renderOnAction = false;
  /**
   * @var ResponseInterface
   */
  protected $response;
  /**
   * @var InjectorInterface
   */
  private $injector;

  function __construct (InjectorInterface $injector, Application $app,
                        RedirectionInterface $redirection, SessionInterface $session, NavigationInterface $navigation,
                        ModelControllerInterface $modelController)
  {
    parent::__construct ();

    $this->injector        = $injector;
    $this->app             = $app;
    $this->redirection     = $redirection;
    $this->session         = $session;
    $this->navigation      = $navigation;
    $this->modelController = $modelController;

    // Inject extra dependencies into the subclasses' inject methods, if one or more exist.

    $this->polyInject ();
  }

  function __debugInfo ()
  {
    // Exclude inherited properties.

    $baseProps = map ((new ReflectionClass(CompositeComponent::class))->getProperties (ReflectionProperty::IS_PUBLIC),
      function (ReflectionProperty $p) { return $p->getName (); });
    $allProps  = map ((new ReflectionClass($this))->getProperties (ReflectionProperty::IS_PUBLIC),
      function (ReflectionProperty $p) { return $p->getName (); });
    $ownProps  = array_diff ($allProps, $baseProps);
    return object_only ($this, $ownProps);
  }

  /**
   * Performs the main execution sequence.
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface      $response
   * @param callable               $next
   * @return ResponseInterface
   * @throws FatalException
   * @throws FileException
   * @throws FlashMessageException
   */
  function __invoke (ServerRequestInterface $request, ResponseInterface $response, callable $next)
  {
    if (!$this->app)
      throw new FatalException("Class <kbd class=type>" . get_class ($this) .
                               "</kbd>'s constructor forgot to call <kbd>parent::__construct()</kbd>");
    $this->request  = $request;
    $this->response = $response;
    $this->redirection->setRequest ($request);
    $this->currentLink = $this->navigation->request ($this->request)->currentLink ();
    $this->navigation->getCurrentTrail ();
    if (!$this->indexPage && $this->autoRedirectUp && $this->currentLink && $parent = $this->currentLink->parent ())
      $this->indexPage = $parent->url ();

    // remove page number parameter
    $this->URI_noPage =
      preg_replace ('#&?' . $this->app->pageNumberParam . '=\d*#', '', $this->request->getUri ()->getPath ());
    $this->URI_noPage = preg_replace ('#\?$#', '', $this->URI_noPage);

    $this->initialize (); //custom setup

    $this->modelController->setRequest ($request);
    $this->model ();
    $this->modelController->handleRequest ();
    $this->model = $this->modelController->getModel ();

    switch ($this->request->getMethod ()) {
      /** @noinspection PhpMissingBreakStatementInspection */
      case 'POST':
        // Perform the requested action.
        $res = $this->doFormAction ();
        if ($res) {
          if (!$res instanceof ResponseInterface)
            throw new FatalException (sprintf ("Invalid HTTP response type: %s<p>Expected: <kbd>%s</kbd>",
              typeInfoOf ($res), formatClassName (ResponseInterface::class)));
          $response = $res;
        }
        if (!$this->renderOnAction) {
          if (!$res)
            $response = $this->autoRedirect ();
          break;
        }
      case 'GET':
        // Render the component.
        $out = $this->getRendering ();
        $response->getBody ()->write ($out);
    }
    $this->finalize ($response);
    return $response;
  }

  /**
   * Responds to the standard 'delete' controller action.
   * The default procedure is to delete the object on the database.
   * Override to implement non-standard behaviour.
   *
   * @param null $param
   * @return ResponseInterface
   * @throws FlashMessageException
   * @throws DataModelException
   * @throws Exception
   * @throws FatalException
   */
  function action_delete ($param = null)
  {
    if (!isset($this->model))
      throw new FlashMessageException('Can\'t delete a NULL model.', FlashType::ERROR);
    throw new FlashMessageException(sprintf ('Can\'t automatically delete object of type <kbd>%s</kbd>',
      gettype ($this->model)), FlashType::ERROR);
  }

  /**
   * Allows processing on the server side to occur and redraws the current page.
   * This is useful, for instance, for updating a form by submitting it without actually saving it.
   * The custom processing will usually take place on the render() or the viewModel() methods, but you may also
   * override this method; just make sure you call the inherited one.
   *
   * @param string $param     A JQuery selector for the element that should automatically receive focus after the page
   *                          reloads.
   */
  function action_refresh ($param = null)
  {
    $this->renderOnAction = true;
    if ($param)
      $this->context->getAssetsService ()->addInlineScript ("$('$param').focus()");
  }

  /**
   * Responds to the standard 'submit' controller action.
   * The default procedure is to throw an error message.
   * Override to implement the desired behaviour.
   *
   * @param null $param
   * @throws FlashMessageException
   */
  function action_submit ($param = null)
  {
    if (!isset($this->model))
      throw new FlashMessageException('Can\'t insert/update a NULL model.', FlashType::ERROR);

    throw new FlashMessageException('Can\'t automatically insert/update an object of type ' . gettype ($this->model),
      FlashType::ERROR);
  }

  function setupView ()
  {
    parent::setupView ();

    $this->context->getAssetsService ()->addScript ("{$this->app->frameworkURI}/js/engine.js");
    $this->context->getFilterHandler ()->registerFallbackHandler ($this);

    $title           = $this->getTitle ();
    $this->pageTitle = exists ($title) ? str_replace ('@', $title, $this->app->title) : $this->app->appName;
    foreach ($this->app->assets as $url) {
      $p = strrpos ($url, '.');
      if (!$p) continue;
      $ext = substr ($url, $p + 1);
      switch ($ext) {
        case 'css':
          $this->context->getAssetsService ()->addStylesheet ($url);
          break;
        case 'js':
          $this->context->getAssetsService ()->addScript ($url);
          break;
      }
    }
  }

  protected function afterRender ()
  {
    parent::afterRender ();
    if ($this->getShadowDOM ()) {
      //----------------------------------------------------------------------------------------
      // View Model panel
      // (MUST run before the DOM panel to capture the data-binding stack at its current state)
      //----------------------------------------------------------------------------------------
      if (DebugConsole::hasLogger ('view') && isset($this->viewModel->context)) {

        $VMFilter = function ($k, $v, $o) {
          if (
            $v instanceof DocumentContext ||
            $v instanceof Component ||
            $k === 'parent' ||
            $k === 'viewModel'
          ) return '...';
          return true;
        };
        $expMap   = Expression::$inspectionMap;
        ksort ($expMap);

        DebugConsole::logger ('view')
                    ->withFilter ($VMFilter, $this->viewModel->context)
                    ->write ('<#section|Compiled expressions>')
                    ->inspect ($expMap)
                    ->write ('</#section>');
      }

      //-----------
      // DOM panel
      //-----------
      if (DebugConsole::hasLogger ('DOM')) {
        $insp = $this->inspect (true);
        DebugConsole::logger ('DOM')->write ($insp);
      }

      if (DebugConsole::hasLogger ('model')) {

        $VMFilter = function ($k, $v, $o) {
          if ($v instanceof Application ||
              $v instanceof NavigationInterface ||
              $v instanceof NavigationLinkInterface ||
              $v instanceof SessionInterface ||
              $v instanceof ServerRequestInterface ||
              $v instanceof DocumentContext ||
              $v instanceof Component ||
              $k === 'viewModel' ||
              $k === 'model'
          ) return '...';
          return true;
        };

        DebugConsole::logger ('model')
                    ->write ("<#section|MODEL>")
                    ->inspect ($this->viewModel ? $this->viewModel->model : null)
                    ->write ("</#section><#section|VIEW MODEL>")
                    ->withFilter ($VMFilter, $this->context->getViewModel ())
                    ->write ("</#section>");
      }
    }
  }

  protected function autoRedirect ()
  {
    if (isset($this->indexPage))
      return $this->redirection->to ($this->indexPage);

    return $this->redirection->refresh ();
  }

  /**
   * Invokes the right controller method in response to the POST request's specified action.
   *
   * @return ResponseInterface|null
   * @throws FlashMessageException
   * @throws FileException
   */
  protected function doFormAction ()
  {
//    if (count ($_POST) == 0 && count ($_FILES) == 0)
//      throw new FileException(FileException::FILE_TOO_BIG, ini_get ('upload_max_filesize'));
    $this->getActionAndParam ($action, $param);
    $class = new ReflectionObject ($this);
    try {
      $method = $class->getMethod ('action_' . $action);
    }
    catch (ReflectionException $e) {
      throw new FlashMessageException('Class <b>' . $class->getName () . "</b> can't handle action <b>$action</b>.",
        FlashType::ERROR);
    }
    return $method->invoke ($this, $param);
  }

  /**
   * Override to do something after the response has been generated.
   *
   * @param ResponseInterface $response
   */
  protected function finalize (ResponseInterface $response)
  {
    // no op
  }

  /**
   * Utility method for retrieving the value of a form field submitted via a `application/x-www-form-urlencoded` or a
   * `multipart/form-data` POST request.
   *
   * @param string $name
   * @param        mixed [optional] $def
   * @return mixed
   */
  protected function formField ($name, $def = null)
  {
    return Http::field ($this->request, $name, $def);
  }

  protected function getActionAndParam (&$action, &$param)
  {
    $action = get ($_REQUEST, Http::ACTION_FIELD, '');
    if (preg_match ('#(\w*):(.*)#', $action, $match)) {
      $action = $match[1];
      $param  = $match[2];
    }
    else $param = null;
  }

  protected function getTitle ()
    // override to return a dynamic title for the current page
  {
    return coalesce (
      $this->pageTitle,
      ($link = $this->navigation->selectedLink ()) ? $link->title () : null
    );
  }

  /**
   * Initializes the controller.
   * Override to implement initialization code that should run before all other processing on the controller.
   * Make sure to always call the parent function.
   */
  protected function initialize ()
  {
  }

  /**
   * Override to set the model for the controller / view.
   *
   * > <p>This model will be available on all request methods (GET, POST, etc) and it will also be set as the 'model'
   * property of the view model.
   *
   * <p>You should set the model on the component's {@see $modelController}, using one of these methods: `setModel()`,
   * `loadModel()` or `loadRequested()`.
   *
   * @return void
   */
  protected function model ()
  {
    // override
  }

  protected function viewModel ()
  {
    // Defaults the view model to the component itself.
    if (!isset($this->viewModel))
      $this->viewModel = $this;
  }

}
