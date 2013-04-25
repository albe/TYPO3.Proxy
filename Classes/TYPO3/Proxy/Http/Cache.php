<?php
namespace TYPO3\Proxy\Http;

/*                                                                        *
 * This script belongs to the FLOW3 Package "TYPO3.Proxy".                *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Log\LoggerInterface;
use TYPO3\Flow\Http\Request;
use TYPO3\Flow\Http\Response;

/**
 * A cache which stores HTTP responses according to Cache-Control headers and recommendations
 * following RFC2616.
 *
 * @Flow\Scope("prototype")
 */
class Cache {

	/**
	 * hop-by-hop headers according to RFC2616 13.5.1
	 * @var array
	 */
	protected static $hopByHopHeaders = array(
			'Connection',
			'Keep-Alive',
			'Proxy-Authenticate',
			'Proxy-Authorization',
			'TE',
			'Trailers',
			'Transfer-Encoding',
			'Upgrade',
	);

	/**
	 * @var \TYPO3\Flow\Core\Bootstrap
	 */
	protected $bootstrap;

	/**
	 * @var \TYPO3\Proxy\Http\Cache\RequestFrontend
	 */
	protected $frontend;

	/**
	 * @var \TYPO3\Flow\Log\LoggerInterface
	 */
	protected $proxyLogger;

	/**
	 * Constructor
	 *
	 * @param \TYPO3\Flow\Core\Bootstrap $bootstrap
	 * @param \TYPO3\Proxy\Http\Cache\RequestFrontend $frontend
	 * @param \TYPO3\Flow\Log\LoggerInterface $proxyLogger
	 */
	public function __construct(\TYPO3\Flow\Core\Bootstrap $bootstrap, Cache\RequestFrontend $frontend, LoggerInterface $proxyLogger) {
		$this->bootstrap = $bootstrap;
		$this->frontend = $frontend;
		$this->proxyLogger = $proxyLogger;
	}

	/**
	 * Checks if a cache entry for the specified request exists.
	 *
	 * @param \TYPO3\Flow\Http\Request $request The request specifying the cache entry
	 * @param string $entryIdentifier
	 * @return boolean TRUE if such an entry exists, FALSE if not
	 * @throws \InvalidArgumentException
	 */
	protected function has(Request $request, $entryIdentifier) {
		$ifMatch = $request->getHeader('If-Match');
		if (is_array($ifMatch)) {
			return !in_array($entryIdentifier, $ifMatch) || $this->frontend->has($entryIdentifier);
		} elseif ($ifMatch === '*') {
			return TRUE;
		}

		$ifNotMatch = $request->getHeader('If-Not-Match');
		if (is_array($ifNotMatch)) {
			if (in_array($entryIdentifier, $ifNotMatch)) {
				return TRUE;
			}
			$request->getHeaders()->remove('If-Modified-Since');
		} elseif ($ifNotMatch === '*') {
			return $this->frontend->has($entryIdentifier);
		}
		
		return $this->frontend->has($entryIdentifier);
	}

	/**
	 * Return a new empty response with the given status
	 * @param integer $status
	 * @return \TYPO3\Flow\Http\Response
	 */
	protected function getEmptyResponse($status) {
		$response = new Response();
		$response->setStatus($status);
		$response->setContent('');
		
		return $response;
	}

	/**
	 * Return a new response with the 304 'Not Modified' status
	 * @return \TYPO3\Flow\Http\Response
	 */
	protected function getNotModifiedResponse() {
		return $this->getEmptyResponse(304);
	}

	/**
	 * Return a new response with the 412 'Precondition Failed' status
	 * @return \TYPO3\Flow\Http\Response
	 */
	protected function getPreconditionFailedResponse() {
		return $this->getEmptyResponse(412);
	}

	/**
	 * Get the stored response for a given request
	 * 
	 * @param \TYPO3\Flow\Http\Request $request
	 * @param string $entryIdentifier
	 * @return \TYPO3\Flow\Http\Response
	 * @throws \InvalidArgumentException
	 */
	protected function get(Request $request, $entryIdentifier) {
		$response = FALSE;
		$ifMatch = $request->getHeader('If-Match');
		if (is_array($ifMatch)) {
			if (in_array($entryIdentifier, $ifMatch)) {
				$response = $this->frontend->get($entryIdentifier);
			}
			$response = $this->getPreconditionFailedResponse();
		} elseif ($ifMatch === '*') {
			if (!$this->frontend->has($entryIdentifier)) {
				$response = $this->getPreconditionFailedResponse();
			} else {
				$response = $this->frontend->get($entryIdentifier);
			}
		}

		$ifNotMatch = $request->getHeader('If-Not-Match');
		if (is_array($ifNotMatch)) {
			if (in_array($entryIdentifier, $ifNotMatch)) {
				if ($request->isMethodSafe()) {
					$response = $this->getNotModifiedResponse();
				} else {
					$response = $this->getPreconditionFailedResponse();
				}
			}
		} elseif ($ifNotMatch === '*') {
			if ($request->isMethodSafe()) {
				$response = $this->getNotModifiedResponse();
			} else {
				$response = $this->getPreconditionFailedResponse();
			}
		}

		if ($response === FALSE) {
			$response = $this->frontend->get($entryIdentifier);
		}
			// This is not RFC2616 conforming
		$response->setHeader('ETag', $entryIdentifier);

		return $response;
	}

