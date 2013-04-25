<?php
namespace TYPO3\Proxy\Aspect;

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

/**
 * Adds the aspect of cache-control headers to web requests
 *
 * @Flow\Aspect
 * @Flow\Scope("singleton")
 */
class CacheControlAspect {

	/**
	 * @var \TYPO3\Flow\Object\ObjectManagerInterface
	 */
	protected $objectManager;

	/**
	 * @var \TYPO3\Flow\Reflection\ReflectionService
	 */
	protected $reflectionService;

	/**
	 * @var \TYPO3\Flow\Log\SystemLoggerInterface
	 * @Flow\Inject
	 */
	protected $systemLogger;

	/**
	 * @var int
	 */
	protected $lastModified;

	/**
	 * @var string
	 */
	protected $etag;

	/**
	 * Constructor
	 *
	 * @param \TYPO3\Flow\Object\ObjectManagerInterface $objectManager
	 * @param \TYPO3\Flow\Reflection\ReflectionService $reflectionService
	 */
	public function __construct(\TYPO3\Flow\Object\ObjectManagerInterface $objectManager, \TYPO3\Flow\Reflection\ReflectionService $reflectionService) {
		$this->objectManager = $objectManager;
		$this->reflectionService = $reflectionService;
	}

	/**
	 * Around advice for TYPO3\Flow\Mvc\Controller\ActionController->processRequest().
	 * This method will check the required Cache-Control HTTP headers and set them in
	 * the response.
	 *
	 * @Flow\Around("within(\TYPO3\Flow\Mvc\Controller\ActionController) && method(.*->processRequest())")
	 * @param \TYPO3\Flow\Aop\JoinPointInterface $joinPoint The current join point
	 * @return mixed Result of the advice or the original method of the target class
	 */
	public function addCacheControlHeaders(\TYPO3\Flow\Aop\JoinPointInterface $joinPoint) {
		$this->systemLogger->log('CacheControlAspect start');

		/** @var $actionRequest \TYPO3\Flow\Mvc\ActionRequest */
		$actionRequest = $joinPoint->getMethodArgument('request');
		if (!$actionRequest instanceof \TYPO3\Flow\Mvc\ActionRequest) {
			return $joinPoint->getAdviceChain()->proceed($joinPoint);
		}
		$request = $actionRequest->getHttpRequest();

		/** @var $response \TYPO3\Flow\Http\Response */
		$response = $joinPoint->getMethodArgument('response');
		if (!$response instanceof \TYPO3\Flow\Http\Response) {
			return $joinPoint->getAdviceChain()->proceed($joinPoint);
		}

		$controllerClassName = $this->objectManager->getClassNameByObjectName($actionRequest->getControllerObjectName());
		$controllerActionName = $actionRequest->getControllerActionName() . 'Action';

		$cacheControlAnnotation = $this->getCacheControlAnnotation($controllerClassName, $controllerActionName);
		if (NULL === $cacheControlAnnotation) {
			return $joinPoint->getAdviceChain()->proceed($joinPoint);
		}

		$now = $_SERVER['REQUEST_TIME'];
		if (NULL === $this->etag) {
			$this->etag = md5($controllerClassName.'->'.$controllerActionName);
		}
		if (NULL === $this->lastModified) {
			$this->lastModified = $now;
		}

		// Action annotation overrides controller annotation
		$actionCacheAnnotation = $this->reflectionService->getMethodAnnotation($controllerClassName, $controllerActionName, 'TYPO3\Flow\Annotations\Cache');
		if (NULL === $actionCacheAnnotation) {
			$actionCacheAnnotation = $this->reflectionService->getClassAnnotation($controllerClassName, 'TYPO3\Flow\Annotations\Cache');
		}

		// Controller annotation || action annotation
		$authenticationContext = $this->reflectionService->getClassAnnotation($controllerClassName, 'TYPO3\Flow\Annotations\AuthenticationContext');
		if (NULL === $authenticationContext) {
			$authenticationContext = $this->reflectionService->getMethodAnnotation($controllerClassName, $controllerActionName, 'TYPO3\Flow\Annotations\AuthenticationContext');
		}

		if ($cacheControlAnnotation->mustRevalidate) {
			$modified = TRUE;
			if (NULL !== ($ifNoneMatch = $request->getHeaders()->get('If-None-Match'))) {
				if (str_replace('"','',$ifNoneMatch) === $this->etag) {
					$modified = FALSE;
				}
			} else if (NULL !== ($ifModifiedSince = $request->getHeaders()->get('If-Modified-Since')) &&
				strtotime($ifModifiedSince) === $this->lastModified) {
				$modified = FALSE;
			}

			if (!$modified) {
				if ($request->getMethod() === 'GET' || $request->getMethod() === 'HEAD') {
					$response->setStatus(304, 'Not Modified');
				} else {
					$response->setStatus(412, 'Precondition Failed');
				}
				return NULL;
			}
		}

		// Allow the ActionCache or other aspects to influence ETag/lastModified time
		$result = $joinPoint->getAdviceChain()->proceed($joinPoint);

		if ('' !== $cacheControlAnnotation->headers) {
			$response->setHeader('Cache-Control', $cacheControlAnnotation->headers);
		} else {
			$response->setHeader('Last-Modified', gmdate('r', $this->lastModified));

			if (!$cacheControlAnnotation->noCache) {
				$cacheControl = array();
				if (NULL !== $authenticationContext || !$cacheControlAnnotation->public) {
					$cacheControl[] = 'private';
				} else {
					$cacheControl[] = 'public';
				}
				if ($cacheControlAnnotation->mustRevalidate) {
					$response->setHeader('ETag', $this->etag);
					$cacheControl[] = 'must-revalidate';
				}
				if (NULL !== $actionCacheAnnotation) {
					$cacheControl[] = 'max-age='.($actionCacheAnnotation->ttl + $this->lastModified - $now);
				} else {
					$cacheControl[] = 'max-age=0';
				}

				$response->setHeader('Cache-Control', implode(', ', $cacheControl));
			} else {
				$response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
			}
		}

		return $result;
	}

