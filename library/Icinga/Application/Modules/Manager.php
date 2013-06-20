<?php

namespace Icinga\Application\Modules;

use Icinga\Application\ApplicationBootstrap;
use Icinga\Data\ArrayDatasource;
use Icinga\Web\Notification;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\SystemPermissionException;

// TODO: show whether enabling/disabling modules is allowed by checking enableDir
//       perms

class Manager
{
    protected $installedBaseDirs;
    protected $enabledDirs       = array();
    protected $loadedModules     = array();
    protected $index;
    protected $app;

    protected $enableDir;

    public function __construct(ApplicationBootstrap $app, $dir = null)
    {
        $this->app = $app;
        if ($dir === null) {
            $dir = $this->app->getConfig()->getConfigDir()
                         . '/enabledModules';
        }
        $this->prepareEssentials($dir);
        $this->detectEnabledModules();
    }

    protected function prepareEssentials($moduleDir)
    {
        $this->enableDir = $moduleDir;

        if (! file_exists($this->enableDir) || ! is_dir($this->enableDir)) {
            throw new ProgrammingError(
                sprintf(
                    'Missing module directory: %s',
                    $this->enableDir
                )
            );
        }
    }

    protected function detectEnabledModules()
    {
        $fh = opendir($this->enableDir);

        while (false !== ($file = readdir($fh))) {

            if ($file[0] === '.') {
                continue;
            }

            $link = $this->enableDir . '/' . $file;
            if (! is_link($link)) {
                continue;
            }

            $dir = realpath($link);
            if (! file_exists($dir) || ! is_dir($dir)) {
                continue;
            }

            $this->enabledDirs[$file] = $dir;
        }
    }

    public function loadEnabledModules()
    {
        foreach ($this->listEnabledModules() as $name) {
            $this->loadModule($name);
        }
        return $this;
    }

    public function loadModule($name)
    {
        if ($this->hasLoaded($name)) {
            return $this;
        }
        $module = new Module($this->app, $name, $this->getModuleDir($name));
        $module->register();
        $this->loadedModules[$name] = $module;
        return $this;
    }

    public function enableModule($name)
    {
        if (! $this->hasInstalled($name)) {
            throw new ConfigurationError(
                sprintf(
                    "Cannot enable module '%s' as it isn't installed",
                    $name
                )
            );
            return $this;
        }
        $target = $this->installedBaseDirs[$name];
        $link = $this->enableDir . '/' . $name;
        if (! is_writable($this->enableDir)) {
            throw new SystemPermissionException(
                "Insufficient system permissions for enabling modules",
                "write",
                $this->enableDir
            );
        }
        if (file_exists($link) && is_link($link)) {
            return $this;
        }
        if (!@symlink($target, $link)) {
            $error = error_get_last();
            if (strstr($error["message"], "File exists") === false) {
                throw new SystemPermissionException($error["message"], "symlink", $link);
            }
        }
        return $this;
    }

    public function disableModule($name)
    {
        if (! $this->hasEnabled($name)) {
            return $this;
        }
        if (! is_writable($this->enableDir)) {
            throw new SystemPermissionException("Can't write the module directory", "write", $this->enableDir);
            return $this;
        }
        $link = $this->enableDir . '/' . $name;
        if (!file_exists($link)) {
            throw new ConfigurationError("The module $name could not be found, can't disable it");
        }
        if (!is_link($link)) {
            throw new ConfigurationError(
                "The module $name can't be disabled as this would delete the whole module. ".
                "It looks like you have installed this module manually and moved it to your module folder.".
                "In order to dynamically enable and disable modules, you have to create a symlink to ".
                "the enabled_modules folder"
            );
        }
            
        if (file_exists($link) && is_link($link)) {
            if (!@unlink($link)) {
                $error = error_get_last();
                throw new SystemPermissionException($error["message"], "unlink", $link);
            }
        } else {

        }
        return $this;
    }

    public function getModuleConfigDir($name)
    {
        return $this->getModuleDir($name, '/config');
    }

    public function getModuleDir($name, $subdir = '')
    {
        if ($this->hasEnabled($name)) {
            return $this->enabledDirs[$name]. $subdir;
        }

        if ($this->hasInstalled($name)) {
            return $this->installedBaseDirs[$name] . $subdir;
        }

        throw new ProgrammingError(
            sprintf(
                'Trying to access uninstalled module dir: %s',
                $name
            )
        );
    }

    public function hasInstalled($name)
    {
        if ($this->installedBaseDirs === null) {
            $this->detectInstalledModules();
        }
        return array_key_exists($name, $this->installedBaseDirs);
    }

    public function hasEnabled($name)
    {
        return array_key_exists($name, $this->enabledDirs);
    }

    public function hasLoaded($name)
    {
        return array_key_exists($name, $this->loadedModules);
    }

    public function getLoadedModules()
    {
        return $this->loadedModules;
    }

    public function getModule($name)
    {
        if (! $this->hasLoaded($name)) {
            throw new ProgrammingError(
                sprintf(
                    'Cannot access module %s as it hasn\'t been loaded',
                    $name
                )
            );
        }
        return $this->loadedModules[$name];
    }

    public function getModuleInfo()
    {
        $installed = $this->listInstalledModules();
        $info = array();
        foreach ($installed as $name) {
            $info[] = (object) array(
                'name'    => $name,
                'path'    => $this->installedBaseDirs[$name],
                'enabled' => $this->hasEnabled($name),
                'loaded'  => $this->hasLoaded($name)
            );
        }
        return $info;
    }

    public function select()
    {
        $ds = new ArrayDatasource($this->getModuleInfo());
        return $ds->select();
    }

    public function listEnabledModules()
    {
        return array_keys($this->enabledDirs);
    }

    public function listLoadedModules()
    {
        return array_keys($this->loadedModules);
    }

    public function listInstalledModules()
    {
        if ($this->installedBaseDirs === null) {
            $this->detectInstalledModules();
        }
        return array_keys($this->installedBaseDirs);
    }

    public function detectInstalledModules()
    {
        // TODO: Allow multiple paths for installed modules (e.g. web vs pkg)
        $basedir = realpath(ICINGA_APPDIR . '/../modules');
        $fh = @opendir($basedir);
        if ($fh === false) {
            return $this;
        }

        while ($name = readdir($fh)) {
            if ($name[0] === '.') {
                continue;
            }
            if (is_dir($basedir . '/' . $name)) {
                $this->installedBaseDirs[$name] = $basedir . '/' . $name;
            }
        }
    }
}
