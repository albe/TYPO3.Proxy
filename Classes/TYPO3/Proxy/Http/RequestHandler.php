<?php
namespace TYPO3\Proxy\Http;

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
use TYPO3\Flow\Core\Bootstrap;
use TYPO3\Flow\Http\Request;

/**
 * A request handler which can handle HTTP requests and acts as a reverse caching proxy.
 *
 * @Flow\Scope("singleton")
 * @Flow\Proxy(false)
 */
class RequestHandler extends \TYPO3\Flow\Http\RequestHandler {

	/**
	 * @var \TYPO3\Proxy\Http\Cache
	 */
	protected $cache;

	/**
	 * Returns the priority - how eager the handler is to actually handle the
	 * request.
	 *
	 * @return integer The priority of the request handler.
	 * @api
	 */
	public function getPriority() {
		return 200;
	}

	/**
	 * Handles a HTTP request
	 *
	 * @param \TYPO3\Flow\Http\Request $request
	 * @return void
	 */
	public function handleRequest(Request $request = NULL) {
			// Create the request very early so the Resource Management has a chance to grab it:
		$this->request = $request ?: Request::createFromEnvironment();

		$this->boot();
		$this->resolveDependencies();
		$this->request->injectSettings($this->settings);

		$this->response = $this->cache->fetchResponse($this->request);
		if ($this->response !== NULL) {
			$this->response->send();

			$this->bootstrap->shutdown('Runtime');
			$this->exit->__invoke();
		}

		$this->bootstrap->setActiveRequestHandler(new \TYPO3\Flow\Http\RequestHandler($this->bootstrap));
		$this->bootstrap->getActiveRequestHandler()->handleRequest($this->request);
	}

	/**
	 * Boots up Flow to essential runtime (Configuration, SystemLogger, ErrorHandling and CacheManagement)
	 *
	 * @return void
	 */
	protected function boot() {
		$sequence = $this->bootstrap->buildEssentialsSequence('runtime');
		$sequence->invoke($this->bootstrap);
	}

	/**
	 * Resolves a few dependencies of this request handler which can't be resolved
	 * automatically due to the early stage of the boot process this request handler
	 * is invoked at.
	 *
	 * @return void
	 */
	protected function resolveDependencies() {
		$configurationManager = $this->bootstrap->getEarlyInstance('TYPO3\Flow\Configuration\ConfigurationManager');
		$settings = $configurationManager->getConfiguration(\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.Proxy');

		if (!isset($settings['log']['proxyLogger']['logger'])) {
			$settings['log']['proxyLogger']['logger'] = 'TYPO3\Flow\Log\Logger';
		}
		$proxyLogger = \TYPO3\Flow\Log\LoggerFactory::create('ProxyLogger', $settings['log']['proxyLogger']['logger'], $settings['log']['proxyLogger']['backend'], $settings['log']['proxyLogger']['backendOptions']);

		$this->settings = $settings;

		/* @var $cacheManager \TYPO3\Flow\Cache\CacheManager */
		$cacheManager = $this->bootstrap->getEarlyInstance('TYPO3\Flow\Cache\CacheManager');
		$this->cache = new Cache($this->bootstrap, $cacheManager->getCache('Flow_Proxy_Response'), $proxyLogger);
	}
}
?>
