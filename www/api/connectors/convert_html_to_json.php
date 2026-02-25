<?php

$html = file_get_contents('input.html');

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML($html);

$xpath = new DOMXPath($dom);
$options = $xpath->query('//select[@id="category"]/option');

$result = [];

foreach ($options as $option) {
    $value = trim($option->getAttribute('value'));
    $text  = trim($option->textContent);

    $result[$value] = $text;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);