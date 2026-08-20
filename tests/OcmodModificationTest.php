<?php

$xmlPath = dirname(__DIR__) . '/upload/system/library/wallee/modification/WalleeCore.ocmod.xml';
$xml = file_get_contents($xmlPath);
$pattern = '~(require|include)(_once)?\((?!modification\()([^)]+)~';
$replacement = '$1$2(modification($3)';

function assertOcmod($expected, $actual, $scenario){
	if ($expected !== $actual) {
		fwrite(STDERR, $scenario . " failed.\nExpected: $expected\nActual: $actual\n");
		exit(1);
	}
}

assertOcmod(2, substr_count($xml, $pattern),
		'Language and action OCMOD rules must both use the guarded pattern');

$action = 'include_once($file);';
$modifiedAction = preg_replace($pattern, $replacement, $action);
assertOcmod('include_once(modification($file));', $modifiedAction,
		'Unmodified action include');
assertOcmod($modifiedAction, preg_replace($pattern, $replacement, $modifiedAction),
		'Reapplying action modification');

$language = 'require($file);';
$modifiedLanguage = preg_replace($pattern, $replacement, $language);
assertOcmod('require(modification($file));', $modifiedLanguage,
		'Unmodified language require');
assertOcmod($modifiedLanguage, preg_replace($pattern, $replacement, $modifiedLanguage),
		'Reapplying language modification');

echo "OCMOD modification scenarios passed.\n";
