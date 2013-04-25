<?php
namespace TYPO3\Proxy\Http\Cache;

/*                                                                        *
 * This script belongs to the FLOW3 framework.                            *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 * A cache frontend which stores HTTP responses
 *
 */
class RequestFrontend extends \TYPO3\Flow\Cache\Frontend\AbstractFrontend {

	/**
	 * Get the entry identifier matching the request. The identifier depends on the
	 * Request Uri and the headers specified by the 'Vary' header.
	 * 
	 * @param \TYPO3\Flow\Http\Request $request The request to get the identifier for
	 * @return string
	 */
	public function getEntryIdentifier(\TYPO3\Flow\Http\Request $request) {
		$prefix = '';
		$varyHeaders = $request->getHeader('Vary');
		if (is_array($varyHeaders)) {
			foreach ($varyHeaders as $varyHeader) {
				$prefix .= $varyHeader . '=' . $request->getHeader($varyHeader) . ';';
			}
		}
		return md5($prefix . (string)$request->getUri());
	}

	/**
	 * Get the stored response for a given cache entry
	 * 
	 * @param string $entryIdentifier The identifier to get the cache entry for
	 * @return \TYPO3\Flow\Http\Response
	 * @throws \InvalidArgumentException
	 */
	public function get($entryIdentifier) {
		if (!$this->isValidEntryIdentifier($entryIdentifier)) {
			throw new \InvalidArgumentException('"' . $entryIdentifier . '" is not a valid cache entry identifier.', 1233058294);
		}

		$rawResult = $this->backend->get($entryIdentifier);
		if ($rawResult === FALSE) {
			return FALSE;
		} else {
			return \TYPO3\Flow\Http\Response::createFromRaw($rawResult);
		}
	}

	/**
	 * Saves the response in the cache.
	 *
	 * @param string $entryIdentifier An identifier used for this cache entry
	 * @param \TYPO3\Flow\Http\Response $response The response to cache
	 * @param array $tags Tags to associate with this cache entry
	 * @param integer $lifetime Lifetime of this cache entry in seconds. If NULL is specified, the default lifetime is used. "0" means unlimited lifetime.
	 * @return void
	 * @throws \InvalidArgumentException
	 */
	public function set($entryIdentifier, $response, array $tags = array(), $lifetime = NULL) {
		if (!$this->isValidEntryIdentifier($entryIdentifier)) {
			throw new \InvalidArgumentException('"' . $entryIdentifier . '" is not a valid cache entry identifier.', 1233058264);
		}
		foreach ($tags as $tag) {
			if (!$this->isValidTag($tag)) {
				throw new \InvalidArgumentException('"' . $tag . '" is not a valid tag for a cache entry.', 1233058269);
			}
		}

		$rawContent = implode(chr(10), $response->renderHeaders()) . chr(10) . chr(10) . $response->getContent();
		$this->backend->set($entryIdentifier, $rawContent, $tags, $lifetime);
	}

	/**
	 * Finds and returns all cache entries which are tagged by the specified tag.
	 *
	 * @param string $tag The tag to search for
	 * @return array An array with the identifier (key) and content (value) of all matching entries. An empty array if no entries matched
	 * @throws \InvalidArgumentException
	 */
	public function getByTag($tag) {
		if (!$this->isValidTag($tag)) {
			throw new \InvalidArgumentException('"' . $tag . '" is not a valid tag for a cache entry.', 1233058312);
		}

		$entries = array();
		$identifiers = $this->backend->findIdentifiersByTag($tag);
		foreach ($identifiers as $identifier) {
			$rawResult = $this->backend->get($identifier);
			if ($rawResult !== FALSE) {
				$entries[$identifier] = \TYPO3\Flow\Http\Response::createFromRaw($rawResult);
			}
		}
		return $entries;
	}
}