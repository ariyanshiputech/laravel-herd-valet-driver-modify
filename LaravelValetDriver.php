<?php

namespace Valet\Drivers;

use Symfony\Component\Process\Process;

class LaravelValetDriver extends ValetDriver
{
    /**
     * Determine if the driver serves the request.
     */
    public function serves(string $sitePath, string $siteName, string $uri): bool
    {
        $config = $this->loadConfig($sitePath);
        $configDocRoot = rtrim($config['document_root'], '/');
        $docRoot = $sitePath . $configDocRoot;

        return file_exists($sitePath.'/index.php') &&
               file_exists($sitePath.'/artisan');
    }

    /**
     * Take any steps necessary before loading the front controller for this driver.
     */
    public function beforeLoading(string $sitePath, string $siteName, string $uri): void
    {
        // Shortcut for getting the "local" hostname as the HTTP_HOST
        if (isset($_SERVER['HTTP_X_ORIGINAL_HOST'], $_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
        }

        if (\function_exists('herd_inject_cb') && class_exists('\Herd\HerdDumper\HerdInjector')) {
            \Herd\HerdDumper\HerdInjector::create($sitePath)->inject();
        }
    }

    /**
     * Determine if the incoming request is for a static file.
     */
    public function isStaticFile(string $sitePath, string $siteName, string $uri)/*: string|false */
    {
        $config = $this->loadConfig($sitePath);
        $configDocRoot = rtrim($config['document_root'], '/');
        $docRoot = $sitePath . $configDocRoot;
        if (file_exists($staticFilePath = $docRoot.$uri)
           && is_file($staticFilePath)) {
            return $staticFilePath;
        }

        $storageUri = $uri;

        if (strpos($uri, '/storage/') === 0) {
            $storageUri = substr($uri, 8);
        }

        if ($this->isActualFile($storagePath = $sitePath.'/storage/app/public'.$storageUri)) {
            return $storagePath;
        }

        return false;
    }

    /**
     * Get the fully resolved path to the application's front controller.
     */
    public function frontControllerPath(string $sitePath, string $siteName, string $uri): ?string
    {
        $config = $this->loadConfig($sitePath);
        $configDocRoot = rtrim($config['document_root'], '/');
        $docRoot = $sitePath . $configDocRoot;
        
        return $docRoot.'/index.php';
    }

    public function siteInformation(string $sitePath, string $phpBinary): array
    {
        try {
            $process = new Process([
                $phpBinary,
                'artisan',
                'about',
                '--json'
            ], $sitePath);

            $process->mustRun();

            $result = json_decode($process->getOutput(), true);
        } catch (\Exception $e) {
            $result = [];
        }

        return [
            ...$result,
        ];
    }

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
