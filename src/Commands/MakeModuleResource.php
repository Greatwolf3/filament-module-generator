<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleResource extends Command
{
    protected $signature = 'module:filament-resource {name} {module} {--panel=admin}';
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
                $this->fixModuleProviderNamespaces($module);

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

        // 2. Crea il modello se non esiste
        $this->createModelIfNotExists($name, $module);

        // 3. Crea la migration se non esiste
        $this->createMigrationIfNotExists($name, $module);

        // 4. Esecuzione comando nativo (genera in app/Filament)
        $this->info("📝 Creazione risorsa Filament...");

        try {
            $resourceNamespace = $panel === 'admin'
                ? "App\\Filament\\Resources"
                : "App\\Filament\\{$panel}\\Resources";

            $this->info("🔄 Esecuzione: make:filament-resource {$name} --model-namespace=Modules\\{$module}\\Models --force");

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

        // 4. Percorsi
        $sourceBase = $panel === 'admin'
            ? app_path("Filament/Resources")
            : app_path("Filament/{$panel}/Resources");
        $targetBase = base_path("Modules/{$module}/Filament/Resources");

        if (!File::isDirectory($targetBase)) {
            File::makeDirectory($targetBase, 0755, true);
        }

        // 5. Processo la Risorsa Principale
        $resourceFile = "{$name}Resource.php";
        $pluralName = Str::plural($name);

        // Cerca il file della risorsa (Filament 5 usa sottocartelle plurali)
        $resourcePath = null;
        $sourceSubdir = null;

        if (File::exists("{$sourceBase}/{$resourceFile}")) {
            $resourcePath = "{$sourceBase}/{$resourceFile}";
        } elseif (File::exists("{$sourceBase}/{$pluralName}/{$resourceFile}")) {
            $resourcePath = "{$sourceBase}/{$pluralName}/{$resourceFile}";
            $sourceSubdir = $pluralName;
        }

        if ($resourcePath) {
            // Se c'è una sottocartella, copia tutta la struttura
            if ($sourceSubdir) {
                $targetSubdir = "{$targetBase}/{$sourceSubdir}";
                File::ensureDirectoryExists($targetSubdir);

                // Copia la risorsa
                $this->generateValidFilament5Resource($resourcePath, "{$targetSubdir}/{$resourceFile}", $module, $name, $panel);

                // Copia le sottocartelle (Pages, Schemas, Tables)
                $sourceDir = dirname($resourcePath);
                $this->copyResourceSubdirectories($sourceDir, $targetSubdir, $module, $panel);

                // Pulisci la cartella temporanea
                File::deleteDirectory("{$sourceBase}/{$sourceSubdir}");

                $this->info("✅ Risorsa {$name}Resource spostata nel modulo {$module} con struttura completa.");
            } else {
                $this->generateValidFilament5Resource($resourcePath, "{$targetBase}/{$resourceFile}", $module, $name, $panel);
                $this->info("✅ Risorsa {$name}Resource spostata nel modulo {$module}.");
            }
        } else {
            $this->warn("⚠️ File di risorsa non trovato: {$resourceFile}");
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

            if (File::isDirectory($pagesDir)) {
                File::ensureDirectoryExists($targetPagesDir);

                foreach (File::allFiles($pagesDir) as $pageFile) {
                    $relativeDest = $targetPagesDir . '/' . $pageFile->getFilename();
                    $this->generateValidFilament5Page($pageFile->getPathname(), $relativeDest, $module, $name, $panel);
                }

                // Pulisco le cartelle temporanee in app/
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
        $this->info("🚀 Operazione completata! Modulo {$module} pronto con la risorsa {$name}Resource per il panel {$panel}.");

        // 7. Aggiorna il Panel Provider per scoprire i moduli
        $this->updatePanelProvider($panel);

        return 0;
    }

    /**
     * Genera una risorsa Filament 5 valida
     */
    protected function generateValidFilament5Resource($source, $dest, $module, $name, $panel = 'admin')
    {
        // Determina se c'è una sottocartella nel path di destinazione
        $destPath = pathinfo($dest, PATHINFO_DIRNAME);
        $subdirName = basename($destPath);
        $resourceBasePath = basename(dirname($destPath));
        $hasSubdir = ($resourceBasePath === 'Resources' && $subdirName !== 'Resources');

        $pluralName = Str::plural($name);
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
            'index' => Pages\\List{$pluralName}::route('/'),
            'create' => Pages\\Create{$name}::route('/create'),
            'edit' => Pages\\Edit{$name}::route('/{record}/edit'),
        ];
    }
}";

        File::put($dest, $content);
        File::delete($source);
    }

    /**
     * Copia le sottocartelle delle risorse (Pages, Schemas, Tables)
     */
    protected function copyResourceSubdirectories($sourceDir, $targetDir, $module, $panel)
    {
        $subdirs = ['Pages', 'Schemas', 'Tables'];
        $subdirName = basename($targetDir);

        foreach ($subdirs as $subdirType) {
            $sourceSubdir = "{$sourceDir}/{$subdirType}";
            $targetSubdir = "{$targetDir}/{$subdirType}";

            if (File::isDirectory($sourceSubdir)) {
                File::ensureDirectoryExists($targetSubdir);

                foreach (File::allFiles($sourceSubdir) as $file) {
                    if ($file->getExtension() === 'php') {
                        $content = File::get($file->getPathname());
                        $filename = $file->getFilename();

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

                        // Per le pagine, usa i metodi di generazione corretti
                        if ($subdirType === 'Pages') {
                            $className = str_replace('.php', '', $filename);
                            if (str_contains($className, 'List')) {
                                $content = $this->getListPageContent($module, '', $className, $subdirName);
                            } elseif (str_contains($className, 'Create')) {
                                $content = $this->getCreatePageContent($module, '', $className, $subdirName);
                            } elseif (str_contains($className, 'Edit')) {
                                $content = $this->getEditPageContent($module, '', $className, $subdirName);
                            }
                        }

                        File::put("{$targetSubdir}/{$filename}", $content);
                    }
                }
            }
        }
    }

    /**
     * Genera pagine Filament 5 valide
     */
    protected function generateValidFilament5Page($source, $dest, $module, $name, $panel = 'admin', $subdir = null)
    {
        $filename = basename($source, '.php');
        $className = str_replace('.php', '', $filename);

        // Determina il tipo di pagina
        if (str_contains($filename, 'List')) {
            $content = $this->getListPageContent($module, $name, $className, $subdir);
        } elseif (str_contains($filename, 'Create')) {
            $content = $this->getCreatePageContent($module, $name, $className, $subdir);
        } elseif (str_contains($filename, 'Edit')) {
            $content = $this->getEditPageContent($module, $name, $className, $subdir);
        } else {
            // Copia il file e aggiorna namespace
            $content = File::get($source);
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
                "use App\\Filament\\",
                "use Modules\\{$module}\\Filament\\",
                $content
            );
        }

        File::put($dest, $content);
        File::delete($source);
    }

    /**
     * Genera contenuto per pagina List
     */
    protected function getListPageContent($module, $name, $className, $subdir = null)
    {
        $namespaceSuffix = $subdir ? "\\{$subdir}" : "";
        $resourceNamespace = $subdir ? "\\{$subdir}" : "\\{$name}Resource";

        return "<?php

namespace Modules\\{$module}\\Filament\\Resources{$namespaceSuffix}\\Pages;

use Modules\\{$module}\\Filament\\Resources{$namespaceSuffix}\\{$name}Resource;
use Filament\\Actions;
use Filament\\Resources\\Pages\\ListRecords;

class {$className} extends ListRecords
{
    protected static string \$resource = {$name}Resource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\\CreateAction::make(),
        ];
    }
}";
    }

    /**
     * Genera contenuto per pagina Create
     */
    protected function getCreatePageContent($module, $name, $className, $subdir = null)
    {
        $namespaceSuffix = $subdir ? "\\{$subdir}" : "";
        $resourceNamespace = $subdir ? "\\{$subdir}" : "\\{$name}Resource";

        return "<?php

namespace Modules\\{$module}\\Filament\\Resources{$namespaceSuffix}\\Pages;

use Modules\\{$module}\\Filament\\Resources{$namespaceSuffix}\\{$name}Resource;
use Filament\\Actions;
use Filament\\Resources\\Pages\\CreateRecord;

class {$className} extends CreateRecord
{
    protected static string \$resource = {$name}Resource::class;
}";
    }

    /**
     * Genera contenuto per pagina Edit
     */
    protected function getEditPageContent($module, $name, $className, $subdir = null)
    {
        $namespaceSuffix = $subdir ? "\\{$subdir}" : "";
        $resourceNamespace = $subdir ? "\\{$subdir}" : "\\{$name}Resource";

        return "<?php

namespace Modules\\{$module}\\Filament\\Resources{$namespaceSuffix}\\Pages;

use Modules\\{$module}\\Filament\\Resources{$namespaceSuffix}\\{$name}Resource;
use Filament\\Actions;
use Filament\\Resources\\Pages\\EditRecord;

class {$className} extends EditRecord
{
    protected static string \$resource = {$name}Resource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\\DeleteAction::make(),
        ];
    }
}";
    }

    /**
     * Verifica se il modulo esiste
     */
    protected function moduleExists($module)
    {
        return File::isDirectory(base_path("Modules/{$module}"));
    }

    /**
     * Crea il modello se non esiste
     */
    protected function createModelIfNotExists($name, $module)
    {
        $modelPath = base_path("Modules/{$module}/app/Models/{$name}.php");

        if (!File::exists($modelPath)) {
            $this->info("📝 Creazione modello {$name}...");

            $modelContent = $this->getModelTemplate($name, $module);

            // Assicura che la directory Models esista
            $modelsDir = dirname($modelPath);
            if (!File::isDirectory($modelsDir)) {
                File::makeDirectory($modelsDir, 0755, true);
            }

            File::put($modelPath, $modelContent);
            $this->info("✅ Modello {$name} creato con successo.");
        } else {
            $this->info("ℹ️ Modello {$name} già esistente.");
        }
    }

    /**
     * Crea la migration se non esiste
     */
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

    /**
     * Template per la migration
     */
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

    /**
     * Fix namespaces in generated module provider files
     * Removes incorrect App\ from provider namespaces to match PSR-4 autoload
     */
    protected function fixModuleProviderNamespaces($module)
    {
        $modulePath = base_path("Modules/{$module}");

        // Fix module.json - remove App\ from provider path
        $moduleJsonPath = "{$modulePath}/module.json";
        if (File::exists($moduleJsonPath)) {
            $content = File::get($moduleJsonPath);
            $content = str_replace(
                "\"Modules\\\\{$module}\\\\App\\\\Providers\\\\",
                "\"Modules\\\\{$module}\\\\Providers\\\\",
                $content
            );
            File::put($moduleJsonPath, $content);
        }

        // Fix provider files - remove App\ from namespace
        $providersDir = "{$modulePath}/app/Providers";
        if (File::isDirectory($providersDir)) {
            $providerFiles = File::allFiles($providersDir);

            foreach ($providerFiles as $file) {
                if ($file->getExtension() === 'php') {
                    $filePath = $file->getPathname();
                    $content = File::get($filePath);

                    // Fix namespace - remove App\
                    $content = str_replace(
                        "namespace Modules\\{$module}\\App\\Providers;",
                        "namespace Modules\\{$module}\\Providers;",
                        $content
                    );

                    File::put($filePath, $content);
                }
            }
        }

        $this->info("✅ Namespace corretti nel modulo {$module}.");
    }

    /**
     * Aggiorna il Panel Provider per scoprire i moduli
     */
    protected function updatePanelProvider($panel)
    {
        $panelName = Str::studly($panel);
        $panelProviderFile = app_path("Providers/Filament/{$panelName}PanelProvider.php");

        if (!File::exists($panelProviderFile)) {
            $this->warn("⚠️ Panel Provider {$panelName}PanelProvider non trovato. Creazione in corso...");
            $this->createPanelProvider($panelName, $panelProviderFile);
        }

        $this->addModuleDiscoveryToPanelProvider($panelProviderFile, $panelName);
        $this->info("✅ Panel Provider {$panelName}PanelProvider aggiornato per scoprire i moduli.");
    }

    /**
     * Crea un nuovo Panel Provider se non esiste
     */
    protected function createPanelProvider($panelName, $filePath)
    {
        $content = $this->getPanelProviderTemplate($panelName);
        File::put($filePath, $content);
    }

    /**
     * Template per un nuovo Panel Provider
     */
    protected function getPanelProviderTemplate($panelName)
    {
        $panelId = strtolower($panelName);
        return "<?php

namespace App\\Providers\\Filament;

use Filament\\Http\\Middleware\\Authenticate;
use Filament\\Http\\Middleware\\AuthenticateSession;
use Filament\\Http\\Middleware\\DisableBladeIconComponents;
use Filament\\Http\\Middleware\\DispatchServingFilamentEvent;
use Filament\\Pages\\Dashboard;
use Filament\\Panel;
use Filament\\PanelProvider;
use Filament\\Support\\Colors\\Color;
use Filament\\Widgets\\AccountWidget;
use Filament\\Widgets\\FilamentInfoWidget;
use Illuminate\\Cookie\\Middleware\\AddQueuedCookiesToResponse;
use Illuminate\\Cookie\\Middleware\\EncryptCookies;
use Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery;
use Illuminate\\Routing\\Middleware\\SubstituteBindings;
use Illuminate\\Session\\Middleware\\StartSession;
use Illuminate\\View\\Middleware\\ShareErrorsFromSession;
use Illuminate\\Support\\Facades\\Auth;
use Illuminate\\Support\\Facades\\File;
use Nwidart\\Modules\\Facades\\Module;

class {$panelName}PanelProvider extends PanelProvider
{
    public function panel(Panel \$panel): Panel
    {
        return \$panel
            ->default()
            ->id('{$panelId}')
            ->path('{$panelId}')
            ->login()
            ->authGuard('web')
            ->authMiddleware([
                'web',
            ])
            ->brandName('Zampa Viaggi')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/{$panelName}/Resources'), for: 'App\\Filament\\{$panelName}\\Resources')
            ->discoverPages(in: app_path('Filament/{$panelName}/Pages'), for: 'App\\Filament\\{$panelName}\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/{$panelName}/Widgets'), for: 'App\\Filament\\{$panelName}\\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
";
    }

    /**
     * Aggiunge la logica di scoperta dei moduli al Panel Provider
     */
    protected function addModuleDiscoveryToPanelProvider($filePath, $panelName)
    {
        $content = File::get($filePath);

        // Verifica se gli use statements sono già presenti
        $useFile = "use Illuminate\\Support\\Facades\\File;";
        $useModule = "use Nwidart\\Modules\\Facades\\Module;";

        if (!str_contains($content, $useFile)) {
            // Aggiunge use File dopo gli altri use statements
            $content = str_replace(
                "use Illuminate\\View\\Middleware\\ShareErrorsFromSession;",
                "use Illuminate\\View\\Middleware\\ShareErrorsFromSession;\n" . $useFile,
                $content
            );
        }

        if (!str_contains($content, $useModule)) {
            // Aggiunge use Module dopo use File
            $content = str_replace(
                $useFile,
                $useFile . "\n" . $useModule,
                $content
            );
        }

        // Verifica se la logica di scoperta moduli è già presente
        $moduleDiscoveryMarker = '$modulePath = base_path(\'Modules\');';
        if (str_contains($content, $moduleDiscoveryMarker)) {
            $this->info("ℹ️ La logica di scoperta moduli è già presente nel Panel Provider.");
            return;
        }

        // Aggiunge la logica di scoperta moduli prima del return statement
        $moduleDiscoveryCode = $this->getModuleDiscoveryCode();

        // Trova l'ultimo return $panel; nel metodo panel() e inserisce prima di esso
        // Prima rimuove eventuali return prematuri dopo authMiddleware
        $content = preg_replace(
            '/(->authMiddleware\(\[\s*Authenticate::class,\s*\]\);)\s*return \$panel;/s',
            '$1',
            $content
        );

        // Inserisce il codice di scoperta moduli prima dell'ultimo return $panel
        $content = preg_replace(
            '/(\s+return \$panel;\s+})$/s',
            $moduleDiscoveryCode . '$1',
            $content
        );

        File::put($filePath, $content);
    }

    /**
     * Codice per la scoperta dei moduli
     */
    protected function getModuleDiscoveryCode()
    {
        return "        \$modulePath = base_path('Modules');

        if (File::isDirectory(\$modulePath)) {
            \$modules = File::directories(\$modulePath);

            foreach (\$modules as \$moduleDir) {
                \$moduleName = basename(\$moduleDir);

                // Verifica se il modulo è abilitato
                if (!Module::has(\$moduleName) || !Module::isEnabled(\$moduleName)) {
                    continue;
                }

                \$baseNamespace = \"Modules\\\\{\$moduleName}\\\\Filament\";
                \$basePath = \"{\$modulePath}/{\$moduleName}/Filament\";

                // Registra Risorse del Modulo
                if (File::isDirectory(\"{\$basePath}/Resources\")) {
                    \$panel->discoverResources(
                        in: \"{\$basePath}/Resources\",
                        for: \"{\$baseNamespace}\\\\Resources\"
                    );
                }

                // Registra Pagine del Modulo
                if (File::isDirectory(\"{\$basePath}/Pages\")) {
                    \$panel->discoverPages(
                        in: \"{\$basePath}/Pages\",
                        for: \"{\$baseNamespace}\\\\Pages\"
                    );
                }

                // Registra Widget del Modulo
                if (File::isDirectory(\"{\$basePath}/Widgets\")) {
                    \$panel->discoverWidgets(
                        in: \"{\$basePath}/Widgets\",
                        for: \"{\$baseNamespace}\\\\Widgets\"
                    );
                }
            }
        }\n";
    }

    /**
     * Template per il modello
     */
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
        // Aggiungi qui i campi del tuo modello
        'name',
        'description',
    ];

    protected \$casts = [
        // Aggiungi qui i cast se necessari
    ];
}";
    }
}
