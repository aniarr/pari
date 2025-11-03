<?php
header('Content-Type: application/json');

$food = isset($_GET['food']) ? trim($_GET['food']) : '';
$fdcId = isset($_GET['fdcId']) ? intval($_GET['fdcId']) : 0;
$apiKey = "iArAfNU8LJzMVhFz95YcKCeownuyZZpkYcb7WVkP";

if ($food === '' && $fdcId <= 0) {
    echo json_encode(['error' => 'No food specified and no fdcId provided']);
    exit;
}

function http_get($url, &$error = null) {
    // Try curl first
    if (function_exists('curl_init')) {
        $tries = 0;
        $maxTries = 2; // try twice before giving up
        $lastErr = null;
        while ($tries < $maxTries) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            // increase timeout to 20s and set a connect timeout
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            // ensure SSL verification is enabled (safer)
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

            $res = curl_exec($ch);
            $curlErr = curl_error($ch);
            $curlErrNo = curl_errno($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($curlErr) {
                $lastErr = 'Curl error: (' . $curlErrNo . ') ' . $curlErr;
                // retry briefly for transient network glitches
                $tries++;
                if ($tries < $maxTries) {
                    usleep(250000); // 250ms
                    continue;
                }
                $error = $lastErr;
                // write to local log for diagnostics
                @file_put_contents(__DIR__ . '/usda_fetch_errors.log', date('c') . " - $lastErr - URL: $url\n", FILE_APPEND);
                return false;
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                $lastErr = 'HTTP error: ' . $httpCode;
                $tries++;
                if ($tries < $maxTries) {
                    usleep(250000);
                    continue;
                }
                $error = $lastErr;
                @file_put_contents(__DIR__ . '/usda_fetch_errors.log', date('c') . " - $lastErr - URL: $url\n", FILE_APPEND);
                return false;
            }

            // success
            return $res;
        }
    }

    // Fallback to file_get_contents
    // fallback to file_get_contents with longer timeout
    $context = stream_context_create(['http' => ['timeout' => 20]]);
    $res = @file_get_contents($url, false, $context);
    if ($res === false) {
        $errMsg = 'file_get_contents failed';
        $error = $errMsg;
        @file_put_contents(__DIR__ . '/usda_fetch_errors.log', date('c') . " - $errMsg - URL: $url\n", FILE_APPEND);
        return false;
    }
    return $res;
}

// If fdcId provided, fetch that exact food's details directly
$detailsData = null;
$searchData = null;
$err = null;
if ($fdcId > 0) {
    $detailsUrl = "https://api.nal.usda.gov/fdc/v1/food/" . $fdcId . "?api_key=" . $apiKey;
    $detailsResponse = http_get($detailsUrl, $err);
    if ($detailsResponse === false) {
        echo json_encode(['error' => $err]);
        exit;
    }
    $detailsData = json_decode($detailsResponse, true);
    if (!is_array($detailsData)) {
        echo json_encode(['error' => 'Invalid food details for fdcId=' . $fdcId]);
        exit;
    }
} else {
    // Build search URL and return candidates so frontend can pick the exact fdcId
    $searchUrl = "https://api.nal.usda.gov/fdc/v1/foods/search?query=" . urlencode($food) . "&pageSize=8&api_key=" . $apiKey;
    $searchResponse = http_get($searchUrl, $err);
    if ($searchResponse === false) {
        echo json_encode(['error' => $err]);
        exit;
    }
    $searchData = json_decode($searchResponse, true);
    if (!is_array($searchData) || empty($searchData['foods'])) {
        $msg = 'Food not found';
        if (is_array($searchData) && !empty($searchData['errors'])) $msg .= ': ' . json_encode($searchData['errors']);
        echo json_encode(['error' => $msg]);
        exit;
    }

    // If there's exactly one candidate, fetch its details for convenience
    if (count($searchData['foods']) === 1 && !empty($searchData['foods'][0]['fdcId'])) {
        $fdcId = intval($searchData['foods'][0]['fdcId']);
        $detailsUrl = "https://api.nal.usda.gov/fdc/v1/food/" . $fdcId . "?api_key=" . $apiKey;
        $detailsResponse = http_get($detailsUrl, $err);
        if ($detailsResponse === false) {
            echo json_encode(['error' => $err]);
            exit;
        }
        $detailsData = json_decode($detailsResponse, true);
        if (!is_array($detailsData)) {
            echo json_encode(['error' => 'Invalid food details']);
            exit;
        }
    }
}

