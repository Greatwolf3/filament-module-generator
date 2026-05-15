<?php

namespace Greatwolf\FilamentModuleGenerator\Composer;

use Composer\Script\Event;

class CleanupPanelProviders
{
    public static function prePackageUninstall(Event $event): void
    {
        $operation = $event->getOperation();
        $package = method_exists($operation, 'getPackage') ? $operation->getPackage() : null;

        if (!$package || $package->getName() !== 'greatwolf3/filament-module-generator') {
            return;
        }

        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $basePath = dirname($vendorDir);
        $providersPath = $basePath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Providers' . DIRECTORY_SEPARATOR . 'Filament';

        if (!is_dir($providersPath)) {
            return;
        }

        foreach (glob($providersPath . DIRECTORY_SEPARATOR . '*PanelProvider.php') ?: [] as $providerPath) {
            $content = file_get_contents($providerPath);

            if ($content === false) {
                continue;
            }

            $updated = str_replace(
                [
                    "use Greatwolf\\FilamentModuleGenerator\\Plugins\\ModuleDiscoveryPlugin;\r\n",
                    "use Greatwolf\\FilamentModuleGenerator\\Plugins\\ModuleDiscoveryPlugin;\n",
                    "            ->plugin(ModuleDiscoveryPlugin::make())\r\n",
                    "            ->plugin(ModuleDiscoveryPlugin::make())\n",
                ],
                '',
                $content
            );

            if ($updated !== $content) {
                file_put_contents($providerPath, $updated);
            }
        }
    }
}
