<?php
namespace Electro\Http\Middleware;

use Electro\Http\Lib\Http;
use Electro\Interfaces\Http\RequestHandlerInterface;
use Electro\Kernel\Config\KernelSettings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A middleware that generates a 404 Not Found response.
 */
class URLNotFoundMiddleware implements RequestHandlerInterface
{
  private $kernelSettings;

  function __construct (KernelSettings $app)
  {
    $this->kernelSettings = $app;
  }

  function __invoke (ServerRequestInterface $request, ResponseInterface $response, callable $next)
  {
    $path     = either ($request->getAttribute ('virtualUri', '<i>not set</i>'), '<i>empty</i>');
    $realPath = $request->getUri ()->getPath ();
    return Http::response ($response, "<br><br><table align=center cellspacing=20 style='text-align:left'>
<tr><th>Virtual&nbsp;URL:<td><kbd>$path</kbd>
<tr><th>URL path:<td><kbd>$realPath</kbd>
</table>", 'text/html', 404);
  }
}
