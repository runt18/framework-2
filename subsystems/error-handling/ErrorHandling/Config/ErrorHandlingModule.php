<?php
namespace Electro\ErrorHandling\Config;

use Electro\ErrorHandling\Middleware\EditorLauncherMiddleware;
use Electro\ErrorHandling\Services\ErrorRenderer;
use Electro\Interfaces\DI\InjectorInterface;
use Electro\Interfaces\Http\ErrorRendererInterface;
use Electro\Interfaces\Http\Shared\ApplicationMiddlewareInterface;
use Electro\Interfaces\KernelInterface;
use Electro\Interfaces\ModuleInterface;
use Electro\Kernel\Lib\ModuleInfo;

class ErrorHandlingModule implements ModuleInterface
{
  static function startUp (KernelInterface $kernel, ModuleInfo $moduleInfo)
  {
    $kernel
      ->onRegisterServices (
        function (InjectorInterface $injector) {
          $injector
            ->alias (ErrorRendererInterface::class, ErrorRenderer::class)
            ->share (ErrorHandlingSettings::class);
        })
      //
      ->onConfigure (
        function ($debugConsole, ApplicationMiddlewareInterface $applicationMiddleware) {
          if ($debugConsole) {
            $applicationMiddleware->add (EditorLauncherMiddleware::class, null, 'router');
          }
        });
  }

}
