<?php
namespace Electro\WebApplication;

use Electro\ContentServer\Middleware\ContentServerMiddleware;
use Electro\Debugging\Middleware\WebConsoleMiddleware;
use Electro\ErrorHandling\Middleware\ErrorHandlingMiddleware;
use Electro\Http\Middleware\CompressionMiddleware;
use Electro\Http\Middleware\CsrfMiddleware;
use Electro\Http\Middleware\URLNotFoundMiddleware;
use Electro\Http\Middleware\WelcomeMiddleware;
use Electro\Interfaces\Http\ApplicationMiddlewareAssemblerInterface;
use Electro\Interfaces\Http\MiddlewareStackInterface;
use Electro\Interfaces\Http\Shared\ApplicationRouterInterface;
use Electro\Localization\Middleware\LanguageMiddleware;
use Electro\Localization\Middleware\TranslationMiddleware;
use Electro\Routing\Middleware\PermalinksMiddleware;
use Electro\Sessions\Middleware\SessionMiddleware;

class ApplicationMiddlewareAssembler implements ApplicationMiddlewareAssemblerInterface
{
  /** @var bool */
  private $debugConsole;
  /** @var bool */
  private $debugMode;

  public function __construct ($debugMode, $debugConsole)
  {
    $this->debugMode    = $debugMode;
    $this->debugConsole = $debugConsole;
  }

  function assemble (MiddlewareStackInterface $stack)
  {
    $stack
      ->set ([
        ContentServerMiddleware::class,
        when (!$this->debugMode, CompressionMiddleware::class),
        when ($this->debugConsole, WebConsoleMiddleware::class),
        TranslationMiddleware::class,
        ErrorHandlingMiddleware::class,
        SessionMiddleware::class,
        CsrfMiddleware::class,
        LanguageMiddleware::class,
        PermalinksMiddleware::class,
        'router'   => ApplicationRouterInterface::class,
        WelcomeMiddleware::class,
        'notFound' => URLNotFoundMiddleware::class,
      ]);
  }

}