<?php
declare(strict_types=1);

/**
 * Wallee OpenCart
 *
 * This OpenCart module enables to process payments with Wallee (https://www.wallee.com).
 *
 * @package Whitelabelshortcut\Wallee
 * @author wallee AG (https://www.wallee.com)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
require_once (DIR_SYSTEM . 'library/wallee/autoload.php');

/**
 * Versioning helper which offers implementations depending on opencart version. (Internal) Some version differences may be handled via rewriter.
 *
 * @author wallee AG (https://www.wallee.com)
 *
 */
class WalleeVersionHelper {
	public const TOKEN = 'user_token';

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function getModifications(): array {
		return [
			'WalleeCore' => [
				'file' => 'WalleeCore.ocmod.xml',
				'default_status' => 1 
			],
			'WalleeAlerts' => [
				'file' => 'WalleeAlerts.ocmod.xml',
				'default_status' => 1 
			],
			'WalleeAdministration' => [
				'file' => 'WalleeAdministration.ocmod.xml',
				'default_status' => 1 
			],
			'WalleeQuickCheckoutCompatibility' => [
				'file' => 'WalleeQuickCheckoutCompatibility.ocmod.xml',
				'default_status' => 0 
			],
			'WalleeXFeeProCompatibility' => [
				'file' => 'WalleeXFeeProCompatibility.ocmod.xml',
				'default_status' => 0
			],
			'WalleePreventConfirmationEmail' => [
				'file' => 'WalleePreventConfirmationEmail.ocmod.xml',
				'default_status' => 0 
			],
			'WalleeFrontendPdf' => [
				'file' => 'WalleeFrontendPdf.ocmod.xml',
				'default_status' => 1 
			],
			'WalleeTransactionView' => [
				'file' => 'WalleeTransactionView.ocmod.xml',
				'default_status' => 1
			]
		];
	}

	/**
	 * @param \Registry $registry
	 * @param string $content
	 * @return string
	 */
	public static function wrapJobLabels(\Registry $registry, string $content): string {
		return $content;
	}

	/**
	 * @param mixed $value
	 * @param mixed $default
	 * @return mixed
	 */
	public static function getPersistableSetting($value, $default) {
		return $value;
	}

	/**
	 * @param string $theme
	 * @param string $template
	 * @return string
	 */
	public static function getTemplate(string $theme, string $template): string {
		return $template;
	}

	/**
	 * @param \Registry $registry
	 * @return \Cart\Tax
	 */
	public static function newTax(\Registry $registry): \Cart\Tax {
		return new \Cart\Tax($registry);
	}

	/**
	 * @param \Registry $registry
	 * @return array<int, array<string, mixed>>
	 */
	public static function getSessionTotals(\Registry $registry): array {
		$registry->get('load')->model('setting/extension');
		
		$totals = [];
		$taxes = $registry->get('cart')->getTaxes();
		$total = 0;
		
		// Because __call can not keep var references so we put them into an array.
		$total_data = [
			'totals' => &$totals,
			'taxes' => &$taxes,
			'total' => &$total
		];
		
		$sort_order = [];
		$results = $registry->get('model_setting_extension')->getExtensions('total');
		foreach ($results as $key => $value) {
			$sort_order[$key] = $registry->get('config')->get('total_' . $value['code'] . '_sort_order');
		}
		
		array_multisort($sort_order, SORT_ASC, $results);
		
		foreach ($results as $result) {
			if ($registry->get('config')->get('total_' . $result['code'] . '_status')) {
				$registry->get('load')->model('extension/total/' . $result['code']);
				
				// We have to put the totals in an array so that they pass by reference.
				$registry->get('model_extension_total_' . $result['code'])->getTotal($total_data);
			}
		}
		
		$sort_order = [];
		
		foreach ($totals as $key => $value) {
			$sort_order[$key] = $value['sort_order'];
		}
		
		array_multisort($sort_order, SORT_ASC, $totals);
		return $total_data['totals'];
	}
	
	/**
	 * @param \Registry $registry
	 * @param array<string, mixed> $post
	 * @throws \RuntimeException
	 */
	public static function persistPluginStatus(\Registry $registry, array $post): void {
		if (!isset($post['wallee_status'], $post['id'])) {
			throw new \RuntimeException('Erforderliche Post-Parameter fehlen.');
		}
		
		$status = [
			'payment_wallee_status' => $post['wallee_status']
		];
		
		$registry->get('model_setting_setting')->editSetting('payment_wallee', $status, $post['id']);
	}
	
	/**
	 * @param string $code
	 * @return string
	 */
	public static function extractPaymentSettingCode(string $code): string {
		return 'payment_' . $code;
	}

	/**
	 * @param array<string, mixed> $language
	 * @return string
	 * @throws \RuntimeException
	 */
	public static function extractLanguageDirectory(array $language): string {
		if (!isset($language['code'])) {
			throw new \RuntimeException('Sprachcode nicht gefunden.');
		}
		return $language['code'];
	}

	/**
	 * @param \Url $url_provider
	 * @param string $route
	 * @param string|array<string, mixed> $query
	 * @param bool $ssl
	 * @return string
	 * @throws \RuntimeException
	 */
	public static function createUrl(\Url $url_provider, string $route, $query, bool $ssl): string {
		if ($route === 'extension/payment') {
			$route = 'marketplace/extension';
			// all calls with extension/payment createUrl use array
			if (is_array($query)) {
				$query['type'] = 'payment';
			}
		}
		
		if (is_array($query)) {
			$query = http_build_query($query);
		} elseif (!is_string($query)) {
			throw new \RuntimeException(
				"Query muss vom Typ string oder array sein, " . get_class($query) . " wurde übergeben."
			);
		}
		
		return $url_provider->link($route, $query, $ssl);
	}
}