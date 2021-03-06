<?php
namespace Electro\Database\Lib;

use Electro\Exceptions\FatalException;
use Electro\Http\Lib\Http;
use Electro\Interfaces\ModelControllerExtensionInterface;
use Electro\Interfaces\ModelControllerInterface;
use Electro\Interfaces\SessionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Selenia\Platform\Config\PlatformModule;

abstract class AbstractModelController implements ModelControllerInterface
{
  /**
   * @var array|object
   */
  protected $model = [];
  /**
   * @var string A dot-separated path to the root/main model.
   * @see setModelRootPath
   */
  protected $modelRootPath = 'model';
  /**
   * @var array|null If null, all route parameters are used as presets.
   */
  protected $presets = null;
  /**
   * @var ServerRequestInterface
   */
  protected $request;
  /**
   * @var mixed
   */
  protected $requestedId = null;
  /**
   * @var string[]|callable[]|ModelControllerExtensionInterface[]
   */
  private $extensions = [];
  /**
   * A pipeline of operations to be performed after saving the model.
   * At this point, the transaction has already been commited, so no further database operations should be performed.
   * You can, though, do other kinds of cleanup operations, like deleting files, for instance.
   *
   * @var callable[]
   */
  private $handlersForPostSave = [];
  /**
   * A pipeline of operations to be performed on the model before saving it.
   * At this point, the transaction has not yet began, so no database operations should be performed yet.
   * You can, though, do other kinds of operations, like preparing uploaded files, for instance.
   *
   * @var callable[]
   */
  private $handlersForPreSave = [];
  /**
   * A pipeline of operations to save the model.
   * These are all performed within a database transaction.
   * You mayn prepend callbacks, to perform database operations before the model is saved, or you may append callbacks,
   * to perform operations after the model is saved. You may also replace the default model saver.
   *
   * @var callable[]
   */
  private $handlersForSave;
  /**
   * @var callable|null
   */
  private $overrideDefaultHandler = null;
  /**
   * @var SessionInterface
   */
  private $session;

  function __construct (SessionInterface $session)
  {
    $this->session         = $session;
    $this->handlersForSave = [[$this, 'builtInSaveHandler']];
  }

  abstract function loadData ($collection, $subModelPath = '', $id = null, $primaryKey = 'id');

  abstract function loadModel ($modelClass, $subModelPath = '', $id = null);

  /**
   * Override to provide an implementation of setting the primary key's name and value for subsequent model load
   * operations.
   * @inheritdoc
   */
  abstract function withRequestedId ($routeParam = 'id', $primaryKey = null);

  function get ($path)
  {
    $root = "$this->modelRootPath.";
    $p    = strlen ($root);
    return str_beginsWith ($path, $root)
      ? getAt ($this->model, substr ($path, $p))
      : ($path == $this->modelRootPath ? $this->model : null);
  }

  function getModel ($subModelPath = '')
  {
    if ($subModelPath === '')
      return $this->model;
    else return getAt ($this->model, $subModelPath);
  }

  function setModel ($data, $subModelPath = '')
  {
    if ($subModelPath === '')
      $this->model = $data;
    else setAt ($this->model, $subModelPath, $data);
  }

  function getRequest ()
  {
    return $this->request;
  }

  function setRequest (ServerRequestInterface $request)
  {
    $this->request = $request;
  }

  function getTarget ($path)
  {
    $z          = explode ('.', $path);
    $prop       = array_pop ($z);
    $targetPath = implode ('.', $z);
    return [$this->get ($targetPath), $prop];
  }

  function handleRequest ()
  {
    $request = $this->request;

    // Merge route parameters.
    $rp = Http::getRouteParameters ($request);
    if ($rp) {
      $o = [];
      $presets = isset($this->presets) ? $this->presets : array_keys ($rp);
      foreach ($presets as $k => $v) {
        if (is_integer ($k))
          $k = $v;
        $o[$v] = get ($rp, $k);
      }
      $this->merge ($o);
    }

    switch ($request->getMethod ()) {

      case 'GET':
        $old = $this->session->getOldInput ();
        if ($old) {
          $data = [];
          foreach ($old as $k => $v)
            $data[str_replace ('model/', '', $k)] = $v;
          $this->merge ($this->parseFormData ($data));
        }
        break;

      case 'POST':
        $data = $request->getParsedBody ();
        if (isset($data)) {

          $contentType = $request->getHeaderLine ('Content-Type');
          if ($contentType == 'application/x-www-form-urlencoded' ||
              str_beginsWith ($contentType, 'multipart/form-data')
          )
            $data = $this->parseFormData ($data);

          unset ($data[PlatformModule::ACTION_FIELD]);
          $this->merge ($data);
        }
    }
    $this->runExtensions ();
  }

