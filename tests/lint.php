<?php

$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root . '/upload', FilesystemIterator::SKIP_DOTS));
$failures = array();
$checked = 0;

foreach ($iterator as $file) {
	if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
		continue;
	}

	$checked++;
	$command = escapeshellarg(PHP_BINARY)
			. ' -d error_reporting=E_ALL -d display_errors=1 -l '
			. escapeshellarg($file->getPathname());
	$output = array();
	$exitCode = 0;
	exec($command . ' 2>&1', $output, $exitCode);
	$result = implode("\n", $output);

	if ($exitCode !== 0 || preg_match('/(?:Deprecated|Warning|Fatal error|Parse error):/', $result)) {
		$failures[] = $file->getPathname() . "\n" . $result;
	}
}

if ($failures) {
	fwrite(STDERR, implode("\n\n", $failures) . "\n");
	exit(1);
}

echo 'Checked ' . $checked . " PHP files without syntax or compatibility warnings.\n";
