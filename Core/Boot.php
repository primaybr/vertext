<?php

/**
 * Bootstraps the application by setting up autoloading for classes.
 * This file configures the autoloading mechanism to dynamically load class files based on their namespace.
 * @package Core
 * @author  Prima Yoga
 */

set_include_path(get_include_path().PATH_SEPARATOR.'./');

// Composer's autoloader (third-party vendor/ packages) is registered FIRST,
// before the custom App/Core/Config loader below - both autoloaders simply
// return on a miss (neither throws), so PHP falls through to try the next
// registered autoloader and, ultimately, its own class-not-found handling.
// Registration order still matters so a vendor package's classes resolve via
// Composer rather than being missed entirely. Stays invisible until a
// runtime Composer dependency is actually added - confirmed missing in a
// sibling fork of this framework when a vendor package was added there and
// silently failed to resolve at runtime.
$_vtxVendorAutoload = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (file_exists($_vtxVendorAutoload)) {
    require $_vtxVendorAutoload;
}
unset($_vtxVendorAutoload);

spl_autoload_extensions('.php');

spl_autoload_register(function ($namespace_class) {
    /**
     * Autoloads the class files based on the given namespace class.
     * This function replaces the namespace separators with directory separators and appends the appropriate file extension.
     * 
     * @param string $namespace_class The fully qualified class name.
     */
    static $autoloadExtensions = null;
    if ($autoloadExtensions === null) {
        $autoloadExtensions = explode(',', spl_autoload_extensions());
    }
    $baseDir = dirname(__DIR__). DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $namespace_class);

    foreach ($autoloadExtensions as $extension) {
        $filePath = $baseDir . $extension;
        if (file_exists($filePath)) {
            require $filePath;
            return;
        }
    }
});

// Load .env into the process environment before anything (Config, helpers)
// might read from it.
Core\Env::load();

// Global helper functions (e.g. the __() translation helper)
$_vtxHelpers = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR . 'helpers.php';
if (file_exists($_vtxHelpers)) {
    require_once $_vtxHelpers;
}
unset($_vtxHelpers);