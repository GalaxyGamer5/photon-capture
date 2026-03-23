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
    $extrasCount = 0;
    $selectedExtras = $order['selectedExtras'] ?? [];
    
    if (isset($pkg['extras'])) {
        foreach ($pkg['extras'] as $extra) {
            $type = $extra['type'] ?? 'checkbox';
            if ($type === 'select') {
                foreach ($selectedExtras as $selected) {
                    if (strpos($selected, $extra['id'] . ':') === 0) {
                        $optionId = substr($selected, strlen($extra['id']) + 1);
                        foreach ($extra['options'] as $opt) {
                            if ($opt['id'] === $optionId) {
                                $extrasTotal += (float)$opt['price'];
                                // Only count as extra if it has a price or is not a "none/standard" option
                                if ($opt['id'] !== 'none' && $opt['id'] !== 'standard' && $opt['id'] !== 'opt0') {
                                    $extrasCount++;
                                }
                                break;
                            }
                        }
                    }
                }
            } else {
                if (in_array($extra['id'], $selectedExtras)) {
                    $extrasTotal += (float)($extra['price'] ?? 0);
                    $extrasCount++;
                }
            }
        }
    }
    $total += $extrasTotal;
    $originalPrice = $total;

    $totalPercentDiscount = 0; // Cumulative percentage
    $totalFlatDiscount = 0;

    // 1. Collect Percentage Discounts
    if (isset($pricing['globalDiscount']) && $pricing['globalDiscount']['active']) {
        $gd = $pricing['globalDiscount'];
        if ($gd['type'] === 'percent') {
            $totalPercentDiscount += (float)$gd['value'];
        }
    }
    if (isset($pkg['bulkDiscounts']) && $extrasCount > 0) {
        foreach ($pkg['bulkDiscounts'] as $bd) {
            if ((int)$bd['count'] === $extrasCount) {
                $totalPercentDiscount += (float)$bd['discountPercent'];
                break;
            }
        }
    }
    $manual = $order['discount'] ?? ['value' => 0, 'type' => 'euro'];
    if ($manual['type'] === 'percent') {
        $totalPercentDiscount += (float)$manual['value'];
    }

    // Apply Percentages (Summed up to avoid compounding vs sequence confusion, following "Percent first")
    $percentSavings = $total * ($totalPercentDiscount / 100);
    $total -= $percentSavings;

    // 2. Collect Flat Discounts
    if (isset($pricing['globalDiscount']) && $pricing['globalDiscount']['active']) {
        $gd = $pricing['globalDiscount'];
        if ($gd['type'] !== 'percent') {
            $totalFlatDiscount += (float)$gd['value'];
        }
    }
    if ($manual['type'] !== 'percent') {
        $totalFlatDiscount += (float)$manual['value'];
    }

    // Apply Flats
    $total -= $totalFlatDiscount;

    $finalPrice = max(0, $total);
    
    // Build discountText: "25% / -10€"
    $parts = [];
    if ($totalPercentDiscount > 0) {
        $parts[] = $totalPercentDiscount . "%";
    }
    if ($totalFlatDiscount > 0) {
        $parts[] = "-" . number_format($totalFlatDiscount, 0, '.', '') . "€";
    }
    
    $discountText = implode(" / ", $parts);

    return [
        'price' => number_format($finalPrice, 2, '.', ''),
        'originalPrice' => number_format($originalPrice, 2, '.', ''),
        'discountText' => $discountText
    ];
}
