<?php

namespace Greatwolf\FilamentModuleGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleResource extends Command
{
    protected $signature = 'module:filament-resource {name} {module} {--panel= : Nome o classe del Filament PanelProvider da aggiornare} {--language= : Lingue da generare separate da virgola, es. it,en,fr}';
    protected $description = 'Genera risorsa e pagine Filament 5 per un modulo nwidart';

    public function handle()
    {
        $name = Str::studly($this->argument('name'));
        $module = Str::studly($this->argument('module'));

        $this->warn("ðŸ› ï¸ Generazione Risorsa e Pagine per Filament 5...");

        // 1. Verifica che il modulo esista, altrimenti crealo come cluster
        if (!$this->moduleExists($module)) {
            $this->warn("âš ï¸ Il modulo '{$module}' non esiste. Creazione in corso...");

            try {
                $exitCode = Artisan::call('module:make', [
                    'name' => [$module],
                ]);

                // Fix namespace in generated module files to use App\Providers
                // $this->fixModuleProviderNamespaces($module);

                if ($exitCode !== 0) {
                    $this->error("âŒ Errore durante la creazione del modulo '{$module}'.");
                    return 1;
                }

                $this->info("âœ… Modulo '{$module}' creato come cluster.");
            } catch (\Exception $e) {
                $this->error("âŒ Errore durante la creazione del modulo: " . $e->getMessage());
                return 1;
            }
        }

        $this->ensureModuleAutoloadIsConfigured($module);
        $this->ensureSelectedPanelDiscoversModules();
        $this->createClusterIfNotExists($module);
        $this->createLanguageFilesIfRequested($module, $name);

        // 2. Crea il modello se non esiste
        $this->createModelIfNotExists($name, $module);

        // 3. Crea la migration se non esiste
        $this->createMigrationIfNotExists($name, $module);

        // 4. Esecuzione comando nativo (genera in app/Filament)
        $this->info("ðŸ“ Creazione risorsa Filament...");

        try {
            $this->info("ðŸ”„ Esecuzione: make:filament-resource {$name} --model-namespace=Modules\\{$module}\\Models --force");

            $exitCode = Artisan::call('make:filament-resource', [
                'model' => $name,
                '--model-namespace' => "Modules\\{$module}\\Models",
                '--resource-namespace' => "App\\Filament\\Resources",
                '--force' => true,
                '--no-interaction' => true,
            ]);

            $output = Artisan::output();
            if (!empty($output)) {
                $this->line($output);
            }

            if ($exitCode !== 0) {
                $this->error("âŒ Errore durante la creazione della risorsa Filament. Exit code: {$exitCode}");
                return 1;
            }

            $this->info("âœ… Comando make:filament-resource completato.");

        } catch (\Exception $e) {
            $this->error("âŒ Eccezione durante la creazione della risorsa: " . $e->getMessage());
            return 1;
        }

        // 4. Percorsi
        $sourceBase = app_path("Filament/Resources");
        $targetBase = base_path("Modules/{$module}/Filament/Resources");

        if (!File::isDirectory($targetBase)) {
            File::makeDirectory($targetBase, 0755, true);
        }

        // 5. Processo la Risorsa Principale
        $resourceFile = "{$name}Resource.php";
        $pluralName = Str::plural($name);

        // Controlla sia il nome singolare che plurale per la risorsa
        $resourcePath = null;
        if (File::exists("{$sourceBase}/{$resourceFile}")) {
            $resourcePath = "{$sourceBase}/{$resourceFile}";
        } elseif (File::exists("{$sourceBase}/{$pluralName}/{$resourceFile}")) {
            $resourcePath = "{$sourceBase}/{$pluralName}/{$resourceFile}";
        }

        if ($resourcePath) {
            $this->generateValidFilament5Resource($resourcePath, "{$targetBase}/{$resourceFile}", $module, $name);
            $this->info("âœ… Risorsa {$name}Resource spostata nel modulo {$module}.");
        } else {
            $this->warn("âš ï¸ File di risorsa non trovato: {$resourceFile}");
        }

        // 6. Processo la cartella delle Pagine (List, Create, Edit)
        $pagesDir = null;
        if (File::isDirectory("{$sourceBase}/{$name}Resource/Pages")) {
            $pagesDir = "{$sourceBase}/{$name}Resource/Pages";
        } elseif (File::isDirectory("{$sourceBase}/{$pluralName}/Pages")) {
            $pagesDir = "{$sourceBase}/{$pluralName}/Pages";
        }

        if ($pagesDir) {
            $targetPagesDir = "{$targetBase}/{$name}Resource/Pages";
            if (!File::isDirectory($targetPagesDir)) {
                File::makeDirectory($targetPagesDir, 0755, true);
            }

            foreach (File::files($pagesDir) as $pageFile) {
                $pageFileName = $pageFile->getFilename();
                $this->generateValidFilament5Page($pageFile->getPathname(), "{$targetPagesDir}/{$pageFileName}", $module, $name);
            }

            // Pulisci le cartelle temporanee create da make:filament-resource
            if (File::isDirectory("{$sourceBase}/{$name}Resource")) {
                File::deleteDirectory("{$sourceBase}/{$name}Resource");
            }
            if (File::isDirectory("{$sourceBase}/{$pluralName}")) {
                File::deleteDirectory("{$sourceBase}/{$pluralName}");
            }
            $this->info("âœ… Pagine della risorsa spostate e aggiornate.");
        } else {
            $this->warn("âš ï¸ Cartella pagine non trovata.");
        }

        Artisan::call('optimize:clear');
        $this->info("ðŸš€ Operazione completata! Modulo {$module} pronto con la risorsa {$name}Resource.");

        return 0;
    }

