<?php
// Autoloader manual para PDF Parser
spl_autoload_register(function ($class) {
    // Base directory for the Smalot\PdfParser namespace
    $base_dir = __DIR__ . '/smalot/pdfparser/src/';
    
    // If the class is not from our namespace, return
    if (strpos($class, 'Smalot\\PdfParser\\') !== 0) {
        return;
    }
    
    // Remove namespace prefix
    $relative_class = substr($class, strlen('Smalot\\PdfParser\\'));
    
    // Convert namespace separators to directory separators
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Also try to load from src/Smalot/PdfParser structure
spl_autoload_register(function ($class) {
    $base_dir = __DIR__ . '/smalot/pdfparser/src/Smalot/PdfParser/';
    
    if (strpos($class, 'Smalot\\PdfParser\\') !== 0) {
        return;
    }
    
    $relative_class = substr($class, strlen('Smalot\\PdfParser\\'));
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});