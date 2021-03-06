<?php

namespace Electro\Http\Config;

use Electro\Http\Lib\CurrentRequest;
use Electro\Http\Services\Redirection;
use Electro\Http\Services\ResponseFactory;
use Electro\Http\Services\ResponseSender;
use Electro\Interfaces\DI\InjectorInterface;
use Electro\Interfaces\Http\RedirectionInterface;
use Electro\Interfaces\Http\ResponseFactoryInterface;
use Electro\Interfaces\Http\ResponseSenderInterface;
use Electro\Interfaces\Http\Shared\CurrentRequestInterface;
use Electro\Interfaces\KernelInterface;
use Electro\Interfaces\ModuleInterface;
use Electro\Kernel\Lib\ModuleInfo;
use Electro\Profiles\WebProfile;

/**
 * ### Notes:
 *
 * - `ServerRequestInterface` and `ResponseInterface` instances are not shared, and injecting them always yields new
 *   pristine instances.
 *   > **Note:** when cloning `ResponseInterface` instances, never forget to also clone their `body` streams.
 * - Injected `HandlerPipelineInterface` instances are not shared.
 */
class HttpModule implements ModuleInterface
{
  static function getCompatibleProfiles ()
  {
    return [WebProfile::class];
  }

  static function startUp (KernelInterface $kernel, ModuleInfo $moduleInfo)
  {
    $kernel->onRegisterServices (
      function (InjectorInterface $injector) {
        $injector
          ->alias (RedirectionInterface::class, Redirection::class)
          ->alias (ResponseFactoryInterface::class, ResponseFactory::class)
          ->alias (ResponseSenderInterface::class, ResponseSender::class)
          ->alias (CurrentRequestInterface::class, CurrentRequest::class)
          ->share (CurrentRequestInterface::class, 'request');
      });
  }

}
