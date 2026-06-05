<?php
$html = file_get_contents('input.html');

libxml_use_internal_errors(true);
$dom = new DOMDocument();

// важное для UTF-8 (см. пункт 2)
$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);

$xpath = new DOMXPath($dom);
$options = $xpath->query('//select[@id="category"]//option');

$result = [];
$dups = [];

foreach ($options as $option) {
    $value = trim($option->getAttribute('value'));
    $text  = trim($option->textContent);

    if (array_key_exists($value, $result)) {
        $dups[$value][] = ['prev' => $result[$value], 'new' => $text];
        // реши сам: оставлять первый или последний
        // continue; // оставить первый
    }

    $result[$value] = $text; // оставляет последний
}

if ($dups) {
    fwrite(STDERR, "DUPLICATE values detected:\n");
    foreach ($dups as $v => $list) {
        fwrite(STDERR, "  [$v] occurrences=".(count($list)+1)."\n");
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

