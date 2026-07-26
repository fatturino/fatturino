<?php

declare(strict_types=1);

/**
 * Formats the PHP preamble of Livewire 4 single-file components.
 *
 * Blade markup is deliberately left untouched. Pint formats PHP syntax while
 * this script adds line breaks around statement and block boundaries that Pint
 * does not enforce for already-valid compact code.
 *
 * Usage:
 *   php scripts/format-livewire-sfcs.php --check
 *   php scripts/format-livewire-sfcs.php --write
 */
const COMPONENT_DIRECTORY = __DIR__.'/../resources/views/pages';

$write = in_array('--write', $argv, true);
$check = in_array('--check', $argv, true);

if ($write === $check) {
    fwrite(STDERR, "Use exactly one of --check or --write.\n");

    exit(2);
}

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(COMPONENT_DIRECTORY));
$invalidFiles = [];

foreach ($files as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }

    $path = $file->getPathname();
    $contents = file_get_contents($path);
    $preambleEnd = strpos($contents, '?>');

    if ($preambleEnd === false) {
        continue;
    }

    $preamble = substr($contents, 0, $preambleEnd + 2);

    if (! str_contains($preamble, 'extends Component')) {
        continue;
    }

    $formatted = formatPreamble($preamble);

    if ($preamble === $formatted) {
        continue;
    }

    $relativePath = substr($path, strlen(__DIR__.'/..') + 1);

    if ($write) {
        file_put_contents($path, $formatted.substr($contents, $preambleEnd + 2));
        fwrite(STDOUT, "Formatted {$relativePath}\n");

        continue;
    }

    $invalidFiles[] = $relativePath;
}

if ($invalidFiles !== []) {
    fwrite(STDERR, "Compact PHP statements found in Livewire SFCs:\n");

    foreach ($invalidFiles as $path) {
        fwrite(STDERR, " - {$path}\n");
    }

    fwrite(STDERR, "Run: php scripts/format-livewire-sfcs.php --write\n");

    exit(1);
}

fwrite(STDOUT, "Livewire SFC PHP preambles are formatted.\n");

function formatPreamble(string $preamble): string
{
    $temporaryFile = tempnam(sys_get_temp_dir(), 'fatturino-livewire-sfc-');

    if ($temporaryFile === false) {
        throw new RuntimeException('Unable to create a temporary PHP file.');
    }

    try {
        file_put_contents($temporaryFile, $preamble);
        runPint($temporaryFile);
        file_put_contents($temporaryFile, expandStatements((string) file_get_contents($temporaryFile)));
        file_put_contents($temporaryFile, normalizeTernaryIndentation((string) file_get_contents($temporaryFile)));
        runPint($temporaryFile);

        return rtrim(restoreSfcClassDeclaration((string) file_get_contents($temporaryFile)))."\n?>";
    } finally {
        @unlink($temporaryFile);
    }
}

function runPint(string $path): void
{
    $pint = escapeshellarg(__DIR__.'/../vendor/bin/pint');
    $command = "{$pint} ".escapeshellarg($path).' --quiet 2>&1';
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        throw new RuntimeException("Pint could not format a Livewire SFC preamble:\n".implode("\n", $output));
    }
}

function restoreSfcClassDeclaration(string $source): string
{
    return (string) preg_replace(
        '/(new\s+(?:#\[[\s\S]*?\]\s*)?class\s+extends\s+Component)\s*\n\{/',
        '$1 {',
        $source,
        1,
    );
}

function normalizeTernaryIndentation(string $source): string
{
    $lines = explode("\n", $source);

    foreach ($lines as $index => $line) {
        if (! preg_match('/^\s*[?:]\s+/', $line)) {
            continue;
        }

        for ($previousIndex = $index - 1; $previousIndex >= 0; $previousIndex--) {
            if (trim($lines[$previousIndex]) === '') {
                continue;
            }

            preg_match('/^(\s*)/', $lines[$previousIndex], $matches);
            $lines[$index] = $matches[1].'    '.ltrim($line);

            break;
        }
    }

    return implode("\n", $lines);
}

function expandStatements(string $source): string
{
    $output = '';
    $parenthesisDepth = 0;
    $previousToken = null;
    $skipWhitespace = false;
    $braceStack = [];

    foreach (token_get_all($source) as $token) {
        $text = is_array($token) ? $token[1] : $token;
        $tokenId = is_array($token) ? $token[0] : null;

        if ($tokenId === T_WHITESPACE && $skipWhitespace) {
            continue;
        }

        $skipWhitespace = false;

        if ($text === '(') {
            $parenthesisDepth++;
        }

        if ($text === ')') {
            $parenthesisDepth--;
        }

        $isDynamicProperty = $text === '{' && $previousToken === T_OBJECT_OPERATOR;

        if ($text === '{') {
            $braceStack[] = $isDynamicProperty;

            if ($isDynamicProperty) {
                $output .= $text;
                $previousToken = $tokenId;

                continue;
            }

            $output = rtrim($output)."\n{\n";
            $skipWhitespace = true;
            $previousToken = $tokenId;

            continue;
        }

        if ($text === '}') {
            $isDynamicProperty = array_pop($braceStack) === true;

            if ($isDynamicProperty) {
                $output .= $text;
                $previousToken = $tokenId;

                continue;
            }

            $output = rtrim($output)."\n}\n";
            $skipWhitespace = true;
            $previousToken = $tokenId;

            continue;
        }

        if ($text === ';' && $parenthesisDepth === 0) {
            $output = rtrim($output).";\n";
            $skipWhitespace = true;
            $previousToken = $tokenId;

            continue;
        }

        $output .= $text;

        if ($tokenId !== T_WHITESPACE) {
            $previousToken = $tokenId;
        }
    }

    return rtrim($output)."\n";
}
