<?php

namespace Greatwolf\FilamentModuleGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleResource extends Command
{
    protected $signature = 'module:filament-resource {name} {module}';
    protected $description = 'Genera risorsa e pagine Filament 5 per un modulo nwidart';

    public function handle()
    {
        $name = Str::studly($this->argument('name'));
        $module = Str::studly($this->argument('module'));

        $this->warn("🛠️ Generazione Risorsa e Pagine per Filament 5...");

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

        // 2. Crea il modello se non esiste
        $this->createModelIfNotExists($name, $module);

        // 3. Crea la migration se non esiste
        $this->createMigrationIfNotExists($name, $module);

        // 4. Esecuzione comando nativo (genera in app/Filament)
        $this->info("📝 Creazione risorsa Filament...");

        try {
            $this->info("🔄 Esecuzione: make:filament-resource {$name} --model-namespace=Modules\\{$module}\\Models --force");

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
                $this->error("❌ Errore durante la creazione della risorsa Filament. Exit code: {$exitCode}");
                return 1;
            }

            $this->info("✅ Comando make:filament-resource completato.");

        } catch (\Exception $e) {
            $this->error("❌ Eccezione durante la creazione della risorsa: " . $e->getMessage());
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
            $this->info("✅ Risorsa {$name}Resource spostata nel modulo {$module}.");
        } else {
            $this->warn("⚠️ File di risorsa non trovato: {$resourceFile}");
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
            $this->info("✅ Pagine della risorsa spostate e aggiornate.");
        } else {
            $this->warn("⚠️ Cartella pagine non trovata.");
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

    protected function generateValidFilament5Resource($source, $dest, $module, $name)
    {
        $content = "<?php

namespace Modules\\{$module}\\Filament\\Resources;

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
use Modules\\{$module}\\Filament\\Resources\\{$name}Resource\\Pages;

class {$name}Resource extends Resource
{
    protected static ?string \$model = {$name}::class;

    protected static string|BackedEnum|null \$navigationIcon = 'heroicon-o-rectangle-stack';

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

        // Fix namespace
        $content = str_replace(
            "namespace App\\Filament\\Resources\\{$name}Resource\\Pages;",
            "namespace Modules\\{$module}\\Filament\\Resources\\{$name}Resource\\Pages;",
            $content
        );

        // Fix use statements
        $content = str_replace(
            "use App\\Filament\\Resources\\{$name}Resource;",
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
