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

use Wallee\Webhook\Entity;

/**
 * This service handles webhooks.
 */
class Webhook extends AbstractService {
	
	/**
	 * The webhook listener API service.
	 *
	 * @var \Wallee\Sdk\Service\WebhookListenerService
	 */
	private ?\Wallee\Sdk\Service\WebhookListenerService $webhook_listener_service = null;
	
	/**
	 * The webhook url API service.
	 *
	 * @var \Wallee\Sdk\Service\WebhookUrlService
	 */
	private ?\Wallee\Sdk\Service\WebhookUrlService $webhook_url_service = null;

	/**
	 * Array of webhook entities.
	 *
	 * @var array<int, Entity>
	 */
	private array $webhook_entities = [];

	/**
	 * Constructor to register the webhook entities.
	 *
	 * @param \Registry $registry
	 */
	public function __construct(\Registry $registry) {
		parent::__construct($registry);
		
		$this->webhook_entities[1472041867364] = new Entity(
			1472041867364,
			'Transaction Void',
			[
				\Wallee\Sdk\Model\TransactionVoidState::FAILED,
				\Wallee\Sdk\Model\TransactionVoidState::SUCCESSFUL
			],
			'Wallee\Webhook\TransactionVoid'
		);
		
		$this->webhook_entities[1472041839405] = new Entity(
			1472041839405,
			'Refund',
			[
				\Wallee\Sdk\Model\RefundState::FAILED,
				\Wallee\Sdk\Model\RefundState::SUCCESSFUL
			],
			'Wallee\Webhook\TransactionRefund'
		);

		$this->webhook_entities[1472041806455] = new Entity(
			1472041806455,
			'Token',
			[
				\Wallee\Sdk\Model\CreationEntityState::ACTIVE,
				\Wallee\Sdk\Model\CreationEntityState::DELETED,
				\Wallee\Sdk\Model\CreationEntityState::DELETING,
				\Wallee\Sdk\Model\CreationEntityState::INACTIVE
			],
			'Wallee\Webhook\Token'
		);

		$this->webhook_entities[1472041811051] = new Entity(
			1472041811051,
			'Token Version',
			[
				\Wallee\Sdk\Model\TokenVersionState::ACTIVE,
				\Wallee\Sdk\Model\TokenVersionState::OBSOLETE
			],
			'Wallee\Webhook\TokenVersion'
		);
	}

	/**
	 * Installs the necessary webhooks in Wallee.
	 *
	 * @param int $space_id
	 * @param string $url
	 * @return bool
	 * @throws \RuntimeException
	 */
	public function install(int $space_id, string $url): bool {
		if (empty($url)) {
			throw new \RuntimeException('Die Webhook-URL darf nicht leer sein.');
		}

		$webhook_url = $this->getWebhookUrl($space_id, $url);
		if ($webhook_url === null) {
			$webhook_url = $this->createWebhookUrl($space_id, $url);
		}

		$existing_listeners = $this->getWebhookListeners($space_id, $webhook_url);
		foreach ($this->webhook_entities as $webhook_entity) {
			$exists = false;
			foreach ($existing_listeners as $existing_listener) {
				if ($existing_listener->getEntity() === $webhook_entity->getId()) {
					$exists = true;
					break;
				}
			}
			if (!$exists) {
				$this->createWebhookListener($webhook_entity, $space_id, $webhook_url);
			}
		}

		return true;
	}

	/**
	 * Gets the webhook URL from Wallee.
	 *
	 * @param int $space_id
	 * @param string $url
	 * @return \Wallee\Sdk\Model\WebhookUrl|null
	 */
	private function getWebhookUrl(int $space_id, string $url): ?\Wallee\Sdk\Model\WebhookUrl {
		$query = new \Wallee\Sdk\Model\EntityQuery();
		$filter = new \Wallee\Sdk\Model\EntityQueryFilter();
		$filter->setType(\Wallee\Sdk\Model\EntityQueryFilterType::_AND);
		$filter->setChildren([
			$this->createEntityFilter('state', \Wallee\Sdk\Model\CreationEntityState::ACTIVE),
			$this->createEntityFilter('url', $url)
		]);
		$query->setFilter($filter);
		
		$webhook_service = $this->getWebhookUrlService();
		$result = $webhook_service->search($space_id, $query);
		
		if (!empty($result)) {
			return current($result);
		}
		
		return null;
	}

