<?php

define('DIR_SYSTEM', dirname(__DIR__) . '/upload/system/');

function modification($path){
	return $path;
}

class Controller {
}

require dirname(__DIR__) . '/upload/catalog/controller/extension/wallee/cron.php';

final class TestCronController extends ControllerExtensionWalleeCron {
	public function isExpectedDuplicate($message){
		return parent::isDuplicateConstraintError($message);
	}
}

function assertCronFilter($expected, $actual, $scenario){
	if ($expected !== $actual) {
		fwrite(STDERR, $scenario . " failed.\n");
		exit(1);
	}
}

$controller = new TestCronController();

assertCronFilter(true, $controller->isExpectedDuplicate(
		"SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1' for key 'constraint_key'"),
		'Expected duplicate constraint error');
assertCronFilter(false, $controller->isExpectedDuplicate(
		"SQLSTATE[23000]: Integrity constraint violation for key 'constraint_key'"),
		'Constraint error without duplicate code');
assertCronFilter(false, $controller->isExpectedDuplicate(
		"SQLSTATE[23000]: 1062 Duplicate entry for key 'another_key'"),
		'Duplicate error for another key');
assertCronFilter(false, $controller->isExpectedDuplicate('Connection to database lost'),
		'Unrelated cron error');

echo "Cron error-filter scenarios passed.\n";
