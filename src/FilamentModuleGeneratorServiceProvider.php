<?php

namespace Greatwolf\FilamentModuleGenerator;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Greatwolf\FilamentModuleGenerator\Commands\MakeModuleResource;
use Greatwolf\FilamentModuleGenerator\Commands\ModuleManager;

class FilamentModuleGeneratorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-module-generator')
            ->hasConfigFile()
            ->hasCommands([
                MakeModuleResource::class,
                ModuleManager::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Register any package services here
    }

    public function packageBooted(): void
    {
        // Register any package boot logic here
    }
}
