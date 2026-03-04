<?php
$html = file_get_contents('input.html');

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML($html);

$xpath = new DOMXPath($dom);

echo "len(html) = ".strlen($html).PHP_EOL;
echo "option(any) = ".$xpath->query('//option')->length.PHP_EOL;
echo "select#category = ".$xpath->query('//select[@id="category"]')->length.PHP_EOL;
echo "option(in select#category) = ".$xpath->query('//select[@id="category"]/option')->length.PHP_EOL;

$firstSelect = $xpath->query('//select')->item(0);
if ($firstSelect) echo "first select id = ".$firstSelect->getAttribute('id').PHP_EOL;

foreach (libxml_get_errors() as $e) echo "LIBXML: ".$e->message;
libxml_clear_errors();


?>