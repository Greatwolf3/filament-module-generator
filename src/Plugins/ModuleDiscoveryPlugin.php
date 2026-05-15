<?php

namespace Greatwolf\FilamentModuleGenerator\Plugins;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Nwidart\Modules\Facades\Module;

class ModuleDiscoveryPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'greatwolf-module-discovery';
    }

    public function register(Panel $panel): void
    {
        foreach (Module::allEnabled() as $module) {
            $panel->discoverClusters(
                in: $module->getPath() . '/Filament/Clusters',
                for: 'Modules\\' . $module->getName() . '\\Filament\\Clusters',
            );

            $panel->discoverResources(
                in: $module->getPath() . '/Filament/Resources',
                for: 'Modules\\' . $module->getName() . '\\Filament\\Resources',
            );
        }
    }

    public function boot(Panel $panel): void
    {
    }
}
