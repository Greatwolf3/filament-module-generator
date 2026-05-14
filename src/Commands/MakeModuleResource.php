<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;

class MakeModuleResource extends Command
{
    protected $signature = 'module:filament-resource {name} {module} {--panel=admin : Nome del Filament Panel}';
    protected $description = 'Genera risorsa Filament per un modulo nwidart/laravel-modules';

    public function handle()
    {
        $name = Str::studly($this->argument('name'));
        $module = Str::studly($this->argument('module'));
        $panel = $this->option('panel') ?? 'admin';
        $panel = Str::studly($panel);

        $this->info("🛠️ Generazione risorsa Filament per modulo {$module} (Panel: {$panel})...");

        // 1. Verifica che il modulo esista, altrimenti crealo
        if (!$this->moduleExists($module)) {
            $this->warn("⚠️ Il modulo '{$module}' non esiste. Creazione in corso...");

            try {
                $exitCode = Artisan::call('module:make', [
                    'name' => [$module],
                ]);

                if ($exitCode !== 0) {
                    $this->error("❌ Errore durante la creazione del modulo '{$module}'.");
                    return 1;
                }

                $this->info("✅ Modulo '{$module}' creato.");
            } catch (\Exception $e) {
                $this->error("❌ Errore durante la creazione del modulo: " . $e->getMessage());
                return 1;
            }
        }

        // 2. Crea il modello se non esiste
        $this->createModelIfNotExists($name, $module);

        // 3. Crea la migration se non esiste
        $this->createMigrationIfNotExists($name, $module);

        // 4. Genera la risorsa Filament direttamente nel modulo
        $this->generateFilamentResource($name, $module, $panel);

        // 5. Registra la risorsa nel PanelProvider
        $this->registerResourceInPanel($panel, $module, $name);

        // 6. Pulisci cache
        Artisan::call('optimize:clear');
        $this->info("✅ Cache pulita.");

        $this->info("🚀 Operazione completata! Risorsa {$name}Resource creata nel modulo {$module} per il panel {$panel}.");

        return 0;
    }

    protected function moduleExists($module): bool
    {
        return Module::has($module);
    }

    protected function createModelIfNotExists($name, $module): void
    {
        $modelPath = base_path("Modules/{$module}/app/Models/{$name}.php");

        if (File::exists($modelPath)) {
            $this->info("✅ Modello {$name} già esistente.");
            return;
        }

        $content = "<?php

namespace Modules\\{$module}\\Models;

use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;
use Illuminate\\Database\\Eloquent\\Model;

class {$name} extends Model
{
    use HasFactory;

    protected \$fillable = [
        'name',
    ];
}";

        File::ensureDirectoryExists(dirname($modelPath));
        File::put($modelPath, $content);
        $this->info("✅ Modello {$name} creato.");
    }

    protected function createMigrationIfNotExists($name, $module): void
    {
        $tableName = Str::plural(Str::snake($name));

        try {
            $exitCode = Artisan::call('make:migration', [
                'name' => "create_{$tableName}_table",
                '--path' => "Modules/{$module}/database/migrations",
            ]);

            if ($exitCode === 0) {
                $this->info("✅ Migration create_{$tableName}_table creata.");

                // Esegui la migration
                try {
                    Artisan::call('migrate', [
                        '--path' => "Modules/{$module}/database/migrations",
                    ]);
                    $this->info("✅ Migration eseguita con successo.");
                } catch (\Exception $e) {
                    $this->warn("⚠️ Impossibile eseguire automaticamente la migration: " . $e->getMessage());
                    $this->info("ℹ️ Esegui manualmente: php artisan migrate --path=Modules/{$module}/database/migrations");
                }
            }
        } catch (\Exception $e) {
            $this->warn("⚠️ Impossibile creare la migration: " . $e->getMessage());
        }
    }

    protected function generateFilamentResource($name, $module, $panel): void
    {
        $resourceDir = base_path("Modules/{$module}/Filament/Resources");
        $pluralName = Str::plural($name);
        $resourceSubdir = "{$resourceDir}/{$pluralName}";

        File::ensureDirectoryExists($resourceSubdir);

        // Genera il file della risorsa
        $resourceContent = $this->getResourceTemplate($name, $module, $pluralName);
        File::put("{$resourceSubdir}/{$name}Resource.php", $resourceContent);

        // Genera le pagine
        $this->generateResourcePages($name, $module, $pluralName);

        $this->info("✅ Risorsa {$name}Resource generata in {$resourceSubdir}");
    }

