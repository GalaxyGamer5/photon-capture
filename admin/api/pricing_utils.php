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
    $total += $extrasTotal;

    // Apply Global Discount
    if (isset($pricing['globalDiscount']) && $pricing['globalDiscount']['active']) {
        $gd = $pricing['globalDiscount'];
        if ($gd['type'] === 'percent') {
            $total *= (1 - ((float)$gd['value'] / 100));
        } else {
            $total -= (float)$gd['value'];
        }
    }

    // Apply Package-Specific Bulk Discount
    if (isset($pkg['bulkDiscounts']) && $extrasCount > 0) {
        foreach ($pkg['bulkDiscounts'] as $bd) {
            if ((int)$bd['count'] === $extrasCount) {
                $total *= (1 - ((float)$bd['discountPercent'] / 100));
                break;
            }
        }
    }

    // Apply Manual Discount (stored in order)
    $manual = $order['discount'] ?? ['value' => 0, 'type' => 'euro'];
    if ($manual['type'] === 'percent') {
        $total *= (1 - ((float)$manual['value'] / 100));
    } else {
        $total -= (float)$manual['value'];
    }

    return number_format(max(0, $total), 2, '.', '');
}
