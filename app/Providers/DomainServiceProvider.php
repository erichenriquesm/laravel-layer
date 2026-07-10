<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ($this->discoverDomainProviders() as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * Every domain owns its bindings in domain/<Domain>/Providers, so no central file
     * has to grow a line per binding.
     *
     * @return array<int, class-string<ServiceProvider>>
     */
    private function discoverDomainProviders(): array
    {
        $pattern = base_path('domain/*/Providers/*ServiceProvider.php');

        return array_map(
            fn (string $path): string => $this->classFromPath($path),
            glob($pattern) ?: []
        );
    }

    private function classFromPath(string $path): string
    {
        $relative = str_replace(base_path('domain') . DIRECTORY_SEPARATOR, '', $path);
        $relative = substr($relative, 0, -strlen('.php'));

        return 'Domain\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
    }
}
