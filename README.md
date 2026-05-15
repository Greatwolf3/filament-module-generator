# Filament Module Generator

A plugin for Filament 5 that simplifies multilingual resource generation for Laravel modules with `nwidart/laravel-modules`.

## Features

- ✅ Automatic nwidart module generation
- ✅ Filament 5 compatible resource creation
- ✅ Automatic correct namespaces
- ✅ **Multilingual support with --languages option**
- ✅ Migration with existing table check
- ✅ Navigation groups for menu organization
- ✅ Complete and functional Filament 5 tables
- ✅ **Plural subdirectories for resources (e.g. Products/)**
- ✅ **Automatic PanelProvider registration with discoverResources**

## Installation

```bash
composer require greatwolf3/filament-module-generator
```

The service provider will be registered automatically.

## Requirements

- PHP 8.4+
- Laravel 13.0+
- Filament 5.4+
- nwidart/laravel-modules 13.0+

## Configuration

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag="filament-module-generator-config"
```

## Usage

### Module Resource Generation

```bash
# Generate a resource in an existing module (default: Italian and English)
php artisan module:filament-resource Category Prova

# Generate a resource with specific multilingual support
php artisan module:filament-resource Product Ecommerce --languages=it,en,fr,de

# Generate a resource in a new module (automatically creates the module)
php artisan module:filament-resource Product Ecommerce --panel=admin
```

The command automatically executes:
1. ✅ Module verification/creation
2. ✅ Model creation with multilingual fields
3. ✅ Migration creation with columns for each language
4. ✅ Automatic migration execution
5. ✅ Filament 5 resource generation with translatable fields
6. ✅ Automatic movement to module with plural subdirectories
7. ✅ Page creation (List, Create, Edit)
8. ✅ Automatic PanelProvider registration

### Module Management

```bash
# List all modules
php artisan module:list

# Create a new module
php artisan module:make NomeModulo
```

## Generated Structure

The plugin creates the following structure with plural subdirectories:

```
Modules/
├── Ecommerce/
│   ├── app/
│   │   └── Models/
│   │       └── Product.php
│   ├── database/
│   │   └── migrations/
│   │       └── 2026_05_15_201234_create_products_table.php
│   └── Filament/
│       └── Resources/
│           └── Products/           # Plural subdirectory
│               ├── ProductResource.php
│               └── Pages/
│                   ├── ListProducts.php
│                   ├── CreateProduct.php
│                   └── EditProduct.php
```

## Multilingual Support

The generator automatically creates translatable fields for each specified language:

### Model (Product.php)
```php
protected $fillable = [
    'name',
    'name_it',
    'name_en',
    'name_fr',
];
```

### Migration
```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('name_it')->nullable();
    $table->string('name_en')->nullable();
    $table->string('name_fr')->nullable();
    $table->timestamps();
});
```

### Filament Resource
```php
public static function form(Schema $form): Schema
{
    return $form
        ->schema([
            Forms\Components\TextInput::make('name_it')
                ->label('Name (it)')
                ->maxLength(255),
            Forms\Components\TextInput::make('name_en')
                ->label('Name (en)')
                ->maxLength(255),
            Forms\Components\TextInput::make('name_fr')
                ->label('Name (fr)')
                ->maxLength(255),
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
        ]);
}
```

## Example Generated Resource

```php
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $slug = 'ecommerce/products';
    protected static string|UnitEnum|null $navigationGroup = 'Ecommerce';

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                // Automatically generated multilingual fields
                Forms\Components\TextInput::make('name_it')
                    ->label('Name (it)')
                    ->maxLength(255),
                Forms\Components\TextInput::make('name_en')
                    ->label('Name (en)')
                    ->maxLength(255),
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
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
```

## Advanced Features

### Plural Subdirectories

Resources are organized in plural subdirectories for better organization:
- `Products/` instead of `Product/`
- `Categories/` instead of `Category/`

### Automatic PanelProvider Registration

The generator automatically registers resources in the specified PanelProvider using `discoverResources`:

```php
->discoverResources(in: base_path('Modules/Ecommerce/Filament/Resources'), for: 'Modules\Ecommerce\Filament\Resources')
```

### Correct Namespaces

The plugin automatically corrects namespaces in generated module providers to ensure PSR-4 compatibility.

### Smart Migrations

Migrations include checks to avoid duplicate table creation and are executed automatically.

## Available Commands

| Command | Description |
|---------|-------------|
| `module:filament-resource {name} {module}` | Generate multilingual Filament resource for module |
| `--panel={panel}` | Specify the PanelProvider to update |
| `--languages={it,en,fr}` | Specify supported languages (default: it,en) |

## AdminPanelProvider Integration

The generator automatically adds resource registration to your `AdminPanelProvider`:

```php
// In the panel() method
->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
->discoverResources(in: base_path('Modules/Ecommerce/Filament/Resources'), for: 'Modules\Ecommerce\Filament\Resources')
```

No manual auto-discovery configuration is required.

## License

MIT License

## Support

For issues or feature requests, open an issue on GitHub.

---

# Generatore Modulo Filament

Un plugin per Filament 5 che semplifica la generazione di risorse multilingua per moduli Laravel con `nwidart/laravel-modules`.

## Caratteristiche

- ✅ Generazione automatica di moduli nwidart
- ✅ Creazione di risorse Filament 5 compatibili
- ✅ Namespace corretti automatici
- ✅ **Supporto multilingua con opzione --languages**
- ✅ Migration con controllo tabella esistente
- ✅ Navigation groups per organizzazione menu
- ✅ Tabelle Filament 5 complete e funzionanti
- ✅ **Sottodirectory plurali per risorse (es. Products/)**
- ✅ **Registrazione automatica nel PanelProvider con discoverResources**

## Installazione

```bash
composer require greatwolf3/filament-module-generator
```

Il provider del servizio verrà registrato automaticamente.

## Requisiti

- PHP 8.4+
- Laravel 13.0+
- Filament 5.4+
- nwidart/laravel-modules 13.0+

## Configurazione

Pubblica il file di configurazione (opzionale):

```bash
php artisan vendor:publish --tag="filament-module-generator-config"
```

## Utilizzo

### Generazione Risorse Modulo

```bash
# Genera una risorsa in un modulo esistente (default: italiano e inglese)
php artisan module:filament-resource Category Prova