    protected function getResourceTemplate($name, $module, $pluralName): string
    {
        $moduleSlug = Str::slug($module);
        $resourceSlug = Str::plural(Str::kebab($name));

        return "<?php

namespace Modules\\{$module}\\Filament\\Resources\\{$pluralName};

use Modules\\{$module}\\Models\\{$name};
use BackedEnum;
use UnitEnum;
use Filament\\Resources\\Resource;
use Filament\\Schemas\\Schema;
use Filament\\Tables\\Table;
use Filament\\Tables\\Columns\\TextColumn;
use Modules\\{$module}\\Filament\\Resources\\{$pluralName}\\Pages;

class {$name}Resource extends Resource
{
    protected static ?string \$model = {$name}::class;

    protected static string|BackedEnum|null \$navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|UnitEnum|null \$navigationGroup = '{$module}';

    protected static ?string \$navigationLabel = '{$name}';

    protected static ?string \$modelLabel = '{$name}';

    public static function form(Schema \$form): Schema
    {
        return \$form
            ->schema([
                //
            ]);
    }

    public static function table(Table \$table): Table
    {
        return \$table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\\List" . Str::plural($name) . "::route('/'),
            'create' => Pages\\Create{$name}::route('/create'),
            'edit' => Pages\\Edit{$name}::route('/{record}/edit'),
        ];
    }
}";
    }

    protected function generateResourcePages($name, $module, $pluralName): void
    {
        $pagesDir = base_path("Modules/{$module}/Filament/Resources/{$pluralName}/Pages");
        File::ensureDirectoryExists($pagesDir);

        // List page
        $listContent = "<?php

namespace Modules\\{$module}\\Filament\\Resources\\{$pluralName}\\Pages;

use Modules\\{$module}\\Filament\\Resources\\{$pluralName}\\{$name}Resource;
use Filament\\Resources\\Pages\\ListRecords;

class List" . Str::plural($name) . " extends ListRecords
{
    protected static string \$resource = {$name}Resource::class;
}";
        File::put("{$pagesDir}/List" . Str::plural($name) . ".php", $listContent);

        // Create page
        $createContent = "<?php

namespace Modules\\{$module}\\Filament\\Resources\\{$pluralName}\\Pages;

use Modules\\{$module}\\Filament\\Resources\\{$pluralName}\\{$name}Resource;
use Filament\\Resources\\Pages\\CreateRecord;

class Create{$name} extends CreateRecord
{
    protected static string \$resource = {$name}Resource::class;
}";
        File::put("{$pagesDir}/Create{$name}.php", $createContent);

        // Edit page
        $editContent = "<?php

namespace Modules\\{$module}\\Filament\\Resources\\{$pluralName}\\Pages;

use Modules\\{$module}\\Filament\\Resources\\{$pluralName}\\{$name}Resource;
use Filament\\Resources\\Pages\\EditRecord;

class Edit{$name} extends EditRecord
{
    protected static string \$resource = {$name}Resource::class;
}";
        File::put("{$pagesDir}/Edit{$name}.php", $editContent);

        $this->info("✅ Pagine generate in {$pagesDir}");
    }

    protected function registerResourceInPanel($panel, $module, $name): void
    {
        $panelProviderPath = $this->resolvePanelProviderPath($panel);

        if (!$panelProviderPath) {
            $this->warn("⚠️ Nessun PanelProvider trovato per registrare le risorse.");
            return;
        }

        $content = File::get($panelProviderPath);

        // Aggiunge la risorsa al discoverResources se non è già presente
        if (!str_contains($content, "Modules/{$module}/Filament/Resources")) {
            $content = str_replace(
                "->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')",
                "->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')\n            ->discoverResources(in: base_path('Modules/{$module}/Filament/Resources'), for: 'Modules\\{$module}\\Filament\\Resources')",
                $content
            );
        }

        File::put($panelProviderPath, $content);
        $this->info("✅ Risorse del modulo {$module} registrate nel PanelProvider: " . basename($panelProviderPath));
    }

    protected function resolvePanelProviderPath($panel): ?string
    {
        $providers = File::glob(app_path('Providers/Filament/*PanelProvider.php')) ?: [];

        if ($panel) {
            $panel = Str::of($panel)->replace('\\', '/')->afterLast('/')->before('.php')->toString();

            foreach ($providers as $provider) {
                if (basename($provider, '.php') === $panel || Str::before(basename($provider, '.php'), 'PanelProvider') === $panel) {
                    return $provider;
                }
            }

            $candidate = app_path("Providers/Filament/{$panel}PanelProvider.php");
            if (File::exists($candidate)) {
                return $candidate;
            }

            $candidate = app_path("Providers/Filament/{$panel}.php");
            if (File::exists($candidate)) {
                return $candidate;
            }

            return null;
        }

        if (count($providers) === 1) {
            return $providers[0];
        }

        if (count($providers) > 1) {
            $choice = $this->choice('Quale Filament PanelProvider vuoi aggiornare?', array_map(fn ($provider) => basename($provider, '.php'), $providers));

            foreach ($providers as $provider) {
                if (basename($provider, '.php') === $choice) {
                    return $provider;
                }
            }
        }

        return null;
    }
}