  function merge (array $data = null)
  {
    if (is_null ($data) || is_null ($this->model)) return;
    $data = array_normalizeEmptyValues ($data, true);
    if (is_array ($this->model))
      array_recursiveMergeInto ($this->model, $data);
    else if (is_object ($this->model))
      extend ($this->model, $data);
    else throw new FatalException (sprintf ("Can't merge data into a model of type <kbd>%s</kbd>",
      gettype ($this->model)));
  }

  function onAfterSave (callable $task)
  {
    $this->handlersForPostSave[] = $task;
  }

  function onBeforeSave (callable $task)
  {
    $this->handlersForPreSave[] = $task;
  }

  function onSave ($priority, callable $task)
  {
    if (!$priority)
      $this->overrideDefaultHandler = $task;
    elseif ($priority > 0)
      array_unshift ($this->handlersForSave, $task);
    else $this->handlersForSave[] = $task;
  }

  function preset (array $presets)
  {
    $this->presets = $presets;
    return $this;
  }

  function registerExtension ($extension)
  {
    $this->extensions[] = $extension;
  }

  function saveModel ()
  {
    $this->callEventHandlers ($this->handlersForPreSave);
    $this->beginTransaction ();
    try {
      $this->callEventHandlers ($this->handlersForSave);
      $this->commit ();
    }
    catch (\Exception $e) {
      $this->rollback ();
      throw $e;
    }
    $this->callEventHandlers ($this->handlersForPostSave);
  }

  function set ($path, $value)
  {
    $root = "$this->modelRootPath.";
    $p    = strlen ($root);
    if (str_beginsWith ($path, $root))
      setAt ($this->model, substr ($path, $p), $value);
    return $this;
  }

  function setModelRootPath ($path)
  {
    $this->modelRootPath = $path;
  }

  /**
   * Override to provide an implementation of beginning a database transaction.
   */
  abstract protected function beginTransaction ();

  /**
   * Override to provide an implementation of a database transaction commit.
   */
  abstract protected function commit ();

  /**
   * Override to provide an implementation of a database transaction rollback.
   */
  abstract protected function rollback ();

  /**
   * Attempts to save the given model on the database.
   *
   * <p>If the model type is unsupported by the specific controller implementation, the method will do nothing and
   * return `null`.
   * <p>If the model could not be save due to another reason, the method should returm `false`.
   * > <p>This is usually only overriden by controller subclasses that implement support for a specific ORM.
   *
   * @param mixed $model
   * @return bool|null true if the model was saved.
   */
  abstract protected function save ($model);

  /**
   * Saves all sub-models on the model that can be automatically saved.
   *
   * <p>Although not common, you may override this if the model has some unsupported format that can not be handled by
   * the default implementation. Arrays of models and objects with models on public properties are supported.
   *
   * @return bool
   */
  protected function saveCompositeModel ()
  {
    foreach ($this->model as $submodel)
      if (!$this->save ($submodel))
        return false;
    return true;
  }

  private function builtInSaveHandler ()
  {
    $def = $this->overrideDefaultHandler;
    if ($def)
      return $def ($this);

    $model = $this->model;
    $s     = $this->save ($model);
    if (is_null ($s))
      $s = $this->saveCompositeModel ();
    return $s;
  }

  private function callEventHandlers (array $handlers)
  {
    array_walk ($handlers, function ($fn) {
      return $fn ($this);
    });
  }

  /**
   * Constructs a tree-like structure from flat form-submitted data.
   *
   * > <p>**Note:** keys with slashes are expanded into nested arrays.
   *
   * @param array $data
   * @return array
   */
  private function parseFormData (array $data)
  {
    $o    = [];
    $root = "$this->modelRootPath.";
    $p    = strlen ($root);
    foreach ($data as $k => $v) {
      $k = str_replace ('/', '.', $k);
      if (str_beginsWith ($k, $root))
        setAt ($o, substr ($k, $p), $v, true);
    }
    return $o;
  }

  private function runExtensions ()
  {
    foreach ($this->extensions as $ext) {
      if (is_string ($ext))
        $extension = new $ext;
      elseif (is_callable ($ext))
        $extension = $ext ();
      else $extension = $ext;
      $extension->modelControllerExtension ($this);
    }
  }

}