# Genera una risorsa con supporto multilingua specifico
php artisan module:filament-resource Product Ecommerce --languages=it,en,fr,de

# Genera una risorsa in un nuovo modulo (crea automaticamente il modulo)
php artisan module:filament-resource Product Ecommerce --panel=admin
```

Il comando esegue automaticamente:
1. ✅ Verifica/creazione del modulo
2. ✅ Creazione del modello con campi multilingua
3. ✅ Creazione della migration con colonne per ogni lingua
4. ✅ Esecuzione automatica della migration
5. ✅ Generazione della risorsa Filament 5 con campi translatibili
6. ✅ Spostamento automatico nel modulo con sottodirectory plurali
7. ✅ Creazione delle pagine (List, Create, Edit)
8. ✅ Registrazione automatica nel PanelProvider

### Gestione Moduli

```bash
# Lista tutti i moduli
php artisan module:list

# Crea un nuovo modulo
php artisan module:make NomeModulo
```

## Struttura Generata

Il plugin crea la seguente struttura con sottodirectory plurali:

```
Modules/
├── Ecommerce/
│   ├── app/
│   │   └── Models/
│   │       └── Product.php
│   ├── database/
│   │   └── migrations/
│   │       └── 2026_05_15_201234_create_products_table.php
│   └── Filament/
│       └── Resources/
│           └── Products/           # Sottodirectory plurale
│               ├── ProductResource.php
│               └── Pages/
│                   ├── ListProducts.php
│                   ├── CreateProduct.php
│                   └── EditProduct.php
```

## Supporto Multilingua

Il generatore crea automaticamente campi translatibili per ogni lingua specificata:

### Modello (Product.php)
```php
protected $fillable = [
    'name',
    'name_it',
    'name_en',
    'name_fr',
];
```

### Migration
```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('name_it')->nullable();
    $table->string('name_en')->nullable();
    $table->string('name_fr')->nullable();
    $table->timestamps();
});
```

### Risorsa Filament
```php
public static function form(Schema $form): Schema
{
    return $form
        ->schema([
            Forms\Components\TextInput::make('name_it')
                ->label('Name (it)')
                ->maxLength(255),
            Forms\Components\TextInput::make('name_en')
                ->label('Name (en)')
                ->maxLength(255),
            Forms\Components\TextInput::make('name_fr')
                ->label('Name (fr)')
                ->maxLength(255),
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
        ]);
}
```

## Esempio di Risorsa Generata

```php
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $slug = 'ecommerce/products';
    protected static string|UnitEnum|null $navigationGroup = 'Ecommerce';

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                // Campi multilingua generati automaticamente
                Forms\Components\TextInput::make('name_it')
                    ->label('Name (it)')
                    ->maxLength(255),
                Forms\Components\TextInput::make('name_en')
                    ->label('Name (en)')
                    ->maxLength(255),
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
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
```

## Funzionalità Avanzate

### Sottodirectory Plurali

Le risorse vengono organizzate in sottodirectory plurali per una migliore organizzazione:
- `Products/` invece di `Product/`
- `Categories/` invece di `Category/`

### Registrazione Automatica PanelProvider

Il generatore registra automaticamente le risorse nel PanelProvider specificato usando `discoverResources`:

```php
->discoverResources(in: base_path('Modules/Ecommerce/Filament/Resources'), for: 'Modules\Ecommerce\Filament\Resources')
```

### Namespace Corretti

Il plugin corregge automaticamente i namespace nei provider dei moduli generati per garantire compatibilità con PSR-4.

### Migration Intelligenti

Le migration includono controlli per evitare la creazione di tabelle duplicate e vengono eseguite automaticamente.

## Comandi Disponibili

| Comando | Descrizione |
|---------|-------------|
| `module:filament-resource {name} {module}` | Genera risorsa Filament multilingua per modulo |
| `--panel={panel}` | Specifica il PanelProvider da aggiornare |
| `--languages={it,en,fr}` | Specifica le lingue supportate (default: it,en) |

## Integrazione con AdminPanelProvider

Il generatore aggiunge automaticamente la registrazione delle risorse nel tuo `AdminPanelProvider`:

```php
// Nel metodo panel()
->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
->discoverResources(in: base_path('Modules/Ecommerce/Filament/Resources'), for: 'Modules\Ecommerce\Filament\Resources')
```

Non è necessario configurare manualmente l'auto-discovery.

## Licenza

MIT License

## Supporto

Per problemi o richieste di funzionalità, apri una issue su GitHub.
