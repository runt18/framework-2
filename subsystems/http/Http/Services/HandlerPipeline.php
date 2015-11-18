<?php
namespace Selenia\Http\Services;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Selenia\Interfaces\Http\HandlerPipelineInterface;
use Selenia\Interfaces\Http\RequestHandlerInterface;
use Selenia\Interfaces\InjectorInterface;

class HandlerPipeline implements HandlerPipelineInterface
{
  /**
   * @var ServerRequestInterface
   */
  private $currentRequest;
  /**
   * @var ResponseInterface
   */
  private $currentResponse;
  /**
   * @var InjectorInterface
   */
  private $injector;
  /**
   * @var array Array of middleware instances or class names.
   *            If a class name is specified, the middleware will be lazily created.
   */
  private $stack = [];

  function __construct (InjectorInterface $injector)
  {
    $this->injector = $injector;
  }

  /**
   * @param ServerRequestInterface $request
   * @param ResponseInterface      $response
   * @param callable               $next
   * @return ResponseInterface
   */
  function __invoke (ServerRequestInterface $request, ResponseInterface $response, callable $next = null)
  {
    $it   = new \ArrayIterator($this->stack);

    $iterate =
      function (ServerRequestInterface $request = null, ResponseInterface $response = null) use ($it, $request, &$iterate,
      $next) {
        if ($it->valid ()) {
          // Save the current state and also make it available outside the stack.

          $request  = $this->currentRequest = $request ?: $this->currentRequest;
          $response = $this->currentResponse = $response ?: $this->currentResponse;

          /** @var \Selenia\Interfaces\Http\RequestHandlerInterface $middleware */
          $m = $it->current ();
          $it->next ();

          // Fetch or instantiate the middleware and run it.
          $middleware  = is_string ($m) && !is_callable ($m) ? $this->injector->make ($m) : $m;
          $newResponse = $middleware ($request, $response, $iterate);

          // Replace the response if necessary.
          if (isset($newResponse)) {
            if ($newResponse instanceof ResponseInterface)
              return $this->currentResponse = $newResponse;
            throw new \RuntimeException ("Response from middleware " . get_class ($middleware) .
                                         " is not a ResponseInterface implementation.");
          }
          return $this->currentResponse;
        }
        return $next ? $next ($request, $response) : $response;
      };

    return $iterate ($request, $response);
  }

  /**
   * @param string|callable|RequestHandlerInterface $middleware
   * @return $this
   */
  function add ($middleware)
  {
    $this->stack[] = $middleware;
    return $this;
  }

  /**
   * @param boolean                                 $condition
   * @param string|callable|RequestHandlerInterface $middleware
   * @return $this
   */
  function addIf ($condition, $middleware)
  {
    if ($condition)
      $this->stack[] = $middleware;
    return $this;
  }

  function getCurrentRequest ()
  {
    return $this->currentRequest;
  }

  function getCurrentResponse ()
  {
    return $this->currentResponse;
  }

}