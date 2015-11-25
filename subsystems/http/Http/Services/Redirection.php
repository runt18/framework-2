<?php
namespace Selenia\Http\Services;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Selenia\Interfaces\Http\RedirectionInterface;
use Selenia\Interfaces\Http\ResponseFactoryInterface;
use Selenia\Interfaces\SessionInterface;
use Zend\Diactoros\Response;

/**
 * **Note:** this class assumes a `baseURI` attribute exists on the given ServerRequestInterface instance.
 */
class Redirection implements RedirectionInterface
{
  /**
   * @var ServerRequestInterface
   */
  private $request;
  /**
   * @var ResponseFactoryInterface
   */
  private $responseFactory;
  /**
   * @var SessionInterface
   */
  private $session;

  /**
   * @param ResponseFactoryInterface $responseFactory A factory fpr creating new responses.
   * @param SessionInterface         $session         The current session (always available, even if session support is
   *                                                  disabled).
   */
  function __construct (ResponseFactoryInterface $responseFactory, SessionInterface $session)
  {
    $this->responseFactory = $responseFactory;
    $this->session         = $session;
  }

  function back ($status = 302)
  {
    $this->validate ();
    return $this->to ($this->request->getHeaderLine ('Referer') ?: $this->request->getUri (), $status);
  }

  function setRequest (ServerRequestInterface $request)
  {
    $this->request = $request;
  }

  function guest ($url, $status = 302)
  {
    $this->validate ();
    $this->session->setPreviousUrl ($this->request->getUri ());
    $url = $this->normalizeUrl ($url);
    return $this->responseFactory->make ($status, '', '', ['Location' => $url]);
  }

  function home ($status = 302)
  {
    $this->validate ();
    return $this->to ($this->request->getAttribute ('baseUri'), $status);
  }

  function intended ($defaultUrl = '', $status = 302)
  {
    $url = $this->session->previousUrl () ?: $this->normalizeUrl ($defaultUrl);
    return $this->to ($url, $status);
  }

  function refresh ($status = 302)
  {
    $this->validate ();
    return $this->to ($this->request->getUri (), $status);
  }

  function secure ($url, $status = 302)
  {
    $url = str_replace ('http://', 'https://', $this->normalizeUrl ($url));
    return $this->responseFactory->make ($status, '', '', ['Location' => $url]);
  }

  function to ($url, $status = 302)
  {
    $url = $this->normalizeUrl ($url);
    return $this->responseFactory->make ($status, '', '', ['Location' => $url]);
  }

  /**
   * Converts the given URL to an absolute URL and returns it as a string.
   * @param string|UriInterface $url
   * @return string
   */
  protected function normalizeUrl ($url)
  {
    $url = strval ($url);
    if (!$url)
      return strval ($this->request->getUri ());
    if ($url[0] != '/' && substr ($url, 0, 4) != 'http')
      $url = $this->request->getAttribute ('baseUri') . "/$url";
    return $url;
  }

  protected function validate ()
  {
    if (!$this->request)
      throw new \BadMethodCallException ("A <kbd class=type>ServerRequestInterface</kbd> instance is not set on the <kbd class=type>Redirection</kbd> instance.");
  }

}
