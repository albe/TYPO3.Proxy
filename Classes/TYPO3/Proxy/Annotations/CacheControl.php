<?php
namespace TYPO3\Proxy\Annotations;

/*                                                                        *
 * This script belongs to the FLOW3 Package "TYPO3.Proxy".                *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * @Annotation
 * @Target({"METHOD", "CLASS"})
 */
final class CacheControl {

	/**
	 * Directly set the Cache-Control headers. (Can be given as anonymous argument.)
	 * @var array
	 */
	public $headers = array();

	/**
	 * Whether the public parameter should be sent
	 * (allow proxy caching)
	 * @var bool
	 */
	public $public = false;

	/**
	 * Whether the private parameter should be sent
	 * (disallow proxy caching)
	 * @var bool
	 */
	public $private = false;

	/**
	 * Whether the no-cache parameter should be sent
	 * (document may be cached, but must be revalidated)
	 * @var bool
	 */
	public $noCache = false;
	
	/**
	 * Whether the no-store parameter should be sent
	 * (document may not be cached)
	 * @var bool
	 */
	public $noStore = false;

	/**
	 * The max-age parameter that is to be sent
	 * @var int
	 */
	public $maxAge = 0;

	/**
	 * The s-maxage parameter that is to be sent
	 * @var int
	 */
	public $sMaxAge = 0;

	/**
	 * Whether the must-revalidate parameter should be sent
	 * (document must be revalidated before delivery from cache)
	 * @var bool
	 */
	public $mustRevalidate = false;

	/**
	 * Whether the proxy-revalidate parameter should be sent
	 * (document must be revalidated by proxy caches)
	 * @var bool
	 */
	public $proxyRevalidate = false;

	/**
	 * @param array $values
	 */
	public function __construct(array $values) {
		if (isset($values['headers']) && is_array($values['headers'])) {
			$this->headers = $values['headers'];
		} elseif (isset($values['value']) && is_array($values['value'])) {
			$this->headers = $values['value'];
		}

		if (isset($values['public'])) {
			$this->public = (bool)$values['public'];
		}

		if (isset($values['private'])) {
			$this->private = (bool)$values['private'];
		}

		if (isset($values['noCache'])) {
			$this->noCache = (bool)$values['noCache'];
		}

		if (isset($values['noStore'])) {
			$this->noStore = (bool)$values['noStore'];
		}

		if (isset($values['maxAge'])) {
			$this->maxAge = (int)$values['maxAge'];
		}

		if (isset($values['sMaxAge'])) {
			$this->sMaxAge = (int)$values['sMaxAge'];
		}

		if (isset($values['mustRevalidate'])) {
			$this->mustRevalidate = (bool)$values['mustRevalidate'];
		}

		if (isset($values['proxyRevalidate'])) {
			$this->proxyRevalidate = (bool)$values['proxyRevalidate'];
		}
	}

}

?>