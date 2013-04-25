<?php
namespace Flow\Proxy\Tests\Unit\Http;

/*                                                                        *
 * This script belongs to the FLOW3 package "FLOW3.Proxy".                *
 *                                                                        *
 *                                                                        */

/**
 * Testcase for http cache
 * @Flow\Scope("singleton")
 */
class CacheTest extends \TYPO3\Flow\Tests\UnitTestCase {

	/**
	 * @var \TYPO3\Flow\Http\Request
	 */
	protected $request;

	/**
	 * @var \TYPO3\Flow\Http\Response
	 */
	protected $response;

	/**
	 * @var \TYPO3\Proxy\Http\Cache\RequestFrontend
	 */
	protected $frontend;

	/**
	 * @var \TYPO3\Flow\Core\Bootstrap
	 */
	protected $bootstrap;

	/**
	 * @var \TYPO3\Proxy\Http\Cache
	 */
	protected $cache;


	public function setUp() {
		$uri = $this->getMock('TYPO3\Flow\Http\Uri', array(), array(), '', FALSE)
					->expects($this->any())->method('__toString')
					->will($this->returnValue('http://www.typo3.org/proxy/cache/test?foo=bar&baz=baz'));

		$request = $this->getAccessibleMock('TYPO3\Flow\Http\Request', array('getUri'), array(), '', FALSE);
		$request->expects($this->any())->method('getUri')
				->will($this->returnValue($uri));
		$this->inject($request, 'headers', new \TYPO3\Flow\Http\Headers());
		$this->inject($request, 'method', 'GET');
		$this->request = $request;

		$response = $this->getMock('TYPO3\Flow\Http\Response', array('dummy'));
		$this->response = $response;

		$requestHandler = $this->getAccessibleMock('TYPO3\Flow\Http\RequestHandler', array(), array(), '', FALSE);
		$requestHandler->expects($this->any())->method('getHttpRequest')
					   ->will($this->returnValue($this->request));
		$requestHandler->expects($this->any())->method('getHttpResponse')
					   ->will($this->returnValue($this->response));

		$bootstrap = $this->getMock('TYPO3\Flow\Core\Bootstrap', array(), array(), '', FALSE);
		$bootstrap->expects($this->any())->method('getActiveRequestHandler')
				  ->will($this->returnValue($requestHandler));
		$this->bootstrap = $bootstrap;

		$requestHandler->_set('bootstrap', $bootstrap);

		$frontend = $this->getMock('TYPO3\Proxy\Http\Cache\RequestFrontend', array(), array(), '', FALSE);
		$frontend->expects($this->any())->method('has')->will($this->returnCallback(function($identifier){ return $identifier === 'ID'; }));
		$frontend->expects($this->any())->method('get')->will($this->returnCallback(function($identifier) use ($response){ return $identifier === 'ID' ? $response : FALSE; }));
		$this->frontend = $frontend;
		
		$proxyLogger = $this->getMock('TYPO3\Proxy\Log\ProxyLoggerInterface');
		$this->cache = $this->getMock('TYPO3\Proxy\Http\Cache', array('dummy'), array($this->bootstrap, $this->frontend, $proxyLogger));
	}

	/**
	 * @test
	 */
	public function testSetUp() {
		$this->assertTrue($this->frontend->has('ID'));
		$this->assertFalse($this->frontend->has('ID2'));
		$this->assertTrue($this->frontend->get('ID') == $this->response);
		$this->assertFalse($this->frontend->get('ID2'));
	}

	public function getNonModifyableHeaders() {
		return array(
			'Content-Location',
			'Content-MD5',
			'ETag',
			'Last-Modified',
			'Expires'
		);
	}

	public function getNonAddableHeaders() {
		return array(
			'Content-Location',
			'Content-MD5',
			'ETag',
			'Last-Modified'
		);
	}

	public function getNonModifyableHeadersForNoTransform() {
		return array(
			'Content-Location',
			'Content-MD5',
			'ETag',
			'Last-Modified',
			'Expires',
			'Content-Encoding',
			'Content-Range',
			'Content-Type'
		);
	}

	public function getNonAddableHeadersForNoTransform() {
		return array(
			'Content-Location',
			'Content-MD5',
			'ETag',
			'Last-Modified',
			'Content-Encoding',
			'Content-Range',
			'Content-Type'
		);
	}

	public function getHopByHopHeaders() {
		return array(
			'Connection',
			'Keep-Alive',
			'Proxy-Authenticate',
			'Proxy-Authorization',
			'TE',
			'Trailers',
			'Transfer-Encoding',
			'Upgrade',
		);
	}