// Robust nutrient extraction supporting multiple USDA response shapes
// If we didn't fetch details (because search returned multiple items), return candidates for frontend selection
if ($detailsData === null && is_array($searchData) && !empty($searchData['foods'])) {
    $candidates = [];
    foreach ($searchData['foods'] as $f) {
        $candidates[] = [
            'fdcId' => isset($f['fdcId']) ? intval($f['fdcId']) : 0,
            'description' => $f['description'] ?? '',
            'dataType' => $f['dataType'] ?? '',
            'brandOwner' => $f['brandOwner'] ?? '',
            'serving_size' => isset($f['servingSize']) ? $f['servingSize'] : (isset($f['serving_size']) ? $f['serving_size'] : null),
            'serving_unit' => $f['servingSizeUnit'] ?? (isset($f['serving_size_unit']) ? $f['serving_size_unit'] : null),
        ];
    }
    echo json_encode(['candidates' => $candidates, 'query' => $food]);
    exit;
}

// Robust nutrient extraction supporting multiple USDA response shapes
function extract_nutrients_from_details($details) {
    $result = ['calories' => null, 'protein' => null, 'carbs' => null, 'fat' => null];

    // 1) branded foods may include labelNutrients
    if (!empty($details['labelNutrients']) && is_array($details['labelNutrients'])) {
        $ln = $details['labelNutrients'];
        if (isset($ln['calories']['value'])) $result['calories'] = floatval($ln['calories']['value']);
        if (isset($ln['protein']['value'])) $result['protein'] = floatval($ln['protein']['value']);
        if (isset($ln['carbohydrates']['value'])) $result['carbs'] = floatval($ln['carbohydrates']['value']);
        if (isset($ln['fat']['value'])) $result['fat'] = floatval($ln['fat']['value']);
        return $result;
    }

    // 2) standard foods: iterate foodNutrients with varying shapes
    if (!empty($details['foodNutrients']) && is_array($details['foodNutrients'])) {
        foreach ($details['foodNutrients'] as $nutrient) {
            // possible shapes
            $name = $nutrient['nutrientName'] ?? ($nutrient['nutrient']['name'] ?? ($nutrient['nutrient']['nutrientName'] ?? ($nutrient['name'] ?? '')));
            $unit = $nutrient['unitName'] ?? ($nutrient['nutrient']['unitName'] ?? ($nutrient['unit'] ?? ''));
            $value = $nutrient['value'] ?? $nutrient['amount'] ?? ($nutrient['nutrient']['value'] ?? null);
            if ($value === null) continue;
            $value = floatval($value);

            if (stripos($name, 'energy') !== false || stripos($name, 'calorie') !== false) {
                if (strtolower($unit) === 'kj') $result['calories'] = $value / 4.184; else $result['calories'] = $value;
            }
            if (stripos($name, 'protein') !== false) {
                if (strtolower($unit) === 'mg') $result['protein'] = $value / 1000; else $result['protein'] = $value;
            }
            if (stripos($name, 'carbohydrate') !== false) {
                if (strtolower($unit) === 'mg') $result['carbs'] = $value / 1000; else $result['carbs'] = $value;
            }
            if (stripos($name, 'lipid') !== false || stripos($name, 'fat') !== false) {
                if (strtolower($unit) === 'mg') $result['fat'] = $value / 1000; else $result['fat'] = $value;
            }
        }
    }

    return $result;
}

$extracted = extract_nutrients_from_details($detailsData);
$calories = $extracted['calories'];
$protein = $extracted['protein'];
$carbs = $extracted['carbs'];
$fat = $extracted['fat'];

// Debug option: return raw search + details for inspection
if (!empty($_GET['debug'])) {
    echo json_encode(['debug' => true, 'search' => $searchData, 'details' => $detailsData, 'extracted' => $extracted]);
    exit;
}

// Default zeros if null and final response
$calories = $calories !== null ? round($calories, 2) : 0;
$protein = $protein !== null ? round($protein, 2) : 0;
$carbs = $carbs !== null ? round($carbs, 2) : 0;
$fat = $fat !== null ? round($fat, 2) : 0;

echo json_encode([
    'food' => $detailsData['description'] ?? ($searchData['foods'][0]['description'] ?? $food),
    'calories' => $calories,
    'protein' => $protein,
    'carbs' => $carbs,
    'fat' => $fat,
    // include serving metadata when available so frontend can scale per-gram or per-serving
    'serving_size' => isset($detailsData['servingSize']) ? $detailsData['servingSize'] : (isset($searchData['foods'][0]['servingSize']) ? $searchData['foods'][0]['servingSize'] : null),
    'serving_unit' => isset($detailsData['servingSizeUnit']) ? $detailsData['servingSizeUnit'] : (isset($searchData['foods'][0]['servingSizeUnit']) ? $searchData['foods'][0]['servingSizeUnit'] : null),
    'household_serving' => isset($detailsData['householdServingFullText']) ? $detailsData['householdServingFullText'] : (isset($searchData['foods'][0]['householdServingFullText']) ? $searchData['foods'][0]['householdServingFullText'] : null),
    'source' => (!empty($detailsData['labelNutrients']) ? 'label' : 'foodNutrients')
]);
exit;

