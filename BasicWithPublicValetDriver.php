<?php

namespace Valet\Drivers;

class BasicWithPublicValetDriver extends ValetDriver
{
    /**
     * Determine if the driver serves the request.
     */
    public function serves(string $sitePath, string $siteName, string $uri): bool
    {
        return is_dir($sitePath . '/public/');
    }

    /**
     * Determine if the incoming request is for a static file.
     */
    public function isStaticFile(string $sitePath, string $siteName, string $uri)/*: string|false */
    {
        $config = $this->loadConfig($sitePath);
        $configDocRoot = rtrim($config['document_root'], '/');
        $staticFilePath = $sitePath . $configDocRoot . $uri;

        if ($this->isActualFile($staticFilePath)) {
            return $staticFilePath;
        }

        return false;
    }

    /**
     * Get the fully resolved path to the application's front controller.
     */
    public function frontControllerPath(string $sitePath, string $siteName, string $uri): ?string
    {
        $_SERVER['PHP_SELF'] = $uri;
        $_SERVER['SERVER_ADDR'] = '127.0.0.1';
        $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];

        $config = $this->loadConfig($sitePath);
        $configDocRoot = rtrim($config['document_root'], '/');
        $docRoot = $sitePath . $configDocRoot;
        $uri = rtrim($uri, '/');

        $candidates = [
            $docRoot . $uri,
            $docRoot . $uri . '/index.php',
            $docRoot . '/index.php',
            $docRoot . '/index.html',
        ];

        foreach ($candidates as $candidate) {
            if ($this->isActualFile($candidate)) {
                $_SERVER['SCRIPT_FILENAME'] = $candidate;
                $_SERVER['SCRIPT_NAME'] = str_replace($sitePath, '', $candidate);
                $_SERVER['DOCUMENT_ROOT'] = $docRoot;

                return $candidate;
            }
        }

        return null;
    }

    /**
     * Load config.json from site root or create one with default values.
     */
    protected function loadConfig(string $sitePath): array
    {
        $configPath = $sitePath . '/config.json';
        $defaultConfig = [
            'document_root' => '/public'
        ];

        if (file_exists($configPath)) {
            $configContent = file_get_contents($configPath);
            $config = json_decode($configContent, true);

            if (is_array($config) && isset($config['document_root'])) {
                return $config;
            }
        }

        // If config doesn't exist or is invalid, write default
        file_put_contents($configPath, json_encode($defaultConfig, JSON_PRETTY_PRINT));
        return $defaultConfig;
    }
}