	/**
	 * Creates a webhook URL.
	 *
	 * @param int $space_id
	 * @param string $url
	 * @return \Wallee\Sdk\Model\WebhookUrl
	 */
	private function createWebhookUrl(int $space_id, string $url): \Wallee\Sdk\Model\WebhookUrl {
		$webhook_url = new \Wallee\Sdk\Model\WebhookUrlCreate();
		$webhook_url->setUrl($url);
		$webhook_url->setState(\Wallee\Sdk\Model\CreationEntityState::ACTIVE);
		return $this->getWebhookUrlService()->create($space_id, $webhook_url);
	}

	/**
	 * Gets the webhook listeners from Wallee.
	 *
	 * @param int $space_id
	 * @param \Wallee\Sdk\Model\WebhookUrl $webhook_url
	 * @return array<\Wallee\Sdk\Model\WebhookListener>
	 */
	private function getWebhookListeners(int $space_id, \Wallee\Sdk\Model\WebhookUrl $webhook_url): array {
		$query = new \Wallee\Sdk\Model\EntityQuery();
		$filter = new \Wallee\Sdk\Model\EntityQueryFilter();
		$filter->setType(\Wallee\Sdk\Model\EntityQueryFilterType::_AND);
		$filter->setChildren([
			$this->createEntityFilter('state', \Wallee\Sdk\Model\CreationEntityState::ACTIVE),
			$this->createEntityFilter('url.id', $webhook_url->getId())
		]);
		$query->setFilter($filter);
		
		return $this->getWebhookListenerService()->search($space_id, $query);
	}

	/**
	 * Creates a webhook listener.
	 *
	 * @param Entity $entity
	 * @param int $space_id
	 * @param \Wallee\Sdk\Model\WebhookUrl $webhook_url
	 * @return \Wallee\Sdk\Model\WebhookListener
	 */
	private function createWebhookListener(
		Entity $entity,
		int $space_id,
		\Wallee\Sdk\Model\WebhookUrl $webhook_url
	): \Wallee\Sdk\Model\WebhookListener {
		$webhook_listener = new \Wallee\Sdk\Model\WebhookListenerCreate();
		$webhook_listener->setEntity($entity->getId());
		$webhook_listener->setEntityStates($entity->getStates());
		$webhook_listener->setName('Opencart ' . $entity->getName());
		$webhook_listener->setState(\Wallee\Sdk\Model\CreationEntityState::ACTIVE);
		$webhook_listener->setUrl($webhook_url->getId());
		$webhook_listener->setNotifyEveryChange(false);
		return $this->getWebhookListenerService()->create($space_id, $webhook_listener);
	}

	/**
	 * Gets the webhook listener service.
	 *
	 * @return \Wallee\Sdk\Service\WebhookListenerService
	 */
	private function getWebhookListenerService(): \Wallee\Sdk\Service\WebhookListenerService {
		if ($this->webhook_listener_service === null) {
			$this->webhook_listener_service = new \Wallee\Sdk\Service\WebhookListenerService(
				\WalleeHelper::instance($this->registry)->getApiClient()
			);
		}
		return $this->webhook_listener_service;
	}

	/**
	 * Gets the webhook URL service.
	 *
	 * @return \Wallee\Sdk\Service\WebhookUrlService
	 */
	private function getWebhookUrlService(): \Wallee\Sdk\Service\WebhookUrlService {
		if ($this->webhook_url_service === null) {
			$this->webhook_url_service = new \Wallee\Sdk\Service\WebhookUrlService(
				\WalleeHelper::instance($this->registry)->getApiClient()
			);
		}
		return $this->webhook_url_service;
	}
}