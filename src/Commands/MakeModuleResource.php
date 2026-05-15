@php artisan package:discover --ansi

   Symfony\Component\ErrorHandler\Error\FatalError

  Cannot redeclare class Greatwolf\FilamentModuleGenerator\Composer\CleanupPanelProviders (previously declared in C:\laragonzo6\www\ProvaModulo\vendor\greatwolf3\filament-module-generator\src\Commands\MakeModuleResource.php:7)

  at vendor\greatwolf3\filament-module-generator\src\Commands\MakeModuleResource.php:7
      3▕ namespace Greatwolf\FilamentModuleGenerator\Composer;
      4▕
      5▕ use Composer\Script\Event;
      6▕
  ➜   7▕ class CleanupPanelProviders
      8▕ {
      9▕     public static function prePackageUninstall(Event $event): void
     10▕     {
     11▕         $operation = $event->getOperation();


   Whoops\Exception\ErrorException

  Cannot redeclare class Greatwolf\FilamentModuleGenerator\Composer\CleanupPanelProviders (previously declared in C:\laragonzo6\www\ProvaModulo\vendor\greatwolf3\filament-module-generator\src\Commands\MakeModuleResource.php:7)

  at vendor\greatwolf3\filament-module-generator\src\Commands\MakeModuleResource.php:7
      3▕ namespace Greatwolf\FilamentModuleGenerator\Composer;
      4▕
      5▕ use Composer\Script\Event;
      6▕
  ➜   7▕ class CleanupPanelProviders
      8▕ {
      9▕     public static function prePackageUninstall(Event $event): void
     10▕     {
     11▕         $operation = $event->getOperation();

  1   vendor\filp\whoops\src\Whoops\Run.php:520
      Whoops\Run::handleError("Cannot redeclare class Greatwolf\FilamentModuleGenerator\Composer\CleanupPanelProviders (previously declared in C:\laragonzo6\www\ProvaModulo\vendor\greatwolf3\filament-module-generator\src\Commands\MakeModuleResource.php:7)", "C:\laragonzo6\www\ProvaModulo\vendor\greatwolf3\filament-module-generator\src\Commands\MakeModuleResource.php")

  2   [internal]:0
      Whoops\Run::handleShutdown()

Script @php artisan package:discover --ansi handling the post-autoload-dump event returned with error code 255
