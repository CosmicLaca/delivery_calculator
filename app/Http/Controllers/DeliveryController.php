<?php

namespace App\Http\Controllers;

use drupol\phpermutations\Generators\Combinations;
use App\Models\Geometry;

class DeliveryController extends Controller {

    /**
     * @return void
     */
    public function index() {
        var_dump('Delivery start...');

        $stores['a'] = ['coordinate' => 'POINT(20.7916584 48.10433578)', 'stock' => 6, 'name' => 'stock 1']; // Miskolc
        $stores['b'] = ['coordinate' => 'POINT(19.10762405 47.48395157)', 'stock' => 4, 'name' => 'stock 2']; // Budapest
        $stores['c'] = ['coordinate' => 'POINT(21.62521362 47.53334045)', 'stock' => 6, 'name' => 'stock 3']; // Debrecen
        $stores['d'] = ['coordinate' => 'POINT(20.41690254 47.7508049)', 'stock' => 3, 'name' => 'stock 4']; // Füzesabony
        $stores['e'] = ['coordinate' => 'POINT(19.91471863 47.50061798)', 'stock' => 1, 'name' => 'stock 5']; // Jászberény

        $target = ['coordinate' => 'POINT(20.19408607 47.17523193)', 'order' => 15]; // Szolnok
        echo 'Stores:';
        var_dump($stores);

        echo 'Target address:';
        var_dump($target);

        $requiredQTY = $target['order'];
        $availableQTY = 0;
        foreach ($stores AS $storeKey => $sortedStore) {
            $availableQTY += $sortedStore['stock'];
            if ($sortedStore['stock'] === 0) {
                unset($stores[$storeKey]);
            }
        }

        $result = [];
        if ($availableQTY >= $requiredQTY) {
            $storeKeys = array_keys($stores);
            $combinations = new Combinations($storeKeys, 2);
            $combined = $combinations->toArray();

            $geometry = new Geometry();
            $stores = $geometry->setStoreData($combined, $stores, $target);

            array_multisort(array_map(function($element) {
                return $element['cost']['target'];
            }, $stores), SORT_ASC, SORT_NUMERIC , $stores);
            $storesByTargetDistance = $stores;

            $route = $geometry->getRoute($stores, $requiredQTY, $storeKeys);
            $result['closest_stocks'] = $route;
            echo "Shortest routes:";
            var_dump($route);

            array_multisort(array_map(function($element) {
                return $element['stock'];
            }, $stores), SORT_DESC, SORT_NUMERIC , $stores);
            $storesByStocks = $stores;

           $route = $geometry->getRoute($stores, $requiredQTY, $storeKeys, TRUE);
            $result['less_stock'] = $route;
            echo "Less stocks:";
            var_dump($route);

            $closestStoreKey = array_key_first($storesByTargetDistance);
            $storesByStocksTargetFirst = array_merge([$closestStoreKey => $storesByTargetDistance[$closestStoreKey]], $storesByStocks);

            $route = $geometry->getRoute($storesByStocksTargetFirst, $requiredQTY, $storeKeys, TRUE);
            $result['less_stock_closest_target'] = $route;
            echo "Less stocks, the first closest to target:";
            var_dump($route);

            $requiredStores = [];
            $availableQTY = 0;
            $storeKeys = [];
            foreach ($storesByStocks AS $stockKey => $stockData) {
                $availableQTY += $stockData['stock'];
                $storeKeys[] = $stockKey;
                $requiredStores[$stockKey] = $stockData;
                if ($availableQTY >= $requiredQTY) {
                    break;
                }
            }

            array_multisort(array_map(function($element) {
                return $element['cost']['target'];
            }, $requiredStores), SORT_ASC, SORT_NUMERIC , $requiredStores);

            $route = $geometry->getRoute($requiredStores, $requiredQTY, $storeKeys);
            $result['less_stock_closest_stocks'] = $route;
            echo "Cheapest lines, less stocks:";
            var_dump($route);

            $requiredStores = [];
            $availableQTY = $storesByStocks[$closestStoreKey]['stock'];
            $storeKeys = [];
            $storeKeys[] = $closestStoreKey;
            $requiredStores[$closestStoreKey] = $storesByStocks[$closestStoreKey];
            foreach ($storesByStocks AS $stockKey => $stockData) {
                if ($closestStoreKey != $stockKey) {
                    $availableQTY += $stockData['stock'];
                    $storeKeys[] = $stockKey;
                    $requiredStores[$stockKey] = $stockData;
                    if ($availableQTY >= $requiredQTY) {
                        break;
                    }
                }
            }

            $route = $geometry->getRoute($requiredStores, $requiredQTY, $storeKeys);
            $result['less_stock_closest_stocks_closest_target'] = $route;
            echo "Cheapest lines, lest stocks, first closest to target:";
            var_dump($route);

            echo 'All in one:';
            var_dump($result);

            array_multisort(array_map(function($element) {
                return $element['cost'];
            }, $result), SORT_ASC, SORT_NUMERIC , $result);

            echo 'All in one, sorted:';
            var_dump($result);

            echo 'Cheapest:';
            var_dump(reset($result));

        } else {
            var_dump('Cannot deliver.');
        }
    }
}
