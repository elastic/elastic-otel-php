<?php

// Path to the composer.lock file
$composerLockFile = 'composer.lock';


$separatorLen = 80;

// Check if the file exists
if (!file_exists($composerLockFile)) {
    die('The composer.lock file does not exist.');
}

// Read and decode the composer.lock file
$composerData = readAndDecodeComposerLock($composerLockFile);

// Check if the 'packages' section exists
if (!isset($composerData['packages']) || !is_array($composerData['packages'])) {
    die('The "packages" section is missing in the composer.lock file.');
}

$packages = $composerData['packages'];

foreach ($packages as $package) {
    $packageName = $package['name'] ?? 'Unknown package name';
    $packageVersion = $package['version'] ?? 'Unknown version';
    $authors = getAuthors($package);
    $licenses = $package['license'] ?? ['No license'];
    $url = $package['homepage'] ?? ($package['support']['source'] ?? 'No URL');
    $sourceUrl = $package['support']['source'] ?? $url;

    // Generate URLs for NOTICE.txt and LICENSE using new method and fallback to old method
    $noticeContent = fetchFileContent(generateRawFileUrl($package), 'NOTICE') ?: fetchFileContent(filterUrl($sourceUrl), 'NOTICE');
    $licenseContent = fetchFileContent(generateRawFileUrl($package), 'LICENSE') ?: fetchFileContent(filterUrl($sourceUrl), 'LICENSE');


    // Display package information
    echo "Package name: $packageName\n";
    echo "Version: $packageVersion\n";
    echo "Authors: " . implode(', ', $authors) . "\n";
    echo "Licenses: " . implode(', ', $licenses) . "\n";
    echo "URL: $url\n";
    echo "\n";
    if ($noticeContent) {
        echo "NOTICE content:\n$noticeContent\n";
    } else {
        echo "No NOTICE file found\n";
    }

    if ($licenseContent) {
        echo "LICENSE content:\n$licenseContent\n";
    } else {
        echo "No LICENSE file found\n";
    }
    echo str_repeat('-', $separatorLen)."\n\n";
}


function readAndDecodeComposerLock($filePath) {
    $content = file_get_contents($filePath);
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die('JSON decoding error: ' . json_last_error_msg());
    }
    return $data;
}

function getAuthors($package) {
    $authors = [];
    if (isset($package['authors']) && is_array($package['authors'])) {
        foreach ($package['authors'] as $author) {
            $name = $author['name'] ?? 'Unknown name';
            if (isset($author['email'])) {
                $email = $author['email'];
                $authors[] = "$name <$email>";
            } else {
                $authors[] = "$name";
            }
        }
    }
    return $authors;
}

function fetchFileContent($fileUrl, $fileName) {
    $fileUrl = $fileUrl . '/' . $fileName;

    if ($fileUrl !== 'No URL') {
        $headers = @get_headers($fileUrl);
        if ($headers && strpos($headers[0], '200')) {
            return file_get_contents($fileUrl);
        }
    }
    return '';
}

function filterUrl($url) {
    if (strpos($url, 'https://github.com/') === 0) {
        if (strpos($url, 'https://github.com/') === 0) {
            $url = str_replace('https://github.com/', 'https://raw.githubusercontent.com/', $url);
            $url .= "/main";
        } else {
            $url = str_replace('/tree/', '/', $url);
        }
    }
    return $url;
}

function generateRawFileUrl($package) {
    if (isset($package['source']['type']) && $package['source']['type'] === 'git' && isset($package['source']['url']) && isset($package['source']['reference'])) {
        $repoUrl = $package['source']['url'];
        $reference = $package['source']['reference'];
        if (strpos($repoUrl, 'https://github.com/') === 0) {
            $repoUrl = str_replace('https://github.com/', 'https://raw.githubusercontent.com/', $repoUrl);
            $repoUrl = substr($repoUrl, 0, -4);
            return $repoUrl . '/' . $reference;
        }
    }
    return 'No URL';
}
?>
