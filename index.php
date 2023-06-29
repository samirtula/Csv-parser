<?php
//Первым делом подготовить файл который будет обрабатываться, Вписать категории. Заменить запятые в колонке имени на точки.
//Если при импорте magento2 будет ругаться на дубль primary_key проверь дубли в simple products.

declare(strict_types=1);

include_once __DIR__ . '/vendor/autoload.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Octopus\ParserCsv\Classes\CSVParser;

try {
    $parser = new CSVParser('list.csv', 'result_good.csv');
    $parser->openStream();
    $parser->createSimpleProducts();
    $parser->createBundleProduct();
    $parser->getReport();
} catch (Exception $e) {
    echo $e->getMessage();
}
?>


