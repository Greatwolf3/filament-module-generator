<?php

namespace Greatwolf\FilamentModuleGenerator;

use Illuminate\Support\Facades\File;
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
        if ($this->app->runningInConsole()) {
            $this->ensureRootComposerKeepsRequiredPackages();
        }
    }

    public function packageBooted(): void
    {
    }

    protected function ensureRootComposerKeepsRequiredPackages(): void
    {
        $composerPath = base_path('composer.json');

        if (!File::exists($composerPath)) {
            return;
        }

        $composer = json_decode(File::get($composerPath), true);

        if (!is_array($composer)) {
            return;
        }

        $composer['require'] ??= [];
        $changed = false;

        $requiredPackages = [
            'nwidart/laravel-modules' => '^13.0',
            'wikimedia/composer-merge-plugin' => '^2.1',
        ];

        foreach ($requiredPackages as $package => $version) {
            if (!array_key_exists($package, $composer['require'])) {
                $composer['require'][$package] = $version;
                $changed = true;
            }
        }

        if (!$changed) {
            return;
        }

        File::put(
            $composerPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }
}
