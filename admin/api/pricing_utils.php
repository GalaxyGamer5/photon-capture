<?php
// admin/api/pricing_utils.php

function calculateOrderPrice($order, $pricing) {
    $pkgId = $order['package'] ?? '';
    $pkg = null;
    foreach ($pricing['packages'] as $p) {
        if ($p['id'] === $pkgId) {
            $pkg = $p;
            break;
        }
    }

    if (!$pkg) return $order['price']; // Keep existing if package not found

    $basePrice = (float)$pkg['price'];
    $hours = (float)($order['hours'] ?? 1);

    if ($pkgId === 'event') {
        $total = $basePrice * $hours;
    } else {
        $total = $basePrice;
    }

    // Add Extras
    $extrasTotal = 0;
    $selectedExtras = $order['selectedExtras'] ?? [];
    $extrasCount = count($selectedExtras);
    
    if (isset($pkg['extras'])) {
        foreach ($pkg['extras'] as $ex) {
            if (in_array($ex['id'], $selectedExtras)) {
                $extrasTotal += (float)$ex['price'];
            }
        }
    }
    $total = $basePrice + $extrasTotal;
    $originalPrice = $total;

    // Apply Global Discount
    $globalDiscountVal = 0;
    if (isset($pricing['globalDiscount']) && $pricing['globalDiscount']['active']) {
        $gd = $pricing['globalDiscount'];
        if ($gd['type'] === 'percent') {
            $globalDiscountVal = $total * ((float)$gd['value'] / 100);
        } else {
            $globalDiscountVal = (float)$gd['value'];
        }
    }
    $total -= $globalDiscountVal;

    // Apply Package-Specific Bulk Discount
    $bulkDiscountVal = 0;
    if (isset($pkg['bulkDiscounts']) && $extrasCount > 0) {
        foreach ($pkg['bulkDiscounts'] as $bd) {
            if ((int)$bd['count'] === $extrasCount) {
                $bulkDiscountVal = $total * ((float)$bd['discountPercent'] / 100);
                $total -= $bulkDiscountVal;
                break;
            }
        }
    }

    // Apply Manual Discount (stored in order)
    $manualDiscountVal = 0;
    $manual = $order['discount'] ?? ['value' => 0, 'type' => 'euro'];
    if ($manual['type'] === 'percent') {
        $manualDiscountVal = $total * ((float)$manual['value'] / 100);
    } else {
        $manualDiscountVal = (float)$manual['value'];
    }
    $total -= $manualDiscountVal;

    $finalPrice = max(0, $total);
    $totalSavings = $originalPrice - $finalPrice;
    $discountText = "";

    if ($totalSavings > 0 && $originalPrice > 0) {
        $percent = round(($totalSavings / $originalPrice) * 100);
        $discountText = $percent . "% Rabatt";
    }

    return [
        'price' => number_format($finalPrice, 2, '.', ''),
        'originalPrice' => number_format($originalPrice, 2, '.', ''),
        'discountText' => $discountText
    ];
}
