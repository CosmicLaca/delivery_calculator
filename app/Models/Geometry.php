<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Geometry extends Model {

    private float $storeCost = 0.15;
    private int $targetCost = 1;

    /**
     * Distance between 2 POINT(x y) WKT
     * @param $pointGeom_1 // WKT point(x y);
     * @param $pointGeom_2 // WKT point(x y);
     * @param $unit
     * @return float
     */
    public function getDistance($pointGeom_1, $pointGeom_2, $unit = 'K') {
        preg_match('/^([^\(]*)([\(]*)([^A-Za-z]*[^\)$])([\)]*[^,])$/', $pointGeom_1, $Match);
        $LanLotCoords = explode(' ', $Match[3]);
        $lat1 = $LanLotCoords[1];
        $lon1 = $LanLotCoords[0];

        preg_match('/^([^\(]*)([\(]*)([^A-Za-z]*[^\)$])([\)]*[^,])$/', $pointGeom_2, $Match);
        $LanLotCoords = explode(' ', $Match[3]);
        $lat2 = $LanLotCoords[1];
        $lon2 = $LanLotCoords[0];

        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        $unit = strtoupper($unit);

        if ($unit == "K") {
            return ($miles * 1.609344);
        } else if ($unit == "N") {
            return ($miles * 0.8684);
        } else if ($unit == "M") {
            return ($miles * 1609.344);
        } else {
            return $miles;
        }
    }

    /**
     * Modify input data
     * @param $combined
     * @param $stores
     * @param $target
     * @return array
     */
    public function setStoreData($combined, $stores, $target) {
        foreach ($combined as $storeData) {
            $coord = [];

            $storeKey_1 = reset($storeData);
            $storeKey_2 = end($storeData);
            $coord[$storeKey_1] = $stores[$storeKey_1]['coordinate'];
            $coord[$storeKey_2] = $stores[$storeKey_2]['coordinate'];

            $distance = round($this->getDistance(reset($coord), end($coord), 'M'));
            $targetDistance = round($this->getDistance(reset($coord), $target['coordinate'], 'M'));

            $stores[$storeKey_1]['distance']['target'] = $targetDistance;
            $stores[$storeKey_1]['cost']['target'] = round((($targetDistance / 1000) * $this->targetCost), 2);

            $targetDistance = round($this->getDistance(end($coord), $target['coordinate'], 'M'));
            $stores[$storeKey_2]['distance']['target'] = $targetDistance;
            $stores[$storeKey_2]['cost']['target'] = round((($targetDistance / 1000) * $this->targetCost), 2);

            $stores[$storeKey_1]['distance'][$storeKey_2] = $distance;
            $stores[$storeKey_2]['distance'][$storeKey_1] = $distance;

            $stores[$storeKey_1]['cost'][$storeKey_2] = (($distance / 1000) * $this->storeCost);
            $stores[$storeKey_2]['cost'][$storeKey_1] = (($distance / 1000) * $this->storeCost);
        }

        return $stores;
    }

    /**
     * Calculate distance and cost
     * @param $stores
     * @param $requiredQTY
     * @param $storeKeys
     * @param $folowQueue
     * @return array
     */
    public function getRoute($stores, $requiredQTY, $storeKeys, $folowQueue = FALSE) {
        $availableQTY = 0;
        $deliveryCost = 0;
        $distance = 0;
        $step = 0;
        $usedStores = [];
        $sortedStoreData = $stores;
        $lastKey = array_key_first($sortedStoreData);

        foreach ($stores AS $sortedKey => $sortedStore) {
            $availableQTY += $sortedStore['stock'];
            $step++;
            //var_dump('step: ' . $step . ' key: ' . $sortedKey . ' lastkey: ' . $lastKey);
            //var_dump('qty: ' . $availableQTY);

            if ($step == 1) {
                $addCost = $sortedStoreData[$sortedKey]['cost']['target'];
                $addDistance = $sortedStoreData[$sortedKey]['distance']['target'];
                $intersect = array_intersect_key($sortedStore['cost'], array_flip($storeKeys));
                $lastKey = $sortedKey;
            } else {
                asort($intersect);
                if ($folowQueue === FALSE) {
                    $nextKey = array_key_first($intersect);
                } else {
                    $nextKey = $sortedKey;
                }
                $addCost = $sortedStoreData[$nextKey]['cost'][$lastKey];
                $addDistance = $sortedStoreData[$nextKey]['distance'][$lastKey];
                $intersect = array_intersect_key($sortedStoreData[$nextKey]['cost'], array_flip($storeKeys));
                $lastKey = $nextKey;
            }
            $usedStores[] = $lastKey;
            $intersect = array_diff_key($intersect, array_flip($usedStores));
            //var_dump('cost: ' . $addCost . ' distance: ' . $addDistance);

            $route['transport'][] = $stores[$lastKey]['name'];
            $deliveryCost += $addCost;
            $distance += $addDistance;
            if ($availableQTY >= $requiredQTY) {
                break;
            }
        }

        $route['transport'][] = 'target';
        $route['cost'] = $deliveryCost;
        $route['distance'] = $distance;
        $route['stores'] = $step;

        return $route;
    }

}
