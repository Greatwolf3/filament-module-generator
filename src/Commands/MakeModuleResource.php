<?php

namespace Greatwolf\FilamentModuleGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleResource extends Command
{
    protected $signature = 'module:filament-resource {name} {module} {--panel=admin : Nome o classe del Filament PanelProvider da aggiornare}';
    protected $description = 'Genera risorsa e pagine Filament 5 per un modulo nwidart (panel: admin, business, struttura)';

    public function handle()
    {
        $name = Str::studly($this->argument('name'));
        $module = Str::studly($this->argument('module'));
        $panel = $this->option('panel') ?? 'admin';
        $panel = Str::studly($panel);

        $this->warn("🛠️ Generazione Risorsa e Pagine per Filament 5 (Panel: {$panel})...");

        // 1. Verifica che il modulo esista, altrimenti crealo come cluster
        if (!$this->moduleExists($module)) {
            $this->warn("⚠️ Il modulo '{$module}' non esiste. Creazione in corso...");

            try {
                $exitCode = Artisan::call('module:make', [
                    'name' => [$module],
                ]);

                // Fix namespace in generated module files to use App\Providers
                // $this->fixModuleProviderNamespaces($module);

                if ($exitCode !== 0) {
                    $this->error("❌ Errore durante la creazione del modulo '{$module}'.");
                    return 1;
                }

                $this->info("✅ Modulo '{$module}' creato come cluster.");
            } catch (\Exception $e) {
                $this->error("❌ Errore durante la creazione del modulo: " . $e->getMessage());
                return 1;
            }
        }

        $this->ensureModuleAutoloadIsConfigured($module);
        $this->ensureSelectedPanelDiscoversModules($panel);

        // 2. Crea il modello se non esiste
        $this->createModelIfNotExists($name, $module);

        // 3. Crea la migration se non esiste
        $this->createMigrationIfNotExists($name, $module);

        // 4. Esecuzione comando nativo (genera in app/Filament)
        $this->info("📝 Creazione risorsa Filament...");

        try {
            $resourceNamespace = $panel === 'admin'
                ? "App\\Filament\\Admin\\Resources"
                : "App\\Filament\\{$panel}\\Resources";

            $this->info("🔄 Esecuzione: make:filament-resource {$name} --model-namespace=Modules\\{$module}\\Models --resource-namespace={$resourceNamespace} --force");

            $exitCode = Artisan::call('make:filament-resource', [
                'model' => $name,
                '--model-namespace' => "Modules\\{$module}\\Models",
                '--resource-namespace' => $resourceNamespace,
                '--force' => true,
                '--no-interaction' => true,
            ]);

            $output = Artisan::output();
            if (!empty($output)) {
                $this->line($output);
            }

            if ($exitCode !== 0) {
                $this->error("❌ Errore durante la creazione della risorsa Filament. Exit code: {$exitCode}");
                return 1;
            }

            $this->info("✅ Comando make:filament-resource completato.");

        } catch (\Exception $e) {
            $this->error("❌ Eccezione durante la creazione della risorsa: " . $e->getMessage());
            return 1;
        }

        // 5. Percorsi
        $sourceBase = $panel === 'admin'
            ? app_path("Filament/Admin/Resources")
            : app_path("Filament/{$panel}/Resources");
        $targetBase = base_path("Modules/{$module}/Filament/Resources");

        if (!File::isDirectory($sourceBase)) {
            File::makeDirectory($sourceBase, 0755, true);
        }

        if (!File::isDirectory($targetBase)) {
            File::makeDirectory($targetBase, 0755, true);
        }

        // 5. Processo la Risorsa Principale
        $resourceFile = "{$name}Resource.php";
        $pluralName = Str::plural($name);

        // Controlla sia il nome singolare che plurale per la risorsa (Filament 5 usa sottocartelle plurali)
        $resourcePath = null;
        $sourceSubdir = null;

        if (File::exists("{$sourceBase}/{$resourceFile}")) {
            $resourcePath = "{$sourceBase}/{$resourceFile}";
        } elseif (File::exists("{$sourceBase}/{$pluralName}/{$resourceFile}")) {
            $resourcePath = "{$sourceBase}/{$pluralName}/{$resourceFile}";
            $sourceSubdir = $pluralName;
        } elseif (File::exists("{$sourceBase}/{$name}Resource/{$resourceFile}")) {
            $resourcePath = "{$sourceBase}/{$name}Resource/{$resourceFile}";
            $sourceSubdir = $name . 'Resource';
        }

        if ($resourcePath) {
            // Se c'è una sottocartella, copia tutta la struttura
            if ($sourceSubdir) {
                $targetSubdir = "{$targetBase}/{$sourceSubdir}";
                File::ensureDirectoryExists($targetSubdir);

                // Copia la risorsa
                $this->generateValidFilament5Resource($resourcePath, "{$targetSubdir}/{$resourceFile}", $module, $name, $sourceSubdir);

                // Copia le sottocartelle (Pages, Schemas, Tables)
                $sourceDir = dirname($resourcePath);
                $this->copyResourceSubdirectories($sourceDir, $targetSubdir, $module, $sourceSubdir);

                // Pulisci la cartella temporanea
                File::deleteDirectory("{$sourceBase}/{$sourceSubdir}");

                $this->info("✅ Risorsa {$name}Resource spostata nel modulo {$module} con struttura completa.");
            } else {
                $this->generateValidFilament5Resource($resourcePath, "{$targetBase}/{$resourceFile}", $module, $name);
                $this->info("✅ Risorsa {$name}Resource spostata nel modulo {$module}.");
            }
        } else {
            $this->warn("⚠️ File di risorsa non trovato: {$resourceFile}");
            // Debug: mostra cosa c'è nella directory
            $this->warn("🔍 Directory: {$sourceBase}");
            if (File::isDirectory($sourceBase)) {
                $this->warn("🔍 Contenuto: " . implode(', ', scandir($sourceBase)));
                if (File::isDirectory("{$sourceBase}/{$pluralName}")) {
                    $this->warn("🔍 Sottocartella {$pluralName}: " . implode(', ', scandir("{$sourceBase}/{$pluralName}")));
                }
            } else {
                $this->warn("🔍 La directory non esiste");
            }
        }

        // 6. Processo la cartella delle Pagine (già gestita sopra se c'è sottocartella)
        if (!$sourceSubdir) {
            $pagesDir = null;
            if (File::isDirectory("{$sourceBase}/{$name}Resource/Pages")) {
                $pagesDir = "{$sourceBase}/{$name}Resource/Pages";
            } elseif (File::isDirectory("{$sourceBase}/{$pluralName}/Pages")) {
                $pagesDir = "{$sourceBase}/{$pluralName}/Pages";
            }

            $targetPagesDir = "{$targetBase}/{$name}Resource/Pages";
            if (!File::isDirectory($targetPagesDir)) {
                File::makeDirectory($targetPagesDir, 0755, true);
            }

            if (File::isDirectory($pagesDir)) {
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
                $this->info("✅ Pagine della risorsa spostate e aggiornate.");
            } else {
                $this->warn("⚠️ Cartella pagine non trovata.");
            }
        }

        Artisan::call('optimize:clear');
        $this->info("🚀 Operazione completata! Modulo {$module} pronto con la risorsa {$name}Resource.");

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
            $this->info("ℹ️ Modello {$name} già esistente.");
            return;
        }

