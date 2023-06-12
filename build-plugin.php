<?php

declare(strict_types=1);

if (ini_get("phar.readonly") === "1") {
    echo "[!] Set phar.readonly to 0 with -dphar.readonly=0" . PHP_EOL;
    exit(1);
}

/**
 * The following lines originated from https://github.com/pmmp/PocketMine-MP/blob/stable/build/server-phar.php
 */
$dir = rtrim(str_replace("/", DIRECTORY_SEPARATOR, __DIR__ . DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$regex = sprintf('/^%s(%s).*/i',
    //String must start with this path...
    preg_quote($dir, '/'),
    //... and must be followed by one of these relative paths, if any were specified. If none, this will produce a null capturing group which will allow anything.
    implode('|', array_map(static function(string $string) : string { return preg_quote($string, '/'); }, ["src", "resources", "plugin.yml", ".poggit.yml"]))
);

$start = microtime(true);
$files = [];

$directory = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS | FilesystemIterator::CURRENT_AS_PATHNAME); //can't use fileinfo because of symlinks
$iterator = new RecursiveIteratorIterator($directory);
$regexIterator = new \RegexIterator($iterator, $regex);
foreach($regexIterator as $file) {
    $files[str_replace($dir, "", $file)] = file_get_contents($file);
}

exec("composer install --no-progress --no-dev --prefer-dist --optimize-autoloader --ignore-platform-reqs");

$vendorPath = __DIR__ . DIRECTORY_SEPARATOR . "vendor";
$composerData = json_decode(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . "composer.json"), true);

$injectableDependencies = [];

foreach ($composerData["require"] ?? [] as $dependency => $version) {
    if (isPlatformPackage($dependency)) {
        continue;
    }
    searchInjectableDependencies($dependency, $vendorPath, $injectableDependencies);
}

foreach ($injectableDependencies as $dependency => $directory) {
    injectDependency($files, $dependency, $directory);
}

$pharPath = getcwd() . DIRECTORY_SEPARATOR . basename(__DIR__) . ".phar";
if (file_exists($pharPath)) {
    Phar::unlinkArchive($pharPath);
}

$phar = new Phar($pharPath);
$phar->startBuffering();

foreach($files as $file => $contents) {
    $phar->addFromString($file, $contents);
}

$phar->compressFiles(Phar::GZ);
$phar->stopBuffering();

echo "Done in " . round(microtime(true) - $start, 3) . "s" . PHP_EOL;
exit();

function searchInjectableDependencies(string $dependency, string $vendorPath, array &$injectableDependencies) : void {
    $dependencyPath = $vendorPath . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $dependency);
    $composerFile = $dependencyPath . DIRECTORY_SEPARATOR . "composer.json";
    if (is_dir($dependencyPath) && is_file($composerFile)) {
        $composerData = json_decode(file_get_contents($composerFile), true);
        if (!isset($composerData["extra"]["virion"])) {
            return;
        }
        $virionData = $composerData["extra"]["virion"];
        if (!is_array($virionData) || !isset($virionData["spec"])) {
            return;
        }
        $specVersion = $virionData["spec"];
        if (version_compare($specVersion, "3.0", ">") || version_compare($specVersion, "3.0", "<") || !isset($virionData["namespace-root"])) {
            return;
        }
        // If the dependency is already in the list, this means another library depends on it.
        // Because of that, we want it add the end of the list, so it gets shaded after all dependencies that depend on it are added.
        if (isset($injectableDependencies[$virionData["namespace-root"]])) {
            unset($injectableDependencies[$virionData["namespace-root"]]);
        }
        $injectableDependencies[$virionData["namespace-root"]] = $dependencyPath;
        foreach($composerData["require"] ?? [] as $subdependency => $version) {
            if (isPlatformPackage($subdependency)) {
                continue;
            }
            searchInjectableDependencies($subdependency, $vendorPath, $injectableDependencies);
        }
    }
}

function injectDependency(array &$files, string $dependency, string $directory) : void {
    $prefix = "_" . bin2hex(random_bytes(10)) . "_";
    // Shading all existing files in the phar
    foreach ($files as $file => $contents) {
        $files[$file] = shadeFile($contents, $dependency, $prefix);
    }
    $src = "src" . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $dependency);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS | FilesystemIterator::CURRENT_AS_PATHNAME)
    );
    // Shading all of the dependency's files
    foreach ($iterator as $file) {
        $srcPos = strpos($file, $src);
        if ($srcPos === false) {
            continue;
        }
        $shadedFilePath = "src" . DIRECTORY_SEPARATOR . $prefix . substr($file, $srcPos + 4);
        $files[$shadedFilePath] = shadeFile(file_get_contents($file), $dependency, $prefix);
    }
}

/**
 * @link https://github.com/poggit/poggit/blob/000a86ff75481540b561a9bf6f363418012bdbdf/assets/php/virion.php#L147-L213
 */
function shadeFile(string $fileContents, string $dependency, string $prefix) : string {
    $tokens = token_get_all($fileContents);
    $tokens[] = ""; // should not be valid though
    foreach($tokens as $offset => $token) {
        if(!is_array($token) or $token[0] !== T_WHITESPACE) {
            /** @noinspection IssetArgumentExistenceInspection */
            list($id, $str, $line) = is_array($token) ? $token : [-1, $token, $line ?? 1];
            //namespace test; is a T_STRING whereas namespace test\test; is not.
            if(isset($init, $prefixToken) and $id === T_STRING){
                if($str === $dependency) { // case-sensitive!
                    $tokens[$offset][1] = $prefix . $dependency . substr($str, strlen($dependency));
                } elseif(stripos($str, $dependency) === 0) {
                    echo "\x1b[38;5;227m\n[WARNING] Not replacing FQN $str case-insensitively.\n\x1b[m";
                }
                unset($init, $prefixToken);
            } else {
                if($id === T_NAMESPACE) {
                    $init = $offset;
                    $prefixToken = $id;
                } elseif($id === T_NAME_QUALIFIED) {
                    if(($str[strlen($dependency)]??"\\") === "\\") {
                        if(strpos($str, $dependency) === 0) { // case-sensitive!
                            $tokens[$offset][1] = $prefix . $dependency . substr($str, strlen($dependency));
                        } elseif(stripos($str, $dependency) === 0) {
                            echo "\x1b[38;5;227m\n[WARNING] Not replacing FQN $str case-insensitively.\n\x1b[m";
                        }
                    }
                    unset($init, $prefixToken);
                } elseif($id === T_NAME_FULLY_QUALIFIED){
                    if(strpos($str, "\\" . $dependency . "\\") === 0) { // case-sensitive!
                        $tokens[$offset][1] = "\\" . $prefix . $dependency . substr($str, strlen($dependency)+1);
                    } elseif(stripos($str, "\\" . $dependency . "\\") === 0) {
                        echo "\x1b[38;5;227m\n[WARNING] Not replacing FQN $str case-insensitively.\n\x1b[m";
                    }
                    unset($init, $prefixToken);
                }
            }
        }
    }
    $ret = "";
    foreach($tokens as $token) {
        $ret .= is_array($token) ? $token[1] : $token;
    }
    return $ret;
}

/**
 * @link https://github.com/SOF3/pharynx/blob/a42afb5e998769ea95ae5808ec25e459b2e3a6e6/src/Args.php#L322-L324
 */
function isPlatformPackage(string $depName) : bool {
    return preg_match('#^(php|(ext|php|lib|composer)-[^/]+)$#', $depName, $_) === 1;
}