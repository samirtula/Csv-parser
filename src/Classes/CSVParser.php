<?php

declare(strict_types=1);

namespace Octopus\ParserCsv\Classes;

use  \Exception;

class CSVParser
{
    private string $startFileName;
    private string $finishFileName;
    private $fileStart;
    private $fileFinish;
    private array $productsNames = [];
    private array $products = [];
    private array $productsData = [];
    private static int $bundleItemsCount = 0;
    private static int $rowsCount = 0;
    private static int $rowsWritten = 0;
    private string $titles = 'sku,store_view_code,attribute_set_code,product_type,categories,product_websites,name,description,short_description,weight,product_online,tax_class_name,visibility,price,special_price,special_price_from_date,special_price_to_date,url_key,meta_title,meta_keywords,meta_description,base_image,base_image_label,small_image,small_image_label,thumbnail_image,thumbnail_image_label,swatch_image,swatch_image_label,created_at,updated_at,new_from_date,new_to_date,display_product_options_in,map_price,msrp_price,map_enabled,gift_message_available,custom_design,custom_design_from,custom_design_to,custom_layout_update,page_layout,product_options_container,msrp_display_actual_price_type,country_of_manufacture,additional_attributes,qty,out_of_stock_qty,use_config_min_qty,is_qty_decimal,allow_backorders,use_config_backorders,min_cart_qty,use_config_min_sale_qty,max_cart_qty,use_config_max_sale_qty,is_in_stock,notify_on_stock_below,use_config_notify_stock_qty,manage_stock,use_config_manage_stock,use_config_qty_increments,qty_increments,use_config_enable_qty_inc,enable_qty_increments,is_decimal_divided,website_id,related_skus,related_position,crosssell_skus,crosssell_position,upsell_skus,upsell_position,additional_images,additional_image_labels,hide_from_product_page,custom_options,bundle_price_type,bundle_sku_type,bundle_price_view,bundle_weight_type,bundle_values,bundle_shipment_type,associated_skus,downloadable_links,downloadable_samples,configurable_variations,configurable_variation_labels';

    public function __construct(string $startFileName, string $finishFileName)
    {
        $this->startFileName = $startFileName;
        $this->finishFileName = $finishFileName;
    }

    public function openStream()
    {
        try {
            $this->fileStart = fopen($this->startFileName, 'r');

            if (!$this->fileStart) {
                throw new Exception("Не удалось подключить файл {$this->startFileName}");
            }
            $this->fileFinish = fopen($this->finishFileName, 'c');

            if (!$this->fileFinish) {
                throw new Exception("Не удалось подключить файл {$this->fileFinish}");
            }
        } catch (Exception $exception) {
            return $exception->getMessage();
        }
    }

    public function createSimpleProducts()
    {
        if ($this->fileStart) {
            while ($data = fgetcsv($this->fileStart, 1000)) {
                self::$rowsCount++;
                $this->productsData[] = $data;
                $this->products[] = $this->prepareSimple($data);
            }
            try {
                $this->writeStream(explode(',', $this->titles));
                foreach ($this->products as $product) {
                    $this->writeStream($product);
                }
            } catch (Exception $exception) {
                return $exception->getMessage();
            }
        } else {
            return "Ошибка нет файл {$this->startFileName} не открыт";
        }
    }

    private function prepareSimple(array $product): array
    {
        $name = mb_strtoupper($product[0]);
        $categories = $product[1];
        $price = $product[4];
        $nameWithoutSpace = str_replace(" ", '', $name);
        //Размеры SS
        $reg = "/[S]{2}[0-9]{2}/";
        $result = preg_match($reg, $nameWithoutSpace, $found);

        if ($result === 1) {
            $this->productsNames[] = preg_replace("/\s[S]{2}(\s)?[0-9]{2}/", '', $name);
            $size = preg_replace("/[S]{2}/", 'SS ', $found[0]);
            //Размеры без SS
        } else {
            $nameWithoutZero = str_replace(",0", '', $name);
            $nameWithoutSpace = str_replace(" ", '', $nameWithoutZero);
            //Здесь поменял на точки
            $reg = "/[0-9]{1,2}(.)?([0-9]{1})?X[0-9]{1,2}(.)?([0-9]{1})?/";
            $result = preg_match($reg, $nameWithoutSpace, $found);
            //избавляемся от буквы в аттрибуте размера в конце
            $found[0] = preg_replace("/X/", 'x', $found[0]);
            $found[0] = preg_replace("/[A-Z]/", '', $found[0]);
            $size = strtolower($found[0] . ' mm');

            if ($result === 1) {
                //Здесь поменял на точки
                $this->productsNames[] = preg_replace("/\s[0-9]{1,2}(.)?([0-9]{1})?X(\s)?[0-9]{1,2}(.)?([0-9]{1})?/", '', $nameWithoutZero);
                $name = str_replace(",", '.', $name);
            } else {
                $reg = "/[A-Z][0-9]{2}[A-Z]/";
                $result = preg_match($reg, $nameWithoutSpace, $found);
                $size = preg_replace("/[A-Z]/", '', $found[0]) . ' mm';

                if ($result === 1) {
                    $this->productsNames[] = preg_replace("/\s[0-9]{2}/", '', $nameWithoutZero);
                    $name = str_replace(",", '.', $name);
                }
            }
        }

        return [
            $name, '', 'Default', 'simple', $categories, 'base', $name, '', '', '', '1', 'Taxable Goods', 'Not Visible Individually', $price,
            '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'Block after Info Column', '', '', '', 'Use config', '', '', '', '', 'Product -- Full Width',
            '', 'Use config', '', "size={$size}", '100000.0000', '0.0000', '1', '0', '0', '1', '1.0000', '1', '10000.0000', '1', '1', '1.0000', '1', '1', '1', '1', '1.0000',
            '1', '0', '0', '0', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''
        ];
    }