    protected function moduleExists($module)
    {
        return File::isDirectory(base_path("Modules/{$module}"));
    }

    protected function createModelIfNotExists($name, $module)
    {
        $modelPath = base_path("Modules/{$module}/app/Models/{$name}.php");

        if (File::exists($modelPath)) {
            $this->info("â„¹ï¸ Modello {$name} giÃ  esistente.");
            return;
        }

        $modelDir = dirname($modelPath);
        if (!File::isDirectory($modelDir)) {
            File::makeDirectory($modelDir, 0755, true);
        }

        $modelContent = $this->getModelTemplate($name, $module);
        File::put($modelPath, $modelContent);
        $this->info("âœ… Modello {$name} creato con successo.");
    }

    protected function createMigrationIfNotExists($name, $module)
    {
        $tableName = Str::plural(Str::snake($name));
        $migrationName = "create_{$tableName}_table";
        $timestamp = date('Y_m_d_His');
        $migrationFileName = "{$timestamp}_{$migrationName}.php";

        $modulePath = base_path("Modules/{$module}");
        $migrationsDir = "{$modulePath}/database/migrations";

        // Verifica se la migration esiste giÃ 
        if (File::exists($migrationsDir) && count(File::glob("{$migrationsDir}/*_{$migrationName}.php")) > 0) {
            $this->info("â„¹ï¸ Migration {$migrationName} giÃ  esistente.");
            return;
        }

        // Verifica se la tabella esiste giÃ  nel database
        if (\Schema::hasTable($tableName)) {
            $this->info("â„¹ï¸ Tabella {$tableName} giÃ  esistente nel database. Migration non creata.");
            return;
        }

        // Assicura che la directory migrations esista
        if (!File::isDirectory($migrationsDir)) {
            File::makeDirectory($migrationsDir, 0755, true);
        }

        $migrationPath = "{$migrationsDir}/{$migrationFileName}";
        $migrationContent = $this->getMigrationTemplate($tableName);

        File::put($migrationPath, $migrationContent);
        $this->info("âœ… Migration {$migrationName} creata con successo.");

        // Esegui automaticamente la migration
        try {
            Artisan::call('migrate', [
                '--path' => "Modules/{$module}/database/migrations",
                '--force' => true,
            ]);
            $this->info("âœ… Migration eseguita con successo.");
        } catch (\Exception $e) {
            $this->warn("âš ï¸ Impossibile eseguire automaticamente la migration: " . $e->getMessage());
            $this->info("â„¹ï¸ Esegui manualmente: php artisan migrate --path=Modules/{$module}/database/migrations");
        }
    }

