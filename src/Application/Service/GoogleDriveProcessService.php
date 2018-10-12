<?php

namespace XmlSquad\Library\Application\Service;

use Exception;
use XmlSquad\Library\Model\Domain\DomainGSheetObjectFactoryInterface;
use XmlSquad\Library\GoogleAPI\GoogleAPIClient;


use XmlSquad\Library\GoogleAPI\GoogleDriveProcessorInterface;

class GoogleDriveProcessService
{
    /** @var GoogleAPIClient */
    private $googleAPIClient;

    /** @var DomainGSheetObjectFactoryInterface */
    private $domainGSheetObjectFactory;

    /** @var googleDriveProcessor */
    private $googleDriveProcessor;

    public function __construct(
        GoogleAPIClient $googleAPIClient
    ) {
        $this->googleAPIClient = $googleAPIClient;
    }

    public function processGoogleUrl(GoogleDriveProcessorInterface $googleDriveProcessor, string $url, bool $recursive, DomainGSheetObjectFactoryInterface $domainGSheetObjectFactory)
    {
        if ($this->isSpreadsheet($url)) {
            return $this->processGoogleSpreadsheet($googleDriveProcessor, $url, $domainGSheetObjectFactory);
        }

        if ($this->isFolder($url)) {
            return $this->processGoogleFolder($googleDriveProcessor, $url, $recursive, $domainGSheetObjectFactory);
        }

        throw new Exception('URL is not either Google Spreadsheet nor Google Drive Folder');
    }

    public function parseFolderIdFromUrl(string $url): ?string
    {
        preg_match("/\/folders\/([a-zA-Z0-9-_]+)\/?/", $url, $result);

        return $result[1] ?? null;
    }

    public function isFolder(string $url): bool
    {
        if (strpos($url, '/folders/') !== false) {
            return true;
        }

        return false;
    }

    /**
     * @see https://developers.google.com/sheets/api/guides/concepts
     */
    public function parseSpreadsheetIdFromUrl(string $url): ?string
    {
        preg_match("/\/spreadsheets\/d\/([a-zA-Z0-9-_]+)/", $url, $result);

        return $result[1] ?? null;
    }

    public function isSpreadsheet(string $url): bool
    {
        if (strpos($url, '/spreadsheets/') !== false) {
            return true;
        }

        return false;
    }

    protected function processGoogleSpreadsheet(GoogleDriveProcessorInterface $googleDriveProcessor, string $spreadsheetUrl, DomainGSheetObjectFactoryInterface $domainGSheetObjectFactory): string
    {
        $spreadsheetId = $this->parseSpreadsheetIdFromUrl($spreadsheetUrl);
        if (true === empty($spreadsheetId)) {
            throw new Exception('Cant parse spreadsheet ID from the URL [' . $spreadsheetUrl .']');
        }

        $service = new GoogleSpreadsheetReadService($this->googleAPIClient);
        $spreadsheetData = $service->getSpreadsheetData($spreadsheetId, $domainGSheetObjectFactory->createGSuiteHandlingSpecifications());

        $domainGSheetObjects = [];
        foreach ($spreadsheetData as $domainGSheetObjectData) {
            $domainGSheetObjects[] = $domainGSheetObjectFactory->createDomainGSheetObject($domainGSheetObjectData, $spreadsheetUrl);
        }

        return $googleDriveProcessor->processDomainGSheetObjects($domainGSheetObjects);
    }

    protected function processGoogleFolder(GoogleDriveProcessorInterface $googleDriveProcessor, string $url, bool $recursive, DomainGSheetObjectFactoryInterface $domainGSheetObjectFactory)
    {
        $folderId = $this->parseFolderIdFromUrl($url);
        if (true === empty($folderId)) {
            throw new Exception('Cant parse folder ID from the URL ' . $url);
        }

        $driveService = new GoogleDriveFolderReadService($this->googleAPIClient);
        $spreadsheetFileIds = $driveService->listSpreaadsheetsInFolder($folderId, $recursive, $domainGSheetObjectFactory->createGSuiteHandlingSpecifications());

        /**
         * Each Google Sheet tab represents one of these: <Product><Inventory>...data here..</Inventory></Product>.
         */
        $spreadsheetService = new GoogleSpreadsheetReadService($this->googleAPIClient);
        $domainGSheetObjects = [];
        foreach ($spreadsheetFileIds as $spreadsheetFileId) {
            $sheetsData = $spreadsheetService->getSpreadsheetData($spreadsheetFileId, $domainGSheetObjectFactory->createGSuiteHandlingSpecifications());
            $sheetUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetFileId}/";

            foreach ($sheetsData as $sheetData) {
                $domainGSheetObjects[] = $domainGSheetObjectFactory->createDomainGSheetObject($sheetData, $sheetUrl);
            }
        }

        return $googleDriveProcessor->processDomainGSheetObjects($domainGSheetObjects);
    }
}
