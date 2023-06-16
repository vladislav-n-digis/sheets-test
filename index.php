<?php

require_once __DIR__ . '/vendor/autoload.php';

use Google\Service\Sheets;
use Google\Service\Sheets\Request;
use Google\Service\Sheets\InsertDimensionRequest;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\ValueRange;
use Google\Service\Sheets\BatchUpdateValuesRequest;

$spreadsheetId = $_GET['spreadsheet_id'];

$input = getInput();
$firstColumnValue = $input[0];

$spreadsheetService = initSpreadsheetsService();

try {
    $parser = new SpreadsheetValuesParser($spreadsheetId, $spreadsheetService);
    $numberPosition = $parser->getPositionOfNumber($firstColumnValue);

    $operator = new SpreadsheetOperations($spreadsheetId, $spreadsheetService, $numberPosition);
    $operator->insertEmptyRowInPostion();
    $operator->insertDataInEmptyRow($input);
} catch (Exception $exception) {
    printf($exception->getMessage());
}

function getInput(): array
{
    $input[] = $_GET['param1'];
    $input[] = $_GET['param2'];
    $input[] = $_GET['param3'];

    return $input;
}

function initSpreadsheetsService(): Sheets
{
    $path = 'sheets-credentials.json';

    $client = new \Google\Client();
    $client->setApplicationName('Google Sheets API');
    $client->setScopes([\Google\Service\Sheets::SPREADSHEETS]);
    $client->setAccessType('offline');
    $client->setAuthConfig($path);

    return new Sheets($client);
}

class SpreadsheetValuesParser
{
    public Sheets $service;
    public string $spreadsheetId;

    public function __construct(string $spreadsheetId, Sheets $service)
    {
        $this->spreadsheetId = $spreadsheetId;
        $this->service = $service;
    }

    public function getPositionOfNumber(int $number): int
    {
        $firstColumnValues = $this->getFirstColumnValues($this->spreadsheetId, $this->service);
        $firstColumnValues = $this->removeInsideArrayOfValues($firstColumnValues);
        $numberPosition = $this->getPositionOfNumberInSortedArray($firstColumnValues, $number);

        return $numberPosition;
    }

    public function getFirstColumnValues(): array
    {
        $range = 'Sheet1!A:A';
        $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range);
        (array) $values = $response->getValues();

        if (count($values) == 0) {
            throw new Exception('No values in return of getFirstColumnValues method!');
        }

        return $values;
    }

    private function removeInsideArrayOfValues(array $values): array
    {
        foreach ($values as $id => $value) {
            $values[$id] = $value[0];
        }

        return $values;
    }

    private function getPositionOfNumberInSortedArray(array $array, int $number): int
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
}

class SpreadsheetOperations
{
    public string $spreadsheetId;
    public Sheets $service;
    public int $numberPosition;

    public function __construct(string $spreadsheetId, Sheets $service, int $numberPosition)
    {
        $this->spreadsheetId = $spreadsheetId;
        $this->service = $service;
        $this->numberPosition = $numberPosition;
    }

    public function insertEmptyRowInPostion(): void
    {
        $insertRequest = new Request();
        $insertRequest->setInsertDimension(new InsertDimensionRequest([
            'range' => [
                'sheetId' => 0,  // First sheet
                'dimension' => 'ROWS',
                'startIndex' => $this->numberPosition,
                'endIndex' => $this->numberPosition + 1,
            ],
            'inheritFromBefore' => false,
        ]));

        $batchUpdateRequest = new BatchUpdateSpreadsheetRequest([
            'requests' => [$insertRequest],
        ]);

        $response = $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $batchUpdateRequest);

        if ($response instanceof \Google\Service\Exception) {
            $error = $response->getErrors()[0];
            throw new Exception($error['message']);
        } else {
            printf("New dimension inserted successfully.\n");
        }
    }

    public function insertDataInEmptyRow(array $input): void
    {
        $range = 'Sheet1!A' . $this->numberPosition + 1 . ':C' . $this->numberPosition + 1;

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

        $response = $this->service->spreadsheets_values->batchUpdate($this->spreadsheetId, $batchUpdateRequest);

        if ($response instanceof \Google\Service\Exception) {
            $error = $response->getErrors()[0];
            throw new Exception($error['message']);
        } else {
            printf("Cells updated successfully.\n");
        }
    }
}