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
 * A filter which post processes a response body
 *
 */
interface FilterInterface {

	/**
	 * Filter the response body
	 * @param Response $response
	 * @return void
	 */
	public function filter(Response $response);
}