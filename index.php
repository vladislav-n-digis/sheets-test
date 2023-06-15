<?php

require_once __DIR__ . '/vendor/autoload.php';

use Google\Service\Sheets;
use Google\Service\Sheets\Request;
use Google\Service\Sheets\InsertDimensionRequest;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\ValueRange;
use Google\Service\Sheets\BatchUpdateValuesRequest;

$spreadsheetId = $_GET['spreadsheet_id'];

function getInput(): array {
    $input[] = $_GET['param1'];
    $input[] = $_GET['param2'];
    $input[] = $_GET['param3'];

    return $input;
}

function initService(): Sheets {
    $path = 'sheets-credentials.json';

    $client = new \Google\Client();
    $client->setApplicationName('Google Sheets API');
    $client->setScopes([\Google\Service\Sheets::SPREADSHEETS]);
    $client->setAccessType('offline');
    $client->setAuthConfig($path);

    return new Sheets($client);
}

function getFirstColumnValues(string $spreadsheetId, Sheets $service): array 
{
    $range = 'Sheet1!A:A';
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    (array) $values = $response->getValues();

    return $values;
}

function removeInsideArrayOfValues(array $values): array 
{
    foreach ($values as $id => $value) {
        $values[$id] = $value[0];
    }

    return $values;
}

function getPositionOfNumberInSortedArray(array $array, int $number): int 
{
    $low = 0;
    $high = count($array) - 1;

    while ($low <= $high) {
        $mid = (int)(($low + $high) / 2);

        if ($array[$mid] == $number) {
            return $array;
        } elseif ($array[$mid] < $number) {
            $low = $mid + 1;
        } else {
            $high = $mid - 1;
        }
    }

    array_splice($array, $low, 0, $number);
    return $low;
}

function insertEmptyRowInPostion(int $numberPosition, Sheets $service, string $spreadsheetId): void
{
    $insertRequest = new Request();
    $insertRequest->setInsertDimension(new InsertDimensionRequest([
        'range' => [
            'sheetId' => 0,  // First sheet
            'dimension' => 'ROWS',
            'startIndex' => $numberPosition,
            'endIndex' => $numberPosition + 1,
        ],
        'inheritFromBefore' => false,
    ]));

    $batchUpdateRequest = new BatchUpdateSpreadsheetRequest([
        'requests' => [$insertRequest],
    ]);

    $response = $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);

    if ($response instanceof \Google\Service\Exception) {
        $error = $response->getErrors()[0];
        printf("Error: %s - %s\n", $error['code'], $error['message']);
    } else {
        printf("New dimension inserted successfully.\n");
    }
}

function insertDataInEmptyRow(int $numberPosition, array $input, Sheets $service, string $spreadsheetId): void
{
    $range = 'Sheet1!A' . $numberPosition + 1 . ':C' . $numberPosition + 1;

    $updateValues = new ValueRange([
        'range' => $range,
        'values' => [
            $input
        ]
    ]);

    $batchUpdateRequest = new BatchUpdateValuesRequest([
        'data' => [$updateValues],
        'valueInputOption' => 'USER_ENTERED',
    ]);

    $response = $service->spreadsheets_values->batchUpdate($spreadsheetId, $batchUpdateRequest);

    if ($response instanceof \Google\Service\Exception) {
        $error = $response->getErrors()[0];
        printf("Error: %s - %s\n", $error['code'], $error['message']);
    } else {
        printf("Cells updated successfully.\n");
    }
}

$input = getInput();
$service = initService();
$firstColumnValues = getFirstColumnValues($spreadsheetId, $service);
$firstColumnValues = removeInsideArrayOfValues($firstColumnValues);
$numberPosition = getPositionOfNumberInSortedArray($firstColumnValues, $input[0]);
insertEmptyRowInPostion($numberPosition, $service, $spreadsheetId);
insertDataInEmptyRow($numberPosition, $input, $service, $spreadsheetId);