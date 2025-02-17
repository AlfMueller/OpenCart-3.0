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

/**
 * This service provides methods to handle manual tasks.
 */
class ManualTask extends AbstractService {
	/**
	 * Configuration key for storing the number of manual tasks.
	 */
	private const CONFIG_KEY = 'wallee_manual_task';

	/**
	 * Returns the number of open manual tasks.
	 *
	 * @return int The number of open manual tasks
	 */
	public function getNumberOfManualTasks(): int {
		$num = $this->registry->get('config')->get(self::CONFIG_KEY);
		return $num === null ? 0 : (int)$num;
	}

	/**
	 * Updates the number of open manual tasks.
	 *
	 * @return int The updated number of manual tasks
	 * @throws \RuntimeException When the space ID is not configured or when the update fails
	 */
	public function update(): int {
		$space_id = $this->registry->get('config')->get('wallee_space_id');
		
		if (empty($space_id)) {
			throw new \RuntimeException('Space ID ist nicht konfiguriert.');
		}

		try {
			$manual_task_service = new \Wallee\Sdk\Service\ManualTaskService(
				\WalleeHelper::instance($this->registry)->getApiClient()
			);
			
			$number_of_manual_tasks = (int)$manual_task_service->count(
				$space_id,
				$this->createEntityFilter('state', \Wallee\Sdk\Model\ManualTaskState::OPEN)
			);

			$this->saveManualTaskCount($number_of_manual_tasks);
			
			return $number_of_manual_tasks;
		} catch (\Exception $e) {
			throw new \RuntimeException(
				sprintf('Fehler beim Aktualisieren der manuellen Aufgaben: %s', $e->getMessage()),
				$e->getCode(),
				$e
			);
		}
	}

	/**
	 * Saves the manual task count to the database and updates the alert.
	 *
	 * @param int $count The number of manual tasks
	 * @throws \RuntimeException When the database update fails
	 */
	private function saveManualTaskCount(int $count): void {
		$table = DB_PREFIX . 'setting';
		$store_id = (int)($this->registry->get('config')->get('config_store_id') ?? 0);
		
		\Wallee\Entity\Alert::loadManualTask($this->registry)
			->setCount($count)
			->save();

		$query = sprintf(
			"UPDATE %s SET `value`='%d' WHERE `code`='wallee' AND `key`='%s' AND `store_id`='%d';",
			$table,
			$count,
			self::CONFIG_KEY,
			$store_id
		);

		if (!$this->registry->get('db')->query($query)) {
			throw new \RuntimeException('Fehler beim Speichern der Anzahl manueller Aufgaben in der Datenbank.');
		}
	}
}