    private function prepareBundle(string $bundleName, array $productArr): array
    {
        $bundleProdAttributes = [];
        $category = $productArr[0][1];
        foreach ($productArr as $productData) {
            self::$bundleItemsCount++;

            $name = mb_strtoupper($productData[0]);
            $name = str_replace(",", '.', $name);
            $pack = $productData[2];
            $packPrice = $productData[4];
            $smallPack = $productData[3];
            $smallPackPrice = $productData[5];

            if (!empty($pack) && !empty($packPrice)) {
                $bundleProdAttributes[] = "name=Pack ${pack},type=checkbox,required=0,sku=${name},price=${packPrice},default=0,default_qty=${pack},price_type=fixed,can_change_qty=0";
            }

            if (!empty($smallPack) && !empty($smallPackPrice)) {
                $bundleProdAttributes[] = "name=Pack ${smallPack},type=checkbox,required=0,sku=${name},price=${smallPackPrice},default=0,default_qty=${smallPack},price_type=fixed,can_change_qty=0";
            }
        }
        $bundleProdAttributes = implode("|", $bundleProdAttributes);

        return [
            $bundleName, '', 'Rhinestones', 'bundle', $category, 'base', $bundleName, '', '', '', '1', 'Taxable Goods', 'Catalog, Search', '0.00',
            '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'Block after Info Column', '', '', '', 'Use config', '', '', '', '', 'Product -- Full Width',
            '', 'Use config', '', '', '0.0000', '0.0000', '1', '0', '0', '1', '1.0000', '1', '10000.0000', '1', '1', '1.0000', '1', '1', '1', '1', '1.0000',
            '1', '0', '0', '0', '', '', '', '', '', '', '', '', '', '', 'fixed', 'dynamic', 'Price range', 'dynamic', $bundleProdAttributes, 'together', '', '', '', '', '',
        ];
    }

    private function isSuitable(string $bundleName, array $product)
    {
        $name = mb_strtoupper($product[0]);
        $reg = "/\s[S]{2}(\s)?[0-9]{2}/";
        $result = preg_match($reg, $name);

        if ($result === 1) {
            $name = preg_replace("/\s[S]{2}(\s)?[0-9]{2}/", '', $name);

            if ($bundleName === $name) {
                return true;
            }
        } else {
            $name = str_replace(",0", '', $name);
            $reg = "/\s[0-9]{1,2}(.)?([0-9]{1})?X(\s)?[0-9]{1,2}(.)?([0-9]{1})?/";
            $result = preg_match($reg, $name);

            if ($result === 1) {
                $name = preg_replace($reg, '', $name);

                if ($bundleName === $name) {
                    return true;
                }
            } else {
                $reg = "/\s[0-9]{2}/";
                $result = preg_match($reg, $name);

                if ($result === 1) {
                    $name = preg_replace($reg, '', $name);

                    if ($bundleName === $name) {
                        return true;
                    }
                }
            }
        }

        return "Ошибка не прошел проверку на регулярные выражения";
    }

    /**
     * @return string|void
     */
    public function createBundleProduct()
    {
        if ($this->fileStart) {
            $bundleProductsList = $this->getBundleProductsList();
            $relatedProducts = [];
            $bundleArr = [];
            foreach ($bundleProductsList as $bundleName) {
                foreach ($this->productsData as $product) {
                    if ($this->isSuitable($bundleName, $product) === true) {
                        $relatedProducts[$bundleName][] = $product;
                    }
                }
            }
            foreach ($relatedProducts as $bundleName => $data) {
                $bundleArr[] = $this->prepareBundle($bundleName, $data);
            }
            try {
                foreach ($bundleArr as $bundle) {
                    $this->writeStream($bundle);
                }
            } catch (Exception $exception) {
                return $exception->getMessage();
            }
        }
    }

    private function getBundleProductsList(): array
    {
        return array_unique($this->productsNames, SORT_REGULAR);
    }

    public function writeStream(array $data): void
    {
        $result = fputcsv($this->fileFinish, $data);

        if ($result) {
            self::$rowsWritten++;
        } else {
            throw new Exception("Запись в файл {$this->finishFileName} не удалась");
        }
    }

    public function getReport()
    {
        echo 'Количество записей ' . self::$rowsCount . '<br>';
        echo 'Количество бандл продуктов ' . count(array_unique($this->productsNames, SORT_REGULAR)) . '<br>';
        echo 'Количество элементов для бандл ' . self::$bundleItemsCount . '<br>';
        echo 'Количество записанных строк с учетом строки title ' . self::$rowsWritten . '<br>';
    }

    public function __destruct()
    {
        fclose($this->fileStart);
        fclose($this->fileFinish);
    }

}