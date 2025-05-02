<?php
$testFile = __DIR__ . '/test_write.txt';
file_put_contents($testFile, date('Y-m-d H:i:s') . " - Test write\n");
echo "Test complete. Check test_write.txt"; 