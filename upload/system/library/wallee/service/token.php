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

namespace Wallee\Service;

use Wallee\Sdk\Model\TokenVersion;
use Wallee\Sdk\Model\TokenVersionState;
use Wallee\Sdk\Model\EntityQuery;
use Wallee\Sdk\Model\EntityQueryFilter;
use Wallee\Sdk\Model\EntityQueryFilterType;
use Wallee\Sdk\Service\TokenService as TokenApiService;
use Wallee\Sdk\Service\TokenVersionService as TokenVersionApiService;

/**
 * This service provides functions to deal with Wallee tokens.
 */
class Token extends AbstractService {
	
	/**
	 * The token API service.
	 */
	private ?TokenApiService $token_service = null;
	
	/**
	 * The token version API service.
	 */
	private ?TokenVersionApiService $token_version_service = null;

	/**
	 * Updates a token version.
	 *
	 * @param int $space_id The space ID
	 * @param int $token_version_id The token version ID
	 * @return void
	 * @throws \RuntimeException When the token version cannot be updated
	 */
	public function updateTokenVersion(int $space_id, int $token_version_id): void {
		$token_version = $this->getTokenVersionService()->read($space_id, $token_version_id);
		$this->updateInfo($space_id, $token_version);
	}

	/**
	 * Updates a token.
	 *
	 * @param int $space_id The space ID
	 * @param int $token_id The token ID
	 * @return void
	 * @throws \RuntimeException When the token cannot be updated
	 */
	public function updateToken(int $space_id, int $token_id): void {
		$query = new EntityQuery();
		$filter = new EntityQueryFilter();
		$filter->setType(EntityQueryFilterType::_AND);
		$filter->setChildren([
			$this->createEntityFilter('token.id', $token_id),
			$this->createEntityFilter('state', TokenVersionState::ACTIVE)
		]);
		$query->setFilter($filter);
		$query->setNumberOfEntities(1);
		
		$token_versions = $this->getTokenVersionService()->search($space_id, $query);
		if (!empty($token_versions)) {
			$this->updateInfo($space_id, current($token_versions));
		} else {
			$info = \Wallee\Entity\TokenInfo::loadByToken($this->registry, $space_id, $token_id);
			if ($info->getId()) {
				$info->delete($this->registry);
			}
		}
	}

	/**
	 * Updates the token information.
	 *
	 * @param int $space_id The space ID
	 * @param TokenVersion $token_version The token version
	 * @return void
	 */
	protected function updateInfo(int $space_id, TokenVersion $token_version): void {
		$info = \Wallee\Entity\TokenInfo::loadByToken($this->registry, $space_id, $token_version->getToken()->getId());
		if (!in_array($token_version->getToken()->getState(), [
			TokenVersionState::ACTIVE,
			TokenVersionState::UNINITIALIZED
		], true)) {
			if ($info->getId()) {
				$info->delete($this->registry);
			}
			return;
		}
		
		$info->setCustomerId($token_version->getToken()->getCustomerId());
		$info->setName($token_version->getName());
		
		$payment_method = \Wallee\Entity\MethodConfiguration::loadByConfiguration(
			$this->registry,
			$space_id,
			$token_version->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration()->getId()
		);
		$info->setPaymentMethodId($payment_method->getId());
		$info->setConnectorId($token_version->getPaymentConnectorConfiguration()->getConnector());
		
		$info->setSpaceId($space_id);
		$info->setState($token_version->getToken()->getState());
		$info->setTokenId($token_version->getToken()->getId());
		$info->save();
	}

	/**
	 * Deletes a token.
	 *
	 * @param int $space_id The space ID
	 * @param int $token_id The token ID
	 * @return void
	 * @throws \RuntimeException When the token cannot be deleted
	 */
	public function deleteToken(int $space_id, int $token_id): void {
		$this->getTokenService()->delete($space_id, $token_id);
	}

	/**
	 * Returns the token API service.
	 *
	 * @return TokenApiService
	 */
	protected function getTokenService(): TokenApiService {
		if ($this->token_service === null) {
			$this->token_service = new TokenApiService(\WalleeHelper::instance($this->registry)->getApiClient());
		}
		return $this->token_service;
	}

	/**
	 * Returns the token version API service.
	 *
	 * @return TokenVersionApiService
	 */
	protected function getTokenVersionService(): TokenVersionApiService {
		if ($this->token_version_service === null) {
			$this->token_version_service = new TokenVersionApiService(\WalleeHelper::instance($this->registry)->getApiClient());
		}
		return $this->token_version_service;
	}
}