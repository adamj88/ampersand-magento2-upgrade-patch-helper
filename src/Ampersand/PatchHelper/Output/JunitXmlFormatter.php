<?php

namespace Ampersand\PatchHelper\Output;

use Ampersand\PatchHelper\Helper\PatchOverrideValidator;

class JunitXmlFormatter
{
    /**
     * Generate JUnit XML report from analysis results
     *
     * @param array $summaryOutputData
     * @param int $warnLevelCount
     * @param int $infoLevelCount
     * @param int $ignoreLevelCount
     * @param string $projectDir
     * @return string
     */
    public function format(
        array $summaryOutputData,
        int $warnLevelCount,
        int $infoLevelCount,
        int $ignoreLevelCount,
        string $projectDir
    ): string {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $totalTests = count($summaryOutputData);
        $failures = $warnLevelCount;
        $skipped = $ignoreLevelCount;

        // Create root testsuites element
        $testsuites = $dom->createElement('testsuites');
        $testsuites->setAttribute('name', 'Magento 2 Upgrade Patch Helper');
        $testsuites->setAttribute('tests', (string) $totalTests);
        $testsuites->setAttribute('failures', (string) $failures);
        $testsuites->setAttribute('skipped', (string) $skipped);
        $testsuites->setAttribute('time', '0');
        $dom->appendChild($testsuites);

        // Group tests by type
        $groupedTests = [];
        foreach ($summaryOutputData as $testData) {
            $level = $testData[0];
            $type = $testData[1];
            $file = $testData[2];
            $check = $testData[3];

            if (!isset($groupedTests[$type])) {
                $groupedTests[$type] = [];
            }
            $groupedTests[$type][] = [$level, $type, $file, $check];
        }

        // Create testsuite for each type
        foreach ($groupedTests as $type => $tests) {
            $testsuite = $dom->createElement('testsuite');
            $testsuite->setAttribute('name', $type);
            $testsuite->setAttribute('tests', (string) count($tests));
            
            $suiteFailures = count(array_filter($tests, function($test) {
                return $test[0] === PatchOverrideValidator::LEVEL_WARN;
            }));
            $suiteSkipped = count(array_filter($tests, function($test) {
                return $test[0] === PatchOverrideValidator::LEVEL_IGNORE;
            }));
            
            $testsuite->setAttribute('failures', (string) $suiteFailures);
            $testsuite->setAttribute('skipped', (string) $suiteSkipped);
            $testsuite->setAttribute('time', '0');

            foreach ($tests as $test) {
                $level = $test[0];
                $type = $test[1];
                $file = $test[2];
                $check = $test[3];

                $testcase = $dom->createElement('testcase');
                $testcase->setAttribute('classname', $type);
                $testcase->setAttribute('name', $this->sanitizeFilePath($projectDir, $file));
                $testcase->setAttribute('time', '0');

                if ($level === PatchOverrideValidator::LEVEL_WARN) {
                    $failure = $dom->createElement('failure');
                    $failure->setAttribute('message', 'Requires Review');
                    $failure->setAttribute('type', 'Warning');
                    $failure->appendChild($dom->createTextNode(
                        "File: {$file}\nCheck: {$check}\nLevel: {$level}\nType: {$type}"
                    ));
                    $testcase->appendChild($failure);
                } elseif ($level === PatchOverrideValidator::LEVEL_IGNORE) {
                    $skipped = $dom->createElement('skipped');
                    $skipped->setAttribute('message', 'Ignored - No Action Required');
                    $skipped->appendChild($dom->createTextNode(
                        "File: {$file}\nCheck: {$check}"
                    ));
                    $testcase->appendChild($skipped);
                } else {
                    // INFO level - add as system-out for informational purposes
                    $systemOut = $dom->createElement('system-out');
                    $systemOut->appendChild($dom->createTextNode(
                        "File: {$file}\nCheck: {$check}\nLevel: {$level}\nType: {$type}"
                    ));
                    $testcase->appendChild($systemOut);
                }

                $testsuite->appendChild($testcase);
            }

            $testsuites->appendChild($testsuite);
        }

        return $dom->saveXML();
    }

    /**
     * Sanitize file path for XML output
     *
     * @param string $projectDir
     * @param string $filePath
     * @return string
     */
    private function sanitizeFilePath(string $projectDir, string $filePath): string
    {
        // Remove project directory prefix if present
        if (str_starts_with($filePath, $projectDir)) {
            $filePath = ltrim(substr($filePath, strlen($projectDir)), '/');
        }
        
        // Replace any problematic characters for XML
        return htmlspecialchars($filePath, ENT_XML1, 'UTF-8');
    }
}