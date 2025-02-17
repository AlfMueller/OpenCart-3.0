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

use Wallee\Sdk\Model\PaymentMethodConfiguration;
use Wallee\Sdk\Model\CreationEntityState;
use Wallee\Sdk\Model\EntityQuery;
use Wallee\Sdk\Service\PaymentMethodConfigurationService;
use Wallee\Entity\MethodConfiguration as MethodConfigurationEntity;
use Wallee\Provider\PaymentMethod as PaymentMethodProvider;

/**
 * This service handles the payment method configuration synchronization and updates.
 */
class MethodConfiguration extends AbstractService {
	
	/**
	 * Updates the data of the payment method configuration.
	 *
	 * @param PaymentMethodConfiguration $configuration The configuration to update
	 * @return void
	 */
	public function updateData(PaymentMethodConfiguration $configuration): void {
		$entity = MethodConfigurationEntity::loadByConfiguration(
			$this->registry,
			$configuration->getLinkedSpaceId(),
			$configuration->getId()
		);
		
		if ($entity->getId() !== null && $this->hasChanged($configuration, $entity)) {
			$entity->setConfigurationName($configuration->getName());
			$entity->setTitle($configuration->getResolvedTitle());
			$entity->setDescription($configuration->getResolvedDescription());
			$entity->setImage($configuration->getResolvedImageUrl());
			$entity->setSortOrder($configuration->getSortOrder());
			$entity->save();
		}
	}

	/**
	 * Checks if the configuration has changed compared to the entity.
	 *
	 * @param PaymentMethodConfiguration $configuration The configuration to check
	 * @param MethodConfigurationEntity $entity The entity to compare with
	 * @return bool True if changes were detected
	 */
	private function hasChanged(
		PaymentMethodConfiguration $configuration,
		MethodConfigurationEntity $entity
	): bool {
		if ($configuration->getName() !== $entity->getConfigurationName()) {
			return true;
		}
		
		if ($configuration->getResolvedTitle() !== $entity->getTitle()) {
			return true;
		}
		
		if ($configuration->getResolvedDescription() !== $entity->getDescription()) {
			return true;
		}
		
		if ($configuration->getResolvedImageUrl() !== $entity->getImage()) {
			return true;
		}
		
		if ($configuration->getSortOrder() !== $entity->getSortOrder()) {
			return true;
		}
		
		return false;
	}

	/**
	 * Synchronizes the payment method configurations from Wallee.
	 *
	 * @param int $space_id The space ID to synchronize
	 * @return void
	 * @throws \RuntimeException When synchronization fails
	 */
	public function synchronize(int $space_id): void {
		$existing_found = [];
		$existing_configurations = MethodConfigurationEntity::loadBySpaceId($this->registry, $space_id);
		
		$payment_method_configuration_service = new PaymentMethodConfigurationService(
			\WalleeHelper::instance($this->registry)->getApiClient()
		);
		$configurations = $payment_method_configuration_service->search($space_id, new EntityQuery());
		
		foreach ($configurations as $configuration) {
			$method = MethodConfigurationEntity::loadByConfiguration(
				$this->registry,
				$space_id,
				$configuration->getId()
			);
			
			if ($method->getId() !== null) {
				$existing_found[] = $method->getId();
			}
			
			$method->setSpaceId($space_id);
			$method->setConfigurationId($configuration->getId());
			$method->setConfigurationName($configuration->getName());
			$method->setState($this->getConfigurationState($configuration));
			$method->setTitle($configuration->getResolvedTitle());
			$method->setDescription($configuration->getResolvedDescription());
			$method->setImage($configuration->getResolvedImageUrl());
			$method->setSortOrder($configuration->getSortOrder());
			$method->save();
		}
		
		foreach ($existing_configurations as $existing_configuration) {
			if (!in_array($existing_configuration->getId(), $existing_found, true)) {
				$existing_configuration->setState(MethodConfigurationEntity::STATE_HIDDEN);
				$existing_configuration->save();
			}
		}
		
		PaymentMethodProvider::instance($this->registry)->clearCache();
	}

	/**
	 * Returns the payment method for the given id.
	 *
	 * @param int $id The payment method ID
	 * @return \Wallee\Sdk\Model\PaymentMethod The payment method
	 */
	protected function getPaymentMethod(int $id): \Wallee\Sdk\Model\PaymentMethod {
		return PaymentMethodProvider::instance($this->registry)->find($id);
	}

	/**
	 * Returns the state for the payment method configuration.
	 *
	 * @param PaymentMethodConfiguration $configuration The configuration to get the state for
	 * @return string The configuration state
	 */
	protected function getConfigurationState(PaymentMethodConfiguration $configuration): string {
		return match ($configuration->getState()) {
			CreationEntityState::ACTIVE => MethodConfigurationEntity::STATE_ACTIVE,
			CreationEntityState::INACTIVE => MethodConfigurationEntity::STATE_INACTIVE,
			default => MethodConfigurationEntity::STATE_HIDDEN,
		};
	}
}