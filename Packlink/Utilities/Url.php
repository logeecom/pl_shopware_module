<?php

namespace Packlink\Utilities;

class Url
{
	/**
	 * Retrieves Front controller url.
	 *
	 * @param $controller
	 * @param $action
	 * @param array $params
	 *
	 * @return string
	 */
	public static function getFrontUrl($controller, $action, array $params = [])
	{
		$params = array_merge([
			'module' => 'frontend',
			'controller' => $controller,
			'action' => $action,
		],
			$params);

		$url = Shopware()->Front()->Router()->assemble($params);

		return str_replace('http:', 'https:', $url);
	}

	/**
	 * Get backend controller url.
	 *
	 * @param $controller
	 * @param $action
	 * @param array $params
	 *
	 * @return mixed|string
	 */
	public static function getBackendUrl($controller, $action, array $params = [])
	{
		$version = Shopware()->Config()->version;
		$backendSession = version_compare($version, '5.7.0', '<')? 'BackendSession' : 'backendsession';

		$csrfToken = Shopware()->Container()->get($backendSession)->offsetGet('X-CSRF-Token');

		$params = array_merge([
			'module' => 'backend',
			'controller' => $controller,
			'action' => $action,
		],
			array_merge($params, ['__csrf_token' => $csrfToken]));

		return Shopware()->Front()->Router()->assemble($params);
	}
}
