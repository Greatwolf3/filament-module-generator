# Filament Module Generator

## English

Filament Module Generator is a Filament 5 package for Laravel projects using `nwidart/laravel-modules`. It helps generate Filament resources inside modules and provides a Filament plugin to automatically discover module resources and clusters in the selected panel.

### Features

- Generates Filament 5 resources inside Laravel modules.
- Creates the model when it does not exist.
- Creates and runs a migration with table-existence checks.
- Generates module-compatible Filament resource pages.
- Configures module PSR-4 autoloading.
- Adds modular URLs using the resource slug, for example `/admin/prova/categories`.
- Groups generated resources in the Filament menu using the standard `$navigationGroup` property.
- Provides `ModuleDiscoveryPlugin` to register module resources and clusters in a Filament panel.
- Includes module management commands.

### Requirements

- PHP 8.3+
- Laravel 13+
- Filament 5+
- `nwidart/laravel-modules` 13+

### Installation

```bash
composer require greatwolf3/filament-module-generator
```

The service provider is auto-discovered by Laravel.

### Optional configuration

```bash
php artisan vendor:publish --tag="filament-module-generator-config"
```

### Registering the Filament plugin

Add the plugin to the Filament panel where you want module resources to be discovered:

```php
use Greatwolf\FilamentModuleGenerator\Plugins\ModuleDiscoveryPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(ModuleDiscoveryPlugin::make());
}
```

The generator can also update the selected panel automatically using the `--panel` option.

### Generate a module resource

```bash
php artisan module:filament-resource Category Prova --panel=Admin
```

You may also use the full panel provider name:

```bash
php artisan module:filament-resource Category Prova --panel=AdminPanelProvider
```

The command will:

1. Create the module if it does not exist.
2. Configure the module autoloading.
3. Register `ModuleDiscoveryPlugin` in the selected Filament panel.
4. Create the model if it does not exist.
5. Create and run the migration if needed.
6. Generate the Filament resource.
7. Move the resource and pages into the module namespace.
8. Configure standard Filament navigation grouping.

### Generated structure

```text
Modules/
└── Prova/
    ├── app/
    │   └── Models/
    │       └── Category.php
    ├── database/
    │   └── migrations/
    │       └── 2026_05_14_000000_create_categories_table.php
    └── Filament/
        ├── Clusters/
        │   └── ProvaCluster.php
        └── Resources/
            ├── CategoryResource.php
            └── CategoryResource/
                └── Pages/
                    ├── ListCategories.php
                    ├── CreateCategory.php
                    └── EditCategory.php
```

### Generated resource navigation

Generated resources use Filament standard navigation properties:

```php
protected static ?string $slug = 'prova/categories';

protected static string | UnitEnum | null $navigationGroup = 'Prova';
```

This keeps URLs modular while letting Filament manage the menu normally.

### Module management

```bash
php artisan module:manager list
php artisan module:manager enable Prova
php artisan module:manager disable Prova
php artisan module:manager status Prova
```

### License

MIT License.

---

## Italiano

Filament Module Generator è un package Filament 5 per progetti Laravel che usano `nwidart/laravel-modules`. Aiuta a generare risorse Filament dentro i moduli e include un plugin Filament per scoprire automaticamente resource e cluster dei moduli nel pannello scelto.

### Funzionalità

- Genera risorse Filament 5 dentro i moduli Laravel.
- Crea il model se non esiste.
- Crea ed esegue una migration con controllo sull'esistenza della tabella.
- Genera pagine Filament compatibili con il namespace del modulo.
- Configura l'autoload PSR-4 del modulo.
- Aggiunge URL modulari usando lo slug della resource, ad esempio `/admin/prova/categories`.
- Raggruppa le risorse generate nel menu Filament usando la proprietà standard `$navigationGroup`.
- Fornisce `ModuleDiscoveryPlugin` per registrare resource e cluster dei moduli in un pannello Filament.
- Include comandi per la gestione dei moduli.

### Requisiti

- PHP 8.3+
- Laravel 13+
- Filament 5+
- `nwidart/laravel-modules` 13+

### Installazione

```bash
composer require greatwolf3/filament-module-generator
```

Il service provider viene registrato automaticamente da Laravel.

### Configurazione opzionale

```bash
php artisan vendor:publish --tag="filament-module-generator-config"
```

### Registrare il plugin Filament

Aggiungi il plugin al pannello Filament in cui vuoi scoprire le risorse dei moduli:

```php
use Greatwolf\FilamentModuleGenerator\Plugins\ModuleDiscoveryPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(ModuleDiscoveryPlugin::make());
}
```

Il generatore può aggiornare automaticamente il pannello scelto usando l'opzione `--panel`.

### Generare una resource di un modulo

```bash
php artisan module:filament-resource Category Prova --panel=Admin
```

Puoi usare anche il nome completo del panel provider:

```bash
php artisan module:filament-resource Category Prova --panel=AdminPanelProvider
```

Il comando esegue:

1. Creazione del modulo se non esiste.
2. Configurazione dell'autoload del modulo.
3. Registrazione di `ModuleDiscoveryPlugin` nel pannello Filament scelto.
4. Creazione del model se non esiste.
5. Creazione ed esecuzione della migration se necessario.
6. Generazione della resource Filament.
7. Spostamento della resource e delle pagine nel namespace del modulo.
8. Configurazione del raggruppamento menu standard Filament.

### Struttura generata

```text
Modules/
└── Prova/
    ├── app/
    │   └── Models/
    │       └── Category.php
    ├── database/
    │   └── migrations/
    │       └── 2026_05_14_000000_create_categories_table.php
    └── Filament/
        ├── Clusters/
        │   └── ProvaCluster.php
        └── Resources/
            ├── CategoryResource.php
            └── CategoryResource/
                └── Pages/
                    ├── ListCategories.php
                    ├── CreateCategory.php
                    └── EditCategory.php
```

### Navigazione della resource generata

Le resource generate usano le proprietà standard di Filament:

```php
protected static ?string $slug = 'prova/categories';

protected static string | UnitEnum | null $navigationGroup = 'Prova';
```

In questo modo gli URL restano modulari e il menu viene gestito normalmente da Filament.

### Gestione moduli

```bash
php artisan module:manager list
php artisan module:manager enable Prova
php artisan module:manager disable Prova
php artisan module:manager status Prova
```

### Licenza

MIT License.
