<?php
/**
 * MediaConverter Pro - Master Test Runner
 *
 * Runs all automated integration tests covering FFmpeg, Validator, SSRF, Security, and Database.
 */

$start = microtime( true );

echo "========================================================\n";
echo "   MediaConverter Pro (PrismConvert) - Test Suite       \n";
echo "========================================================\n\n";

require_once __DIR__ . '/test_database.php';
require_once __DIR__ . '/test_security.php';
require_once __DIR__ . '/test_validator.php';
require_once __DIR__ . '/test_ffmpeg.php';

$elapsed = round( microtime( true ) - $start, 2 );

echo "========================================================\n";
echo "   [SUCCESS] ALL TEST SUITES PASSED! (Duration: {$elapsed}s)\n";
echo "========================================================\n";
