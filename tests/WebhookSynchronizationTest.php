<?php

define('DIR_SYSTEM', dirname(__DIR__) . '/upload/system/');

function modification($path){
	return $path;
}

require DIR_SYSTEM . 'library/wallee/autoload.php';

final class TestWebhookService extends \Wallee\Service\Webhook {
	private $urls = array();
	public $actions = array();

	public function __construct(array $urls = array()){
		foreach ($urls as $url) {
			$this->urls[$url] = new \Wallee\Sdk\Model\WebhookUrl(array(
				'id' => count($this->urls) + 1,
				'url' => $url,
				'version' => 1
			));
		}
	}

	public function install($space_id, $url){
		$this->actions[] = 'install:' . $url;
	}

	public function uninstall($space_id, $url){
		$this->actions[] = 'uninstall:' . $url;
	}

	protected function getWebhookUrl($space_id, $url){
		return isset($this->urls[$url]) ? $this->urls[$url] : null;
	}

	protected function updateWebhookUrl(
			$space_id,
			\Wallee\Sdk\Model\WebhookUrl $webhook_url,
			$url){
		$this->actions[] = 'update:' . $webhook_url->getUrl() . '->' . $url;
		return $webhook_url;
	}
}

final class FakeWebhookUrlApiService {
	public $spaceId;
	public $update;

	public function update($space_id, $update){
		$this->spaceId = $space_id;
		$this->update = $update;
		return $update;
	}
}

final class TestWebhookUpdateService extends \Wallee\Service\Webhook {
	private $apiService;

	public function __construct(FakeWebhookUrlApiService $apiService){
		$this->apiService = $apiService;
	}

	public function updateUrl($space_id, \Wallee\Sdk\Model\WebhookUrl $webhook_url, $url){
		return parent::updateWebhookUrl($space_id, $webhook_url, $url);
	}

	protected function getWebhookUrlService(){
		return $this->apiService;
	}
}

function assertActions(array $expected, TestWebhookService $service, $scenario){
	if ($service->actions !== $expected) {
		fwrite(STDERR, $scenario . " failed.\nExpected: " . json_encode($expected)
				. "\nActual: " . json_encode($service->actions) . "\n");
		exit(1);
	}
}

$oldUrl = 'https://shop.example/webhook';
$newUrl = 'https://www.shop.example/webhook';

$service = new TestWebhookService();
$service->synchronize(1, array(), $newUrl);
assertActions(array('install:' . $newUrl), $service, 'Invalid persisted URL');

$service = new TestWebhookService(array($oldUrl));
$service->synchronize(1, $oldUrl, $newUrl);
assertActions(array('update:' . $oldUrl . '->' . $newUrl, 'install:' . $newUrl), $service,
		'Update existing URL in place');

$service = new TestWebhookService(array($oldUrl, $newUrl));
$service->synchronize(1, $oldUrl, $newUrl);
assertActions(array('install:' . $newUrl, 'uninstall:' . $oldUrl), $service,
		'Prepare existing target before deleting old URL');

$service = new TestWebhookService();
$service->synchronize(1, $oldUrl, $newUrl);
assertActions(array('install:' . $newUrl), $service, 'Recover missing persisted URL');

$apiService = new FakeWebhookUrlApiService();
$service = new TestWebhookUpdateService($apiService);
$webhookUrl = new \Wallee\Sdk\Model\WebhookUrl(array(
	'id' => 42,
	'name' => 'OpenCart',
	'state' => \Wallee\Sdk\Model\CreationEntityState::ACTIVE,
	'url' => $oldUrl,
	'version' => 7
));
$service->updateUrl(99, $webhookUrl, $newUrl);

if ($apiService->spaceId !== 99
		|| $apiService->update->getId() !== 42
		|| $apiService->update->getVersion() !== 7
		|| $apiService->update->getName() !== 'OpenCart'
		|| $apiService->update->getState() !== \Wallee\Sdk\Model\CreationEntityState::ACTIVE
		|| $apiService->update->getUrl() !== $newUrl) {
	fwrite(STDERR, "In-place webhook URL update did not preserve the entity metadata.\n");
	exit(1);
}

echo "Webhook synchronization scenarios passed.\n";
