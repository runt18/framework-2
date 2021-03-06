<?php

namespace Electro\Http\Lib;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Utility methods for working with HTTP messages.
 */
class Http
{
  const BAD_REQUEST        = 400;
  const FORBIDDEN          = 403;
  const NOT_FOUND          = 404;
  const PERMANENT_REDIRECT = 308;
  const SEE_OTHER          = 303;
  const TEMPORARY_REDIRECT = 307;
  const UNAUTHORIZED       = 401;

  /**
   * Converts an URL into an absolute form (with scheme://domain/path).
   *
   * @param  string                $url     An absolute or relative URL.
   * @param ServerRequestInterface $request A server request that will be used to build the absolute URL.
   * @return string The absolute URL.
   */
  static function absoluteUrlOf ($url, ServerRequestInterface $request)
  {
    if (self::isAbsoluteUrl ($url))
      return $url;

    $base = $request->getAttribute ('baseUri');
    if ($url == '')
      $url = $base;
    elseif ($url[0] != '/')
      $url = "$base/$url";
    return (string)$request->getUri ()->withPath ($url);
  }

  /**
   * Checks if the HTTP client accepts the given content type.
   *
   * @param ServerRequestInterface $request
   * @param string                 $contentType Ex: <kbd>'text/html'</kbd>
   * @return boolean
   */
  static function clientAccepts (ServerRequestInterface $request, $contentType)
  {
    return strpos ($request->getHeaderLine ('Accept'), $contentType) !== false;
  }

  /**
   * Utility method for retrieving the value of a form field submitted via a `application/x-www-form-urlencoded` or a
   * `multipart/form-data` POST request.
   *
   * @param ServerRequestInterface $request
   * @param string                 $name The field name.
   * @param mixed                  $def  [optional] A default value.
   * @return mixed
   */
  static function field (ServerRequestInterface $request, $name, $def = null)
  {
    return get ($request->getParsedBody (), $name, $def);
  }

  /**
   * Returns the referer URL in virtual URI format.
   *
   * @param ServerRequestInterface $request
   * @return string
   */
  static function getRefererVirtualUri (ServerRequestInterface $request)
  {
    return Http::relativePathOf ($request->getHeaderLine ('Referer'));
  }

  /**
   * Returns a map of routing parameters extracted from the request attributes (which mast have been set previously by
   * a router).
   *
   * @param ServerRequestInterface $request
   * @return array A map of name => value of all routing parameters set on the request.
   */
  static function getRouteParameters (ServerRequestInterface $request)
  {
    return mapAndFilter ($request->getAttributes (), function ($v, &$k) {
      if ($k && $k[0] == '@') {
        $k = substr ($k, 1);
        return $v;
      }
      return null;
    });
  }

  /**
   * Checks if a given URL matches is on absolute form (scheme://domain/path).
   *
   * @param string $url
   * @return bool
   */
  static function isAbsoluteUrl ($url)
  {
    return isset($url) ? (bool)preg_match ('#^\w+://\w#', $url) : false;
  }

  /**
   * Decodes a JSON response.
   *
   * @param ResponseInterface $response
   * @param bool              $assoc [optional] Return an associative array?
   * @return mixed
   */
  static function jsonFromResponse (ResponseInterface $response, $assoc = false)
  {
    if ($response->getHeaderLine ('Content-Type') != 'application/json')
      throw new \RuntimeException ("HTTP response is not of type JSON");
    return json_decode ($response->getBody (), $assoc);
  }

  /**
   * Creates a JSON-encoded response from the given data.
   *
   * @param ResponseInterface $response
   * @param mixed             $data
   * @return ResponseInterface
   */
  static function jsonResponse (ResponseInterface $response, $data)
  {
    return self::response ($response, json_encode ($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      'application/json');
  }

  /**
   * Simplifies setting response object properties to return a simple HTTP redirection response.
   *
   * <p>**Warning:** the given URL, if relative, will be relative to the request's URL, NOT to the application's base
   * URL.
   *
   * @param ResponseInterface $response    An existing, pristine, response object.
   * @param string            $url         The target URL.
   * @param int               $status      HTTP status code.
   *                                       <p>Valid redirection values should be:
   *                                       <p>302 - Found (client will always send a GET request and original URL will
   *                                       not be cached)
   *                                       <p>303 - See Other
   *                                       <p>307 - Temporary Redirect
   *                                       <p>308 - Permanent Redirect
   * @return ResponseInterface A new response object.
   */
  static function redirect (ResponseInterface $response, $url, $status = 302)
  {
    return $response->withStatus ($status)->withHeader ('Location', $url);
  }

  /**
   * Converts an URL to be relative to the root URL or to application's base URL (if a request object is given).
   *
   * <p>Ex: `http://domain.com/news/1 --> news/1`
   *
   * @param string                      $url
   * @param ServerRequestInterface|null $request
   * @return mixed|string
   */
  static function relativePathOf ($url, ServerRequestInterface $request = null)
  {
    $url = preg_replace ('#\?.*#', '', $url);
    if ($request) {
      $base = $request->getAttribute ('baseUrl');
      $len  = strlen ($base);
      if (substr ($url, 0, $len) == $base)
        $url = substr ($url, $len + 1);
    }
    else return preg_replace ('#^https?://[^/]*#', '', $url);
    return $url;
  }

  /**
   * Simplifies setting response object properties to return a simple HTTP response.
   *
   * @param ResponseInterface $response    An existing, pristine, response object.
   * @param string            $body        Am optional HTML body content.
   * @param string            $contentType Defaults to 'text/html'.
   * @param int               $status      HTTP status code.
   * @return ResponseInterface A new response object.
   */
  static function response (ResponseInterface $response, $body = '', $contentType = 'text/html', $status = 200)
  {
    if ($body)
      $response->getBody ()->write ($body);
    return $response->withStatus ($status)->withHeader ('Content-Type', $contentType);
  }

}