	/**
	 * Check that the cache does not modify headers according to
	 * RFC2616 13.5.2
	 * @test
	 */
	public function storeResponseDoesNotModifyHeadersIfNoTransformSet() {
		$this->response->getHeaders()->setCacheControlDirective('no-transform');
		$value = 'Test';
		foreach ($this->getNonModifyableHeadersForNoTransform() as $header) {
			$this->response->setHeader($header, $value, TRUE);
		}
		$this->cache->storeResponse();
		foreach ($this->getNonModifyableHeadersForNoTransform() as $header) {
			$this->assertEquals($this->response->getHeader($header), $value);
		}
	}

	/**
	 * Check that the cache does add headers according to
	 * RFC2616 13.5.2
	 * @test
	 */
	public function storeResponseDoesNotAddHeadersIfNoTransformSet() {
		$this->response->getHeaders()->setCacheControlDirective('no-transform');
		foreach ($this->getNonAddableHeadersForNoTransform() as $header) {
			$this->response->getHeaders()->remove($header);
		}
		$this->cache->storeResponse();
		foreach ($this->getNonAddableHeadersForNoTransform() as $header) {
			$this->assertEquals($this->response->getHeader($header), NULL);
		}
	}

	/**
	 * Check that the cache does not modify headers according to
	 * RFC2616 13.5.2
	 * @test
	 */
	public function storeResponseDoesNotModifyHeaders() {
		$this->response->getHeaders()->removeCacheControlDirective('no-transform');
		$value = 'Test';
		foreach ($this->getNonModifyableHeaders() as $header) {
			$this->response->setHeader($header, $value, TRUE);
		}
		$this->cache->storeResponse();
		foreach ($this->getNonModifyableHeaders() as $header) {
			$this->assertEquals($this->response->getHeader($header), $value);
		}
	}

	/**
	 * Check that the cache does add headers according to
	 * RFC2616 13.5.2
	 * @test
	 */
	public function storeResponseDoesNotAddHeaders() {
		$this->response->getHeaders()->removeCacheControlDirective('no-transform');
		foreach ($this->getNonAddableHeaders() as $header) {
			$this->response->getHeaders()->remove($header);
		}
		$this->cache->storeResponse();
		foreach ($this->getNonAddableHeaders() as $header) {
			$this->assertEquals($this->response->getHeader($header), NULL);
		}
	}

	/**
	 * Check that the response is not stored again if it is the response we just got from cache
	 * @test
	 */
	public function storeResponseDoesNotStoreResponseThatWasFetchedPreviously() {
		$proxyLogger = $this->getMock('TYPO3\Proxy\Log\ProxyLoggerInterface');
		$this->cache = $this->getMock('TYPO3\Proxy\Http\Cache', array('has', 'isFresh', 'canFetch'), array($this->bootstrap, $this->frontend, $proxyLogger));
		$this->frontend->expects($this->any())->method('getEntryIdentifier')->will($this->returnValue('ID'));
		$this->frontend->expects($this->never())->method('set');
		$this->cache->expects($this->once())->method('has')->will($this->returnValue(TRUE));
		$this->cache->expects($this->once())->method('isFresh')->will($this->returnValue(TRUE));
		$this->cache->expects($this->once())->method('canFetch')->will($this->returnValue(TRUE));
		$response = $this->cache->fetchResponse($this->request);
		$this->assertTrue($response !== NULL);
		$this->cache->storeResponse();
	}

	/**
	 * Check that the response is not stored again if it is the response we just got from cache
	 * @test
	 */
	public function fetchResponseGetsPreviouslyStoredResponse() {
		$proxyLogger = $this->getMock('TYPO3\Proxy\Log\ProxyLoggerInterface');
		$this->cache = $this->getMock('TYPO3\Proxy\Http\Cache', array('has', 'isFresh', 'canFetch'), array($this->bootstrap, $this->frontend, $proxyLogger));
		$this->frontend->expects($this->any())->method('getEntryIdentifier')->will($this->returnValue('ID'));
		$this->cache->expects($this->once())->method('has')->will($this->returnValue(TRUE));
		$this->cache->expects($this->once())->method('isFresh')->will($this->returnValue(TRUE));
		$this->cache->expects($this->once())->method('canFetch')->will($this->returnValue(TRUE));
		$response = $this->cache->fetchResponse($this->request);
		$this->assertEquals($response, $this->response);
	}
}

?>