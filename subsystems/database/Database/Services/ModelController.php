<?php
namespace Electro\Database\Services;

use Electro\Database\Lib\AbstractModelController;
use Electro\Interfaces\SessionInterface;
use PhpKit\ExtPDO\ExtPDO;
use PhpKit\ExtPDO\Interfaces\ConnectionInterface;

class ModelController extends AbstractModelController
{
  /** @var ExtPDO */
  private $pdo;
  /** @var string A field name. */
  private $primaryKey;

  public function __construct (SessionInterface $session, ConnectionInterface $connection)
  {
    parent::__construct ($session);
    $driver = $connection->driver ();
    if ($driver && $driver != 'none')
      $this->pdo = $connection->getPdo ();
  }

  function loadData ($collection, $subModelPath = '', $id = null, $primaryKey = 'id')
  {
    $id                = $id ?: $this->requestedId;
    $this->requestedId = $id;
    $primaryKey        = $primaryKey ?: $this->primaryKey;
    $this->primaryKey  = $primaryKey;

    $data = $this->sql->query ("SELECT * FROM $collection WHERE $primaryKey=?", [$id])->fetch ();
    if ($subModelPath === '')
      $this->model = $data;
    else setAt ($this->model, $subModelPath, $data);
    return $data;
  }

  function loadModel ($modelClass, $subModelPath = '', $id = null)
  {
    // Does nothing; this implementation (obviously) does not support an ORM.
  }

  function withRequestedId ($routeParam = 'id', $primaryKey = null)
  {
    $this->requestedId = $this->request->getAttribute ("@$routeParam");
    $this->primaryKey  = $primaryKey ?: 'id';
    return $this;
  }

  /**
   * Override to provide an implementation of beginning a database transaction.
   */
  protected function beginTransaction ()
  {
    $this->pdo->beginTransaction ();
  }

  /**
   * Override to provide an implementation of a database transaction commit.
   */
  protected function commit ()
  {
    $this->pdo->commit ();
  }

  /**
   * Override to provide an implementation of a database transaction rollback.
   */
  protected function rollback ()
  {
    $this->pdo->rollBack ();
  }

  /**
   * Attempts to save the given model on the database.
   *
   * <p>If the model type is unsupported by the specific controller implementation, the method will do nothing and
   * return `false`.
   * > <p>This is usually only overriden by controller subclasses that implement support for a specific ORM.
   *
   * @param mixed $model
   * @param array $options Driver/ORM-specific options.
   * @return bool true if the model was saved.
   */
  protected function save ($model, array $options = [])
  {
    // Does nothing; there's no automated saving support on this implementation yet.
    return null;
  }

}