	/**
	 * @return int
	 */
	public function getLastModified() {
		return $this->lastModified;
	}

	/**
	 * @param int $lastModified
	 */
	public function setLastModified($lastModified) {
		$this->lastModified = $lastModified;
	}

	/**
	 * @return string
	 */
	public function getEtag() {
		return $this->etag;
	}

	/**
	 * @param string $etag
	 */
	public function setEtag($etag) {
		$this->etag = $etag;
	}

	/**
	 *
	 * @param string $controllerClassName
	 * @param string $controllerActionName
	 * @return \TYPO3\Flow\Annotations\CacheControl
	 */
	protected function getCacheControlAnnotation($controllerClassName, $controllerActionName) {
		$classAnnotation = $this->reflectionService->getClassAnnotation($controllerClassName, 'TYPO3\Flow\Annotations\CacheControl');
		if (NULL !== $classAnnotation) {
			return $classAnnotation;
		}

		$actionAnnotation = $this->reflectionService->getMethodAnnotation($controllerClassName, $controllerActionName, 'TYPO3\Flow\Annotations\CacheControl');
		if (NULL !== $actionAnnotation) {
			return $actionAnnotation;
		}

		return NULL;
		/*
		$cacheControlAnnotation = new \TYPO3\Flow\Annotations\CacheControl();

		if (NULL !== $actionAnnotation && '' !== $actionAnnotation->headers) {
			$cacheControlAnnotation->headers = $actionAnnotation->headers;
		} elseif (NULL !== $classAnnotation && '' !== $classAnnotation->headers) {
			$cacheControlAnnotation->headers = $classAnnotation->headers;
		}

		return $cacheControlAnnotation;
		*/
	}

}
?>