# Filament Module Generator XXX

Un plugin per Filament 5 che semplifica la generazione di risorse per moduli Laravel con `nwidart/laravel-modules`.

## Caratteristiche

- ✅ Generazione automatica di moduli come cluster nwidart
- ✅ Creazione di risorse Filament 5 compatibili
- ✅ Namespace corretti automatici
- ✅ Migration con controllo tabella esistente
- ✅ Navigation groups per organizzazione menu
- ✅ Tabelle Filament 5 complete e funzionanti
- ✅ Gestione completa dei moduli (enable/disable/status)

## Installazione

```bash
composer require Greatwolf3/filament-module-generator
```

Il provider del servizio verrà registrato automaticamente.

## Requisiti

- PHP 8.1+
- Laravel 10.0+ o 11.0+
- Filament 3.0+ o 5.0+
- nwidart/laravel-modules 13.0+

## Configurazione

Pubblica il file di configurazione (opzionale):

```bash
php artisan vendor:publish --tag="filament-module-generator-config"
```

## Utilizzo

### Generazione Risorse Modulo

```bash
# Genera una risorsa in un modulo esistente
php artisan module:filament-resource Category Prova

# Genera una risorsa in un nuovo modulo (crea automaticamente il modulo)
php artisan module:filament-resource Product Ecommerce
```

Il comando esegue automaticamente:
1. ✅ Verifica/creazione del modulo come cluster
2. ✅ Creazione del modello se non esiste
3. ✅ Creazione della migration con controllo tabella esistente
4. ✅ Esecuzione automatica della migration
5. ✅ Generazione della risorsa Filament 5
6. ✅ Spostamento automatico nel modulo
7. ✅ Creazione delle pagine (List, Create, Edit)

### Gestione Moduli

```bash
# Lista tutti i moduli con status
php artisan module:manager list

# Abilita un modulo
php artisan module:manager enable Prova

# Disabilita un modulo (rimuove dal menu Filament)
php artisan module:manager disable Prova

# Status dettagliato di un modulo
php artisan module:manager status Prova
```

## Struttura Generata

Il plugin crea la seguente struttura:

```
Modules/
├── Prova/
│   ├── app/
│   │   └── Models/
│   │       └── Category.php
│   ├── database/
│   │   └── migrations/
│   │       └── 2026_05_12_201234_create_categories_table.php
│   └── Filament/
│       └── Resources/
│           ├── CategoryResource.php
│           └── CategoryResource/
│               └── Pages/
│                   ├── ListCategories.php
│                   ├── CreateCategory.php
│                   └── EditCategory.php
```

## Esempio di Risorsa Generata

```php
class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static string|UnitEnum|null $navigationGroup = 'Prova';

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                // ... altre colonne
            ])
            ->filters([
                // filtri disponibili
            ]);
    }
}
```

## Funzionalità Avanzate

### Auto-discovery Filament

Le risorse vengono automaticamente scoperte dal pannello Filament. I moduli disabilitati vengono automaticamente rimossi dal menu.

### Namespace Corretti

Il plugin corregge automaticamente i namespace nei provider dei moduli generati per garantire compatibilità con PSR-4.

### Migration Intelligenti

Le migration includono controlli per evitare la creazione di tabelle duplicate e vengono eseguite automaticamente.

## Comandi Disponibili

| Comando | Descrizione |
|---------|-------------|
| `module:filament-resource {name} {module}` | Genera risorsa Filament per modulo |
| `module:manager list` | Lista tutti i moduli |
| `module:manager enable {module}` | Abilita modulo |
| `module:manager disable {module}` | Disabilita modulo |
| `module:manager status {module}` | Status dettagliato modulo |

## Integrazione con AdminPanelProvider

Il plugin richiede l'auto-discovery dei moduli nel tuo `AdminPanelProvider`:

```php
use Nwidart\Modules\Facades\Module;
use Illuminate\Support\Facades\File;

// Nel metodo panel()
$modulePath = base_path('Modules');

if (File::isDirectory($modulePath)) {
    $modules = File::directories($modulePath);

    foreach ($modules as $moduleDir) {
        $moduleName = basename($moduleDir);

        // Verifica se il modulo è abilitato
        if (!Module::has($moduleName) || !Module::isEnabled($moduleName)) {
            continue;
        }

        $baseNamespace = "Modules\\{$moduleName}\\Filament";
        $basePath = "{$modulePath}/{$moduleName}/Filament";

        // Registra Risorse, Pagine, Widget...
    }
}
```

## Licenza

MIT License

## Supporto

Per problemi o richieste di funzionalità, apri una issue su GitHub.