    protected function ensureSelectedPanelDiscoversModules(): void
    {
        $panelPath = $this->resolvePanelProviderPath();

        if (!$panelPath) {
            $this->warn('Nessun PanelProvider trovato da aggiornare. Usa --panel=NomePanelProvider.');
            return;
        }

        $content = File::get($panelPath);

        $content = preg_replace(
            '/^use\s+Greatwolf\\\\FilamentModuleGenerator\\\\Plugins\\\\ModuleDiscoveryPlugin;\R/m',
            '',
            $content
        );

        if (!str_contains($content, 'Greatwolf\\FilamentModuleGenerator\\Plugins\\ModuleDiscoveryPlugin::make()')) {
            $content = preg_replace(
                '/(->colors\(\[[\s\S]*?\]\))/',
                '$1' . PHP_EOL . '            ->when(' . PHP_EOL . '                class_exists(\\Greatwolf\\FilamentModuleGenerator\\Plugins\\ModuleDiscoveryPlugin::class),' . PHP_EOL . '                fn (Panel $panel): Panel => $panel->plugin(\\Greatwolf\\FilamentModuleGenerator\\Plugins\\ModuleDiscoveryPlugin::make()),' . PHP_EOL . '            )',
                $content,
                1
            );
        }

        File::put($panelPath, $content);
        $this->info('PanelProvider aggiornato con ModuleDiscoveryPlugin: ' . $panelPath);
    }
    protected function resolvePanelProviderPath(): ?string
    {
        $panel = $this->option('panel');
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
    protected function ensureModuleAutoloadIsConfigured($module): void
    {
        $composerPath = base_path("Modules/{$module}/composer.json");

        if (!File::exists($composerPath)) {
            return;
        }

        $composer = json_decode(File::get($composerPath), true);

        if (!is_array($composer)) {
            return;
        }

        $composer['autoload'] ??= [];
        $composer['autoload']['psr-4'] ??= [];
        $composer['autoload']['psr-4']["Modules\\{$module}\\"] = 'app/';
        $composer['autoload']['psr-4']["Modules\\{$module}\\Filament\\"] = 'Filament/';

        File::put(
            $composerPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );

        $this->refreshComposerAutoload();
    }

    protected function refreshComposerAutoload(): void
    {
        try {
            $composer = base_path('composer.phar');

            if (File::exists($composer)) {
                exec(PHP_BINARY . ' ' . escapeshellarg($composer) . ' dump-autoload --no-scripts');
                return;
            }

            $laragonComposer = 'C:/laragonzo6/bin/composer/composer.phar';

            if (File::exists($laragonComposer)) {
                exec(PHP_BINARY . ' ' . escapeshellarg($laragonComposer) . ' dump-autoload --no-scripts');
                return;
            }

            exec('composer dump-autoload --no-scripts');
        } catch (\Throwable $exception) {
            $this->warn('?? Impossibile aggiornare automaticamente Composer autoload: ' . $exception->getMessage());
        }
    }
    protected function createLanguageFilesIfRequested(string $module, string $name): void
    {
        $languages = $this->parseLanguagesOption();

        if ($languages === []) {
            return;
        }

        $resourceKey = Str::snake(Str::pluralStudly($name));

        foreach ($languages as $language) {
            $languageDir = base_path("Modules/{$module}/lang/{$language}");

            if (!File::isDirectory($languageDir)) {
                File::makeDirectory($languageDir, 0755, true);
            }

            $languagePath = "{$languageDir}/{$resourceKey}.php";

            if (File::exists($languagePath)) {
                $this->info("File lingua {$language} già esistente: {$languagePath}");
                continue;
            }

            File::put($languagePath, $this->getLanguageTemplate($name));
            $this->info("File lingua {$language} creato: {$languagePath}");
        }
    }

    protected function parseLanguagesOption(): array
    {
        $languages = $this->option('language');

        if (!$languages) {
            return [];
        }

        return collect(explode(',', $languages))
            ->map(fn (string $language) => trim($language))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function getLanguageTemplate(string $name): string
    {
        $label = Str::headline($name);
        $pluralLabel = Str::headline(Str::plural($name));

        return "<?php

return [
    'label' => '{$label}',
    'plural_label' => '{$pluralLabel}',
    'navigation_label' => '{$pluralLabel}',
    'fields' => [
        'name' => 'Name',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],
];
";
    }
    protected function createClusterIfNotExists($module): void
    {
        $clusterPath = base_path("Modules/{$module}/Filament/Clusters/{$module}Cluster.php");

        if (File::exists($clusterPath)) {
            return;
        }

        $clusterDir = dirname($clusterPath);
        if (!File::isDirectory($clusterDir)) {
            File::makeDirectory($clusterDir, 0755, true);
        }

        File::put($clusterPath, $this->getClusterTemplate($module));
    }

    protected function getClusterTemplate($module): string
    {
        $slug = Str::slug($module);

        return "<?php

namespace Modules\\{$module}\\Filament\\Clusters;

use BackedEnum;
use Filament\\Clusters\\Cluster;

class {$module}Cluster extends Cluster
{

    protected static bool \$shouldRegisterNavigation = false;

    protected static string|BackedEnum|null \$navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string \$navigationLabel = '{$module}';

    protected static ?string \$slug = '{$slug}';
}
";
    }
    protected function generateValidFilament5Resource($source, $dest, $module, $name)
    {
        $moduleSlug = Str::slug($module);
        $resourceSlug = Str::plural(Str::kebab($name));

        $content = "<?php

namespace Modules\\{$module}\\Filament\\Resources;

use Modules\\{$module}\\Models\\{$name};
use BackedEnum;
use Filament\\Forms;
use Filament\\Forms\\Form;
use UnitEnum;
use Filament\\Resources\\Resource;
use Filament\\Schemas\\Schema;
use Filament\\Tables;
use Filament\\Tables\\Table;
use Illuminate\\Database\\Eloquent\\Builder;
use Illuminate\\Database\\Eloquent\\SoftDeletingScope;
use Modules\\{$module}\\Filament\\Resources\\{$name}Resource\\Pages;

class {$name}Resource extends Resource
{
    protected static ?string \$model = {$name}::class;

    protected static string|BackedEnum|null \$navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string \$slug = '{$moduleSlug}/{$resourceSlug}';

    protected static string|UnitEnum|null \$navigationGroup = '{$module}';

    public static function form(Schema \$form): Schema
    {
        return \$form
            ->schema([
                Forms\\Components\\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table \$table): Table
    {
        return \$table
            ->columns([
                Tables\\Columns\\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\\Columns\\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\\Columns\\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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

        File::put($dest, $content);
        File::delete($source);
    }

    protected function generateValidFilament5Page($source, $dest, $module, $name)
    {
        $content = File::get($source);

        $pluralName = Str::plural($name);

        $content = preg_replace(
            '/namespace App\\\\Filament\\\\Resources\\\\(?:' . preg_quote($name . 'Resource', '/') . '|' . preg_quote($pluralName, '/') . ')\\\\Pages;/',
            "namespace Modules\\{$module}\\Filament\\Resources\\{$name}Resource\\Pages;",
            $content
        );

        $content = preg_replace(
            '/use App\\\\Filament\\\\Resources\\\\(?:' . preg_quote($name . 'Resource', '/') . '|' . preg_quote($pluralName, '/') . ')\\\\' . preg_quote($name . 'Resource', '/') . ';/',
            "use Modules\\{$module}\\Filament\\Resources\\{$name}Resource;",
            $content
        );

        File::put($dest, $content);
        File::delete($source);
    }

    protected function getMigrationTemplate($tableName)
    {
        return "<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('{$tableName}')) {
            Schema::create('{$tableName}', function (Blueprint \$table) {
                \$table->id();
                \$table->string('name');
                \$table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};
";
    }

    protected function getModelTemplate($name, $module)
    {
        return "<?php

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
    }

}
