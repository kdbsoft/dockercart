<?php
declare(strict_types=1);

/**
 * ProductStockSorter — единое правило «товар не в наличии» и стабильная
 * сортировка листингов: товары без наличия (quantity <= 0, без предзаказа,
 * и без живых вариантов у конфигурируемых) уходят в конец списка.
 *
 * Правило зеркалит логику контроллеров витрины (product.php:430,
 * category.php:349-381): в наличии = quantity > 0 ИЛИ preorder; для
 * конфигурируемых товаров дополнительно в наличии, если variants_in_stock > 0.
 *
 * Класс чистый (без registry), чтобы его можно было тестировать без БД.
 */
class ProductStockSorter
{
    /**
     * Проверяет, считается ли товар «не в наличии» для сортировки листингов.
     *
     * @param array $product строка товара (quantity, preorder, is_configurable,
     *                       variants_in_stock)
     * @return bool
     */
    public static function isOutOfStock(array $product): bool
    {
        $quantity = (float)($product['quantity'] ?? 0);

        if ($quantity > 0 || !empty($product['preorder'])) {
            return false;
        }

        // Конфигурируемый товар с живыми вариантами считается в наличии,
        // даже если количество дефолтного варианта = 0.
        if (!empty($product['is_configurable'])) {
            return (int)($product['variants_in_stock'] ?? 0) === 0;
        }

        return true;
    }

    /**
     * Стабильная сортировка: товары в наличии — в начале, не в наличии — в
     * конце, с сохранением исходного порядка внутри обеих групп и ключей.
     *
     * @param array $products map [product_id => данные товара]
     * @return array
     */
    public static function sort(array $products): array
    {
        $in = [];
        $out = [];

        foreach ($products as $key => $product) {
            if (self::isOutOfStock($product)) {
                $out[$key] = $product;
            } else {
                $in[$key] = $product;
            }
        }

        return $in + $out;
    }
}
