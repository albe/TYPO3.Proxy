<?php
namespace TYPO3\Proxy\Tests\Unit\Http\Cache;

/*                                                                        *
 * This script belongs to the FLOW3 package "FLOW3.Proxy".                *
 *                                                                        *
 *                                                                        */

/**
 * Testcase for http cache frontend
 * @Flow\Scope("singleton")
 */
class RequestFrontendTest extends \TYPO3\Flow\Tests\UnitTestCase {


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
	 * @var \TYPO3\Flow\Cache\Backend\BackendInterface
	 */
	protected $backend;

	public function setUp() {
		$className = 'MockBackend' . mt_rand(1000,9999);
		eval('class '.$className.' implements \TYPO3\Flow\Cache\Backend\BackendInterface {
				private $data = array();
				public function setCache(\TYPO3\Flow\Cache\Frontend\FrontendInterface $cache) {}
				public function getPrefixedIdentifier($entryIdentifier) {}
				public function set($entryIdentifier, $data, array $tags = array(), $lifetime = NULL) { $this->data[$entryIdentifier] = $data; }
				public function get($entryIdentifier) { return $this->data[$entryIdentifier]; }
				public function has($entryIdentifier) { return isset($this->data[$entryIdentifier]); }
				public function remove($entryIdentifier) { unset($this->data[$entryIdentifier]); }
				public function flush() {}
				public function collectGarbage() {}
			  };'
			);
		$this->backend = $this->getMock($className, array('dummy'));
		$this->frontend = new \TYPO3\Proxy\Http\Cache\RequestFrontend('Test', $this->backend);
		$this->frontend = $this->getAccessibleMock('TYPO3\Proxy\Http\Cache\RequestFrontend', array('dummy'), array('Test', $this->backend));

		$uri = $this->getMock('TYPO3\Flow\Http\Uri', array(), array(), '', FALSE)
					->expects($this->any())->method('__toString')
					->will($this->returnValue('http://www.typo3.org/proxy/cache/test?foo=bar&baz=baz'));

		$request = $this->getAccessibleMock('TYPO3\Flow\Http\Request', array('getUri'), array(), '', FALSE);
		$request->expects($this->any())->method('getUri')
				->will($this->returnValue($uri));
		$this->inject($request, 'headers', new \TYPO3\Flow\Http\Headers());
		$this->inject($request, 'method', 'GET');
		$this->request = $request;

		$this->response = $this->getMock('TYPO3\Flow\Http\Response', array('dummy'));
		$this->response->setStatus(200, 'OK');
		$this->response->setContent('<html>Test</html>');
	}

	/**
	 * @test
	 */
	public function getReturnsPreviouslySetResponse() {
		$this->frontend->set('ID', $this->response);
		$this->assertTrue($this->frontend->has('ID'));
		$response = $this->frontend->get('ID');
		$this->assertEquals($this->response->getContent(), $response->getContent());
		$this->assertEquals($this->response->getStatus(), $response->getStatus());
		$this->assertEquals($this->response->renderHeaders(), $response->renderHeaders());
	}

	/**
	 * @test
	 */
	public function hasReturnsBool() {
		$has = $this->frontend->has('ID');
		$this->assertTrue(is_bool($has));
	}

	/**
	 * @test
	 */
	public function getReturnsResponse() {
		$this->backend->set('ID', 'HTTP/1.1 200 OK');
		$response = $this->frontend->get('ID');
		$this->assertInstanceOf('TYPO3\Flow\Http\Response', $response);
	}

}

?>