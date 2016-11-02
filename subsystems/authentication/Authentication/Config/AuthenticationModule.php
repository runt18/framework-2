<?php
namespace Electro\Authentication\Config;

use Electro\Core\Assembly\ModuleInfo;
use Electro\Core\Assembly\Services\Bootstrapper;
use Electro\Interfaces\DI\InjectorInterface;
use Electro\Interfaces\ModuleInterface;
use Electro\Interfaces\UserInterface;
use const Electro\Core\Assembly\Services\REGISTER_SERVICES;

class AuthenticationModule implements ModuleInterface
{
  static function bootUp (Bootstrapper $bootstrapper, ModuleInfo $moduleInfo)
  {
    $bootstrapper->on (REGISTER_SERVICES, function (InjectorInterface $injector) {
      $injector
        ->share (UserInterface::class, 'user')
        ->share (AuthenticationSettings::class)
        ->delegate (UserInterface::class, function (AuthenticationSettings $settings) use ($injector) {
          return $injector->make ($settings->userModel ());
        });
    });
  }
}
