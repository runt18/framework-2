<?php
class DataSourceFormat {
  const DATA_SET    = 'dataset';
  const DATA_RECORD = 'record';
}

class DataSourceInfo extends Object {
  public $format; //enum DataSourceFormat
  public $model;
  public $preset;
  public $filter = '';
  public $key = 'id';
  public $value = null;
  public $limit;
  public $pageParam = 'p';
  public $pageSize;
  public $sortBy = '';
  /**
   * SQL field list. If not an empty string, this SQL expression will be used
   * to select fields on queries to the database. Subselects can be used to
   * emulate joins.
   * @var string
   */
  public $fields = '';

  private $dataItem;

  public function getTypes() {
    return array(
      'format'    => 'string',
      'model'     => 'string',
      'filter'    => 'string',
      'preset'    => 'string',
      'key'       => 'string',
      'value'     => 'string',
      'limit'     => 'integer',
      'pageParam' => 'string',
      'pageSize'  => 'integer',
      'sortBy'    => 'string',
      'fields'    => 'string'
    );
  }

  public function __construct(array $init) {
    parent::__construct($init);
  }

  public function createDataItem() {
    global $model;
    if (!isset($this->model))
      throw new ConfigException("Property <b>model</b> is required on a DataSourceInfo instance.");
    $modelItem = get($model,$this->model);
    if (!isset($modelItem))
      throw new ConfigException("Model <b>$this->model</b> is not defined.");
    $dataClass = property($modelItem,'class');
    if (!isset($dataClass))
      throw new ConfigException("No data class defined for model <b>$this->model</b>.");
    if (!isset($modelItem->module))
      throw new ConfigException("No module defined for model <b>$this->model</b>.");
    return newInstanceOf($dataClass);
  }

  public function getDataItem() {
    if (!isset($this->dataItem))
      $this->dataItem = $this->createDataItem();
    return $this->dataItem;
  }

  public function getData(Controller $controller,$dataSourceName) {
    $dataItem = $this->getDataItem();
    if (isset($this->key))
      $dataItem->{$this->key} = $this->value;
    if (isset($this->preset)) {
      $presets = explode('&',$this->preset);
      foreach ($presets as $preset) {
        $presetParts = explode('=',$preset);
        if ($presetParts[1][0] == '{') {
          $field = substr($presetParts[1],1,strlen($presetParts[1]) - 2);
          $dataItem->{$presetParts[0]} = get($controller->URIParams,$field);
        }
        else $dataItem->{$presetParts[0]} = $presetParts[1];
      }
    }
    if ($this->format == DataSourceFormat::DATA_RECORD) {
     if ($dataSourceName == 'default')
        $controller->standardDataInit($this->dataItem);
      $this->dataItem->read();
      $controller->interceptViewDataRecord($dataSourceName,$this->dataItem);
      return new DataRecord($this->dataItem);
    }
    if (isset($this->pageSize)) {
      $page = garray_et($_REQUEST,$this->pageParam,1);
      $start = ($page - 1) * $this->pageSize;
      $count = $dataItem->queryBy($this->filter,'COUNT(*)',null,null)->fetchColumn(0);
      $data = $dataItem->queryBy($this->filter,$this->fields,$this->sortBy,null,"LIMIT $start,$this->pageSize")->fetchAll();
      $controller->max = ceil($count / $this->pageSize);
    }
    else $data = $dataItem->queryBy($this->filter,$this->fields,$this->sortBy,null,isset($this->limit) ? "LIMIT $this->limit" : '')->fetchAll();
    $controller->interceptViewDataSet($dataSourceName,$data);
    return new DataSet($data);
  }

}
