<?php
declare(strict_types=1);

namespace Nutronyx\SDK;

use Nutronyx\Database;
use Nutronyx\SDK\Contracts\AdminModuleInterface;

class ModuleLoader
{
    private static ?ModuleLoader $instance = null;
    /** @var array<string, AdminModuleInterface> */
    private array $loadedModules = [];
    /** @var array<string, array> */
    private array $moduleManifests = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function bootstrap(): void
    {
        $modulesDir = __DIR__ . '/../../modules';
        if (!is_dir($modulesDir)) {
            return;
        }

        $db = Database::getConnection();
        $stmt = $db->query("SELECT id, status FROM installed_modules");
        $activeMap = [];
        while ($row = $stmt->fetch()) {
            $activeMap[$row['id']] = ($row['status'] === 'ACTIVE');
        }

        $extensionRegistry = ExtensionRegistry::getInstance();
        $eventBus = EventBus::getInstance();

        $entries = scandir($modulesDir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $modulePath = $modulesDir . '/' . $entry;
            $manifestFile = $modulePath . '/module.json';

            if (is_dir($modulePath) && file_exists($manifestFile)) {
                $manifestData = json_decode((string)file_get_contents($manifestFile), true);
                if (!$manifestData || empty($manifestData['identifier']) || empty($manifestData['entrypoint'])) {
                    continue;
                }

                $identifier = $manifestData['identifier'];
                $this->moduleManifests[$identifier] = $manifestData;

                // Check if active in DB (default to active if in manifest)
                $isActive = $activeMap[$identifier] ?? true;

                if ($isActive) {
                    $entryClassFile = $modulePath . '/' . basename(str_replace('\\', '/', $manifestData['entrypoint'])) . '.php';
                    if (file_exists($entryClassFile)) {
                        require_once $entryClassFile;
                        $className = $manifestData['entrypoint'];
                        if (class_exists($className)) {
                            $moduleInstance = new $className();
                            if ($moduleInstance instanceof AdminModuleInterface) {
                                $moduleInstance->boot($extensionRegistry, $eventBus);
                                $this->loadedModules[$identifier] = $moduleInstance;
                            }
                        }
                    }
                }
            }
        }
    }

    public function getLoadedModules(): array
    {
        return $this->loadedModules;
    }

    public function getAllManifests(): array
    {
        return $this->moduleManifests;
    }
}
