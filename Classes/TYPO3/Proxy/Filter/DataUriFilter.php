<?php
namespace TYPO3\Proxy\Filter;

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
use TYPO3\Flow\Http\Response;

/**
 * A filter which replaces file links by their DataURI
 *
 */
class DataUriFilter implements FilterInterface {

	/**
	 * DataURI encode a file
	 * @param string $filename
	 * @return string The DataUri representing the file or the original filename
	 */
	protected function dataUri($filename) {
		if (!file_exists($filename)) {
			return $filename;
		}

		$mimeType = mime_content_type($filename);
		$data = base64_encode(file_get_contents($filename));

		return "data:$mimeType;base64,$data";
	}

	/**
	 * Filter the response body and replace all local file references with their DataURI
	 * @param Response $response
	 * @return void
	 */
	public function filter(Response $response) {
		$content = $response->getContent();

		if (!preg_match_all('/src=["\']?(.*?)["\']?[\s>\/]/', $content, $matches)) {
			return;
		}

		$srcs = array_count_values($matches[1]);
		$srcs = array_filter($srcs, function($value) { return $value < 2; });
		foreach ($srcs as $src) {
				// TODO: Resolve $src to full filename
			$filename = $_SERVER['DOCUMENT_ROOT'] . $src;
			$content = preg_replace('/src=["\']?'.$src.'["\']', $this->dataUri($src), $content);
		}

		$response->setContent($content);
	}
}