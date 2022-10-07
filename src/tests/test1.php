<?php

declare(strict_types=1);
require_once(__DIR__ . '/../httpreadfile/httpreadfile.php');
ob_start();
\Divinity76\httpreadfile\httpreadfile(__FILE__);
$outputted = ob_get_clean();
$shouldBe = file_get_contents(__FILE__);
if ($outputted !== $shouldBe) {
    echo "outputted !== shouldBe" . PHP_EOL;
    echo "TEST FAILED!";
    exit(1);
} else {
    echo "TEST PASSED!";
    exit(0);
}

// todo add $_SERVER tests for not-modified and range and stuff.