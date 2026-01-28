<?php
// 1. Указываем путь к папке с текстовыми файлами
$directory = '/home/easyt/web/wwwocr_samples/'; // Убедитесь, что папка 'texts' существует и находится рядом со скриптом

// 2. Проверяем, существует ли каталог
if (is_dir($directory)) {
    // Получаем список всех файлов и директорий в папке
    $files = scandir($directory);

    echo '<h1>Содержимое текстовых файлов:</h1>';

    // 3. Проходим по каждому элементу в каталоге
    foreach ($files as $file) {
        // 4. Исключаем '.' и '..' (текущий и родительский каталог)
        if ($file !== '.' && $file !== '..') {
            $filePath = $directory . $file;

            // 5. Проверяем, является ли элемент файлом и имеет ли он расширение .txt
            if (is_file($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'txt') {
                // 6. Читаем содержимое файла
                $content = file_get_contents($filePath);
                
                // 7. Выводим название файла и его содержимое
                echo '<h2>Файл: ' . htmlspecialchars($file) . '</h2>';
                // Используем nl2br для преобразования переносов строк в <br> в HTML
                echo '<pre>' . nl2br(htmlspecialchars($content)) . '</pre>';
                echo '<hr>'; // Разделитель
            }
        }
    }
} else {
    echo 'Каталог не найден: ' . $directory;
}
?>