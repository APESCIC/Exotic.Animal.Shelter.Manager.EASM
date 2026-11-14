<?php

namespace App\Install;

/**
 * Backwards-compatible facade: one-shot install used by the classic form POST.
 */
class Installer
{
    public function __construct(private InstallWizard $wizard) {}

    /**
     * Persist hosting config, migrate, create the first admin, and lock the wizard.
     *
     * @param  array<string, mixed>  $input
     */
    public function install(array $input): void
    {
        $this->wizard->installAll($input);
    }
}
