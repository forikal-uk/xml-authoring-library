<?php
/**
 * Created by PhpStorm.
 * User: jw
 * Date: 10/10/2018
 * Time: 08:06
 */

namespace XmlSquad\Library\GoogleAPI;


interface GSuiteHandlingSpecificationsInterface
{

    /**
     * Range limit when getting data from spreadsheet.
     *
     * @return string Column identity of maximum range to get from sheet.
     */
    public function getColumnRangeLimit(): string;

    /**
     * If a file is called foo_, then it is assumed to be 'private' and should be explicitly ignored,
     *
     *
     * Test if full file name ends with _ or only filename without the extension
     * i.e. foo_.xlsx and foo__
     *
     * @param $fullName of Google Sheet
     * @return bool
     */
    public function isGSheetFileNameIgnored($fullName): bool;

    /**
     *  If a Google Sheet's tab is named foo_, then it is assumed to be 'private'.
     *
     * @param $title
     * @return bool
     */
    public function isGSheetTabNameIgnored($title): bool;

    /**
     * @param array|null $row
     * @param \XmlSquad\GsheetXml\Model\Domain\DomainGSheetObjectFactoryInterface $domainGSheetObjectFactory
     * @return bool
     */
    public function isHeadingsRow(?array $row): bool;


}