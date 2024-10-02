<?php

// Directorios donde se buscarán los archivos, dos niveles por encima
$baseDir = realpath(__DIR__ . '/../../');
$searchDirs = [
    $baseDir . '/public',
    $baseDir . '/src'
];

// Archivo PHP con las claves
$phpFile = __DIR__ . '/en/translations.php';

// Extensiones de archivos a buscar
$extensions = ['php', 'js', 'phtml'];

// Verificar que el archivo de claves exista
if (!file_exists($phpFile)) {
    die("El archivo de claves no se ha encontrado: $phpFile\n");
}

// Incluir el archivo de claves y obtener las claves del array
$keysArray = include $phpFile;
$keys = array_keys($keysArray);

// Función para buscar una clave en archivos, excluyendo la carpeta i18n y sus subcarpetas
function searchKeyInFiles($key, $directories, $extensions) {
    $found = false;
    $foundLocations = []; // Para almacenar las ubicaciones donde se encuentra la clave

    foreach ($directories as $directory) {
        // Recorrer recursivamente los archivos del directorio
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            // Excluir archivos dentro de cualquier carpeta que contenga "i18n" en la ruta
            if (strpos($file->getPathname(), DIRECTORY_SEPARATOR . 'i18n' . DIRECTORY_SEPARATOR) !== false) {
                continue; // Saltar archivos dentro de la carpeta i18n y sus subcarpetas
            }

            // Solo buscar en archivos con las extensiones indicadas
            if (in_array(pathinfo($file, PATHINFO_EXTENSION), $extensions)) {
                // Leer el contenido del archivo
                $content = file($file);

                // Buscar la clave de manera insensible a mayúsculas
                foreach ($content as $lineNumber => $lineContent) {
                    if (stripos($lineContent, $key) !== false) {
                        $found = true;
                        $foundLocations[] = $file->getPathname() . " (línea " . ($lineNumber + 1) . ")";
                        break;
                    }
                }
            }
        }

        if ($found) {
            break; // Salir si la clave fue encontrada en algún archivo
        }
    }

    return $found ? $foundLocations : false;
}

// Listar las claves que no se encontraron
$notFoundKeys = [];
$foundKeys = [];

// Archivo de salida para las claves no encontradas
$outputFile = __DIR__ . '/not_found_keys.txt';

// Archivo de salida para las claves encontradas
$foundFile = __DIR__ . '/found_keys.txt';

// Borrar los archivos de salida si ya existen
if (file_exists($outputFile)) {
    unlink($outputFile);
}
if (file_exists($foundFile)) {
    unlink($foundFile);
}

// Buscar claves en los archivos
foreach ($keys as $key) {
    $foundLocations = searchKeyInFiles($key, $searchDirs, $extensions);
    if ($foundLocations === false) {
        $notFoundKeys[] = $key;
    } else {
        // Registrar dónde se encontró la clave
        $foundKeys[] = ['key' => $key, 'locations' => $foundLocations];
    }
}

// Escribir claves no encontradas en archivo
if (!empty($notFoundKeys)) {
    file_put_contents($outputFile, "Las siguientes claves no se encontraron:\n", FILE_APPEND);
    foreach ($notFoundKeys as $notFoundKey) {
        file_put_contents($outputFile, "- $notFoundKey\n", FILE_APPEND);
    }
    echo "Claves no encontradas guardadas en $outputFile\n";
} else {
    echo "Todas las claves fueron encontradas en los archivos.\n";
}

// Escribir claves encontradas y sus ubicaciones en archivo
if (!empty($foundKeys)) {
    file_put_contents($foundFile, "Las siguientes claves se encontraron en los archivos:\n", FILE_APPEND);
    foreach ($foundKeys as $foundKey) {
        file_put_contents($foundFile, "- " . $foundKey['key'] . " encontrada en:\n", FILE_APPEND);
        foreach ($foundKey['locations'] as $location) {
            file_put_contents($foundFile, "    - $location\n", FILE_APPEND);
        }
    }
    echo "Claves encontradas guardadas en $foundFile\n";
}