        $modelDir = dirname($modelPath);
        if (!File::isDirectory($modelDir)) {
            File::makeDirectory($modelDir, 0755, true);
        }

        $modelContent = $this->getModelTemplate($name, $module);
        File::put($modelPath, $modelContent);
        $this->info("✅ Modello {$name} creato con successo.");
    }

    protected function createMigrationIfNotExists($name, $module)
    {
        $tableName = Str::plural(Str::snake($name));
        $migrationName = "create_{$tableName}_table";
        $timestamp = date('Y_m_d_His');
        $migrationFileName = "{$timestamp}_{$migrationName}.php";

        $modulePath = base_path("Modules/{$module}");
        $migrationsDir = "{$modulePath}/database/migrations";

        // Verifica se la migration esiste già
        if (File::exists($migrationsDir) && count(File::glob("{$migrationsDir}/*_{$migrationName}.php")) > 0) {
            $this->info("ℹ️ Migration {$migrationName} già esistente.");
            return;
        }

        // Verifica se la tabella esiste già nel database
        if (\Schema::hasTable($tableName)) {
            $this->info("ℹ️ Tabella {$tableName} già esistente nel database. Migration non creata.");
            return;
        }

        // Assicura che la directory migrations esista
        if (!File::isDirectory($migrationsDir)) {
            File::makeDirectory($migrationsDir, 0755, true);
        }

        $migrationPath = "{$migrationsDir}/{$migrationFileName}";
        $migrationContent = $this->getMigrationTemplate($tableName);

        File::put($migrationPath, $migrationContent);
        $this->info("✅ Migration {$migrationName} creata con successo.");

        // Esegui automaticamente la migration
        try {
            Artisan::call('migrate', [
                '--path' => "Modules/{$module}/database/migrations",
                '--force' => true,
            ]);
            $this->info("✅ Migration eseguita con successo.");
        } catch (\Exception $e) {
            $this->warn("⚠️ Impossibile eseguire automaticamente la migration: " . $e->getMessage());
            $this->info("ℹ️ Esegui manualmente: php artisan migrate --path=Modules/{$module}/database/migrations");
        }
    }

    protected function ensureSelectedPanelDiscoversModules($panel): void
    {
        $panelPath = $this->resolvePanelProviderPath($panel);

        if (!$panelPath) {
            $this->warn("⚠️ Nessun PanelProvider trovato da aggiornare. Usa --panel=NomePanelProvider.");
            return;
        }

        $content = File::get($panelPath);

        if (!str_contains($content, 'Greatwolf\\FilamentModuleGenerator\\Plugins\\ModuleDiscoveryPlugin')) {
            $content = preg_replace(
                '/use Filament\\Widgets\\FilamentInfoWidget;\r?\n/',
                "use Filament\\Widgets\\FilamentInfoWidget;\nuse Greatwolf\\FilamentModuleGenerator\\Plugins\\ModuleDiscoveryPlugin;\n",
                $content,
                1
            );
        }

        if (!str_contains($content, 'ModuleDiscoveryPlugin::make()')) {
            $content = preg_replace(
                '/(->colors\(\[\s*\r?\n\s*\'primary\' => [^\]]+\]\))/s',
                "$1\n            ->plugin(ModuleDiscoveryPlugin::make())",
                $content,
                1
            );
        }

        File::put($panelPath, $content);
        $this->info("✅ PanelProvider aggiornato con ModuleDiscoveryPlugin: " . basename($panelPath));
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
            $this->warn('⚠️ Impossibile aggiornare automaticamente Composer autoload: ' . $exception->getMessage());
        }
    }

    /**
     * Copia le sottocartelle delle risorse (Pages, Schemas, Tables)
     */
    protected function copyResourceSubdirectories($sourceDir, $targetDir, $module, $subdir = null)
    {
        $subdirs = ['Pages', 'Schemas', 'Tables'];
        $subdirName = $subdir ? $subdir : basename($targetDir);

        foreach ($subdirs as $subdirType) {
            $sourceSubdir = "{$sourceDir}/{$subdirType}";
            $targetSubdir = "{$targetDir}/{$subdirType}";

            if (File::isDirectory($sourceSubdir)) {
                File::ensureDirectoryExists($targetSubdir);

                foreach (File::allFiles($sourceSubdir) as $file) {
                    if ($file->getExtension() === 'php') {
                        $filename = $file->getFilename();
                        $className = str_replace('.php', '', $filename);

                        // Per le pagine, usa il metodo di generazione
                        if ($subdirType === 'Pages') {
                            $this->generateValidFilament5Page($file->getPathname(), "{$targetSubdir}/{$filename}", $module, '', $subdirName);
                        } else {
                            $content = File::get($file->getPathname());

                            // Aggiorna namespace
                            $content = str_replace(
                                "namespace App\\Filament\\",
                                "namespace Modules\\{$module}\\Filament\\",
                                $content
                            );
                            $content = str_replace(
                                "namespace App\\Filament\\Admin\\",
                                "namespace Modules\\{$module}\\Filament\\",
                                $content
                            );
                            $content = str_replace(
                                "Modules\\{$module}\\Filament\\Admin\\",
                                "Modules\\{$module}\\Filament\\",
                                $content
                            );

                            // Aggiorna namespace delle risorse nei use statements
                            $content = str_replace(
                                "use App\\Filament\\",
                                "use Modules\\{$module}\\Filament\\",
                                $content
                            );
                            $content = str_replace(
                                "Modules\\{$module}\\Filament\\Admin\\",
                                "Modules\\{$module}\\Filament\\",
                                $content
                            );

                            // Aggiorna namespace per includere la sottocartella
                            $content = str_replace(
                                "Modules\\{$module}\\Filament\\Resources\\",
                                "Modules\\{$module}\\Filament\\Resources\\{$subdirName}\\",
                                $content
                            );

                            File::put("{$targetSubdir}/{$filename}", $content);
                        }
                    }
                }
            }
        }
    }

    protected function generateValidFilament5Resource($source, $dest, $module, $name, $subdir = null)
    {
        $moduleSlug = Str::slug($module);
        $resourceSlug = Str::plural(Str::kebab($name));

        // Determina se c'è una sottocartella nel path di destinazione
        $destPath = pathinfo($dest, PATHINFO_DIRNAME);
        $subdirName = $subdir ? $subdir : basename($destPath);
        $resourceBasePath = basename(dirname($destPath));
        $hasSubdir = ($resourceBasePath === 'Resources' && $subdirName !== 'Resources');

        $namespaceSuffix = $hasSubdir ? "\\{$subdirName}" : "";
        $pagesNamespace = $hasSubdir ? "\\{$subdirName}" : "\\{$name}Resource";

        $content = "<?php

namespace Modules\\{$module}\\Filament\\Resources{$namespaceSuffix};

use Modules\\{$module}\\Models\\{$name};
use BackedEnum;
use UnitEnum;
use Filament\\Forms;
use Filament\\Forms\\Form;
use Filament\\Resources\\Resource;
use Filament\\Schemas\\Schema;
use Filament\\Tables;
use Filament\\Tables\\Table;
use Illuminate\\Database\\Eloquent\\Builder;
use Illuminate\\Database\\Eloquent\\SoftDeletingScope;
use Modules\\{$module}\\Filament\\Resources{$namespaceSuffix}\\Pages;

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

    protected function generateValidFilament5Page($source, $dest, $module, $name, $subdir = null)
    {
        $content = File::get($source);

        $pluralName = Str::plural($name);
        $subdirName = $subdir ? $subdir : $name;

        $content = preg_replace(
            '/namespace App\\\\Filament\\\\Resources\\\\(?:' . preg_quote($name . 'Resource', '/') . '|' . preg_quote($pluralName, '/') . '|' . preg_quote($subdirName, '/') . ')\\\\Pages;/',
            "namespace Modules\\{$module}\\Filament\\Resources\\{$subdirName}\\Pages;",
            $content
        );

        $content = preg_replace(
            '/use App\\\\Filament\\\\Resources\\\\(?:' . preg_quote($name . 'Resource', '/') . '|' . preg_quote($pluralName, '/') . '|' . preg_quote($subdirName, '/') . ')\\\\' . preg_quote($name . 'Resource', '/') . ';/',
            "use Modules\\{$module}\\Filament\\Resources\\{$subdirName}\\{$name}Resource;",
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
