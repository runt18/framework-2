<?php
namespace Selenia\Exceptions;

class ConfigException extends FatalException {

  public function __construct($msg) {
    parent::__construct($msg,"Error on application configuration");
  }

}