	/**
	 * Check if the response for a given request can be fetched
	 * @param \TYPO3\Flow\Http\Request $request
	 * @param \TYPO3\Flow\Http\Response $response
	 * @return boolean TRUE if the response can be fetched, FALSE otherwise
	 */
	protected function canFetch(Request $request, Response $response = NULL) {
		if (!$request->isMethodSafe()) {
			return FALSE;
		}

			// Don't cache authorization requests according to RFC2616 14.8
		if ($request->getHeader('Authorization') !== NULL) {
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Check if the response for a given request can be stored
	 * @param \TYPO3\Flow\Http\Request $request
	 * @param \TYPO3\Flow\Http\Response
	 * @return boolean TRUE if the response can be stored, FALSE otherwise
	 */
	protected function canStore(Request $request, Response $response) {
		if (!$request->isMethodSafe()) {
			$this->proxyLogger->log('Cannot store response because request method is not safe.');
			return FALSE;
		}

			// Don't store authorization requests according to RFC2616 14.8
		if ($request->getHeader('Authorization') !== NULL &&
			!$request->getHeaders()->getCacheControlDirective('s-maxage') &&
			$request->getHeaders()->getCacheControlDirective('must-revalidate') === NULL &&
			$request->getHeaders()->getCacheControlDirective('public') === NULL &&
			$request->getHeaders()->getCacheControlDirective('private') === NULL) {
			$this->proxyLogger->log('Cannot store response because request contains authorization information and is not cacheable.');
			return FALSE;
		}

		if (in_array($response->getStatusCode(), array(200, 203, /*206,*/ 300, 301, 410)) &&
			$response->getHeaders()->getCacheControlDirective('no-store') === NULL &&
			$response->getHeaders()->getCacheControlDirective('no-cache') === NULL) {
			return TRUE;
		}

		$this->proxyLogger->log('Cannot store response because it is not cacheable.');
		return FALSE;
	}

	/**
	 * Check that a (cached) response is still fresh
	 * @param \TYPO3\Flow\Http\Response $response
	 * @return boolean TRUE if the response is fresh and can be used, FALSE otherwise
	 */
	protected function isFresh(Response $response) {
		$maximumAge = ($response->getSharedMaximumAge() !== NULL) ? $response->getSharedMaximumAge() : $response->getMaximumAge();

		if ($maximumAge !== NULL) {
			$minFresh = $response->getHeaders()->getCacheControlDirective('min-fresh');
			$maxStale = $response->getHeaders()->getCacheControlDirective('max-stale');

			if ($maximumAge - $minFresh + $maxStale < $response->getAge()) {
				return FALSE;
			}

			if ($maximumAge < $response->getAge()) {
					// We provide a stale response, so we need to send a Warning header
				$response->setHeader('Warning', '110 Response is stale');
			}

				// max-age overrides expires (see RFC2616 14.9.3)
			return TRUE;
		}

		$expires = $response->getExpires();
		if ($expires !== NULL && $expires < $response->getDate()) {
			return FALSE;
		}
		
		return TRUE;
	}

	/**
	 * Fetch the matching response from the cache if available
	 * 
	 * @param \TYPO3\Flow\Http\Request $request
	 * @return \TYPO3\Flow\Http\Response|NULL
	 * @throws \InvalidArgumentException
	 */
	public function fetchResponse(Request $request) {
		if (!$this->canFetch($request)) {
			return NULL;
		}

		if ($request->getHeader('ETag') !== NULL) {
			$entryIdentifier = $request->getHeader('ETag');
		} else {
			$entryIdentifier = $this->frontend->getEntryIdentifier($request);
		}
		if ($this->has($request, $entryIdentifier)) {
			$response = $this->get($request, $entryIdentifier);
			if (!$this->isFresh($response)) {
				return NULL;
			}
			$response->setHeader('X-Flow-Proxy-Cache', 'hit', TRUE);
			$this->proxyLogger->log('Fetched response for request with identifier "' . $entryIdentifier . '".');
			return $response;
		}

		return NULL;
	}

	/**
	 * Store the current response in the cache
	 * 
	 * @return void
	 * @throws \InvalidArgumentException
	 */
	public function storeResponse() {
		/* @var $request Request */
		$request = $this->bootstrap->getActiveRequestHandler()->getHttpRequest();
		/* @var $response Response */
		$response = $this->bootstrap->getActiveRequestHandler()->getHttpResponse();

		if ($response->getHeader('X-Flow-Proxy-Cache') === 'hit') {
			return;
		}
		$response->setHeader('X-Flow-Proxy-Cache', 'miss');

		if (!$this->canStore($request, $response)) {
			return;
		}

		$entryIdentifier = $this->frontend->getEntryIdentifier($request);

		$tags = array();
		foreach (self::$hopByHopHeaders as $hopByHopHeader) {
			if ($response->getHeaders()->has($hopByHopHeader)) {
				$response->getHeaders()->remove($hopByHopHeader);
			}
		}

		if ($request->getHeaders()->getCacheControlDirective('no-transform') === NULL) {
			// We MAY change response body for performance optimizations and even change following headers:
			// Content-Encoding, Content-Range, Content-Type
			// TODO: add different optimization strategies, like adding DataURI images, strip whitespaces etc.
		}

		$this->frontend->set($entryIdentifier, $response, $tags, $response->getMaximumAge());
		$this->proxyLogger->log('Stored response for request with identifier "' . $entryIdentifier . '".');
	}
}