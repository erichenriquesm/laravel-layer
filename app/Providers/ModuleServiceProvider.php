<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ($this->discoverModuleProviders() as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * Every module owns its bindings in modules/<Module>/Providers, so no central file
     * has to grow a line per binding.
     *
     * @return array<int, class-string<ServiceProvider>>
     */
    private function discoverModuleProviders(): array
    {
        $pattern = base_path('modules/*/Providers/*ServiceProvider.php');

        return array_map(
            fn (string $path): string => $this->classFromPath($path),
            glob($pattern) ?: []
        );
    }

    private function classFromPath(string $path): string
    {
        $relative = str_replace(base_path('modules') . DIRECTORY_SEPARATOR, '', $path);
        $relative = substr($relative, 0, -strlen('.php'));

        return 'Modules\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
    }
}
