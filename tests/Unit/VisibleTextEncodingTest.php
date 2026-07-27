<?php

namespace Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class VisibleTextEncodingTest extends TestCase
{
    public function test_php_files_do_not_contain_question_marks_replacing_letters(): void
    {
        $root = dirname(__DIR__, 2);
        $directories = ['app', 'config', 'database', 'resources/views', 'routes'];
        $pattern = "/\p{L}\?\p{L}|\p{L}\?(?=[\s,;:!<\"'])|\p{L}\?\.(?=[\s<\"'])/u";
        $corruptedFiles = [];

        foreach ($directories as $directory) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                if ($contents !== false && preg_match($pattern, $contents) === 1) {
                    $corruptedFiles[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                }
            }
        }

        $this->assertSame(
            [],
            $corruptedFiles,
            "Des caractères '?' semblent remplacer des lettres accentuées dans :\n".implode("\n", $corruptedFiles),
        );
    }
}
