<?php

namespace Greatwolf\FilamentModuleGenerator\Commands;

use Illuminate\Console\Command;
use Nwidart\Modules\Facades\Module;
use Illuminate\Support\Facades\Artisan;

class ModuleManager extends Command
{
    protected $signature = 'module:manager {action} {name?}';
    protected $description = 'Gestione moduli: list, enable, disable, status';

    public function handle()
    {
        $action = $this->argument('action');
        $name = $this->argument('name');

        switch ($action) {
            case 'list':
                $this->listModules();
                break;
            case 'enable':
                if (!$name) {
                    $this->error('❌ Specifica il nome del modulo da abilitare: module:manager enable <ModuleName>');
                    return 1;
                }
                $this->enableModule($name);
                break;
            case 'disable':
                if (!$name) {
                    $this->error('❌ Specifica il nome del modulo da disabilitare: module:manager disable <ModuleName>');
                    return 1;
                }
                $this->disableModule($name);
                break;
            case 'status':
                if (!$name) {
                    $this->error('❌ Specifica il nome del modulo: module:manager status <ModuleName>');
                    return 1;
                }
                $this->showStatus($name);
                break;
            default:
                $this->error('❌ Azione non valida. Azioni disponibili: list, enable, disable, status');
                return 1;
        }

        return 0;
    }

    protected function listModules()
    {
        $this->info('📋 Lista Moduli:');
        $this->info(str_repeat('=', 80));

        $modules = Module::all();

        if (empty($modules)) {
            $this->warn('⚠️ Nessun modulo trovato.');
            return;
        }

        foreach ($modules as $module) {
            $status = $module->isEnabled() ? '✅ Enabled' : '❌ Disabled';
            $path = $module->getPath();
            $priority = $module->getPriority();

            $this->line("  [{$status}] {$module->getName()} - Priority: {$priority}");
            $this->line("    📁 Path: {$path}");
            $this->line('');
        }
    }

    protected function enableModule($name)
    {
        if (!Module::has($name)) {
            $this->error("❌ Il modulo '{$name}' non esiste.");
            return;
        }

        $module = Module::findOrFail($name);

        if ($module->isEnabled()) {
            $this->warn("⚠️ Il modulo '{$name}' è già abilitato.");
            return;
        }

        try {
            Artisan::call('module:enable', ['module' => $name]);
            $this->info("✅ Modulo '{$name}' abilitato con successo.");
            $this->info("🔄 Ricarica il pannello Filament per vedere le modifiche.");
        } catch (\Exception $e) {
            $this->error("❌ Errore durante l'abilitazione del modulo: " . $e->getMessage());
        }
    }

    protected function disableModule($name)
    {
        if (!Module::has($name)) {
            $this->error("❌ Il modulo '{$name}' non esiste.");
            return;
        }

        $module = Module::findOrFail($name);

        if (!$module->isEnabled()) {
            $this->warn("⚠️ Il modulo '{$name}' è già disabilitato.");
            return;
        }

        try {
            Artisan::call('module:disable', ['module' => $name]);
            $this->info("✅ Modulo '{$name}' disabilitato con successo.");
            $this->info("🔄 Ricarica il pannello Filament per vedere le modifiche.");
            $this->info("ℹ️ Il modulo è stato rimosso dal menu ma i file sono conservati.");
        } catch (\Exception $e) {
            $this->error("❌ Errore durante la disabilitazione del modulo: " . $e->getMessage());
        }
    }

    protected function showStatus($name)
    {
        if (!Module::has($name)) {
            $this->error("❌ Il modulo '{$name}' non esiste.");
            return;
        }

        $module = Module::findOrFail($name);

        $this->info("📊 Status Modulo: {$name}");
        $this->info(str_repeat('=', 50));

        $status = $module->isEnabled() ? '✅ Enabled' : '❌ Disabled';
        $this->line("Status: {$status}");
        $this->line("Nome: {$module->getName()}");
        $this->line("Alias: {$module->getLowerName()}");
        $this->line("Priorità: {$module->getPriority()}");
        $this->line("Path: {$module->getPath()}");
        $this->line("Versione: {$module->get('version', 'N/A')}");
        $this->line("Descrizione: {$module->get('description', 'N/A')}");

        // Mostra risorse Filament se abilitato
        if ($module->isEnabled()) {
            $filamentPath = $module->getPath() . '/Filament/Resources';
            if (is_dir($filamentPath)) {
                $resources = glob($filamentPath . '/*Resource.php');
                if (!empty($resources)) {
                    $this->line("Risorse Filament:");
                    foreach ($resources as $resource) {
                        $resourceName = basename($resource, '.php');
                        $this->line("  📄 {$resourceName}");
                    }
                }
            }
        }
    }
